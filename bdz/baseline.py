import re
import pandas as pd
import torch
from sentence_transformers import CrossEncoder

# ========= 1) Загрузка данных =========
#df = pd.read_csv("brave_url.csv")

# При желании потом поменяешь на yandex_url.csv
df = pd.read_csv("yandex_url.csv")

# На всякий случай нормализуем названия колонок
df.columns = [c.strip().lower() for c in df.columns]

required = ["db", "query", "url", "snippet"]
missing = [c for c in required if c not in df.columns]
if missing:
    raise ValueError(f"Нет колонок: {missing}. Есть: {df.columns.tolist()}")

# ========= 2) Модель =========
model = CrossEncoder(
    "cross-encoder/ms-marco-MiniLM-L6-v2",
    max_length=512,
    device="cuda",
    activation_fn=torch.nn.Sigmoid()
)

# ========= 3) Словари правил =========
POSITIVE_TERMS = [
    "big data", "bigdata", "большие данные",
    "hadoop", "spark", "hdfs", "data lake", "озеро данных",
    "data warehouse", "etl", "analytics", "аналитик",
    "big data appliance", "oracle big data appliance",
    "apache hive", "apache kafka", "nosql", "distributed", "распредел"
]

NEGATIVE_TERMS = [
    "lob", "blob", "clob", "nclob", "bfile",
    "размер бд", "размер базы", "размер таблиц", "largest tables",
    "dba_data_files", "v$datafile", "v$datafile",
    "sql file", ".sql", "import sql", "oraclelob",
    "chunk parameter", "oracle lobs", "просмотр размера",
    "найти самые большие таблицы", "импорт файла"
]

BAD_DOMAINS = [
    "coursera.org", "youtube.com", "youtu.be", "pinterest.com",
    "translate", "studentorg", "calendar-uk.co.uk"
]

GOOD_DOMAINS_HINT = [
    "oracle.com", "enterprisedb.com", "postgresql.org",
    "percona.com", "cybertec-postgresql.com"
]

def norm_text(x):
    x = "" if pd.isna(x) else str(x)
    x = x.lower()
    x = re.sub(r"<[^>]+>", " ", x)
    x = re.sub(r"\s+", " ", x).strip()
    return x

def count_terms(text, terms):
    return sum(1 for t in terms if t in text)

def rule_score(url, snippet):
    text = f"{url} {snippet}"
    text = norm_text(text)

    pos = count_terms(text, POSITIVE_TERMS)
    neg = count_terms(text, NEGATIVE_TERMS)
    bad_domain = any(d in text for d in BAD_DOMAINS)
    good_domain = any(d in text for d in GOOD_DOMAINS_HINT)

    score = 0.0
    score += pos * 0.18
    score -= neg * 0.22

    if good_domain:
        score += 0.08
    if bad_domain:
        score -= 0.20

    return max(-1.0, min(1.0, score)), pos, neg, good_domain, bad_domain

# ========= 4) Подготовка пар для модели =========
pairs = []
for _, row in df.iterrows():
    query_text = f"{row['db']} {row['query']}"
    doc_text = f"{row['url']} {row['snippet']}"
    pairs.append((query_text, doc_text))

# ========= 5) Score модели =========
model_scores = model.predict(pairs, batch_size=32, show_progress_bar=True)

# На всякий случай подрежем в [0, 1]
model_scores = [float(max(0.0, min(1.0, s))) for s in model_scores]

# ========= 6) Rule-based признаки =========
rule_scores = []
pos_hits = []
neg_hits = []
good_domain_flags = []
bad_domain_flags = []

for _, row in df.iterrows():
    rs, ph, nh, gd, bd = rule_score(row["url"], row["snippet"])
    rule_scores.append(rs)
    pos_hits.append(ph)
    neg_hits.append(nh)
    good_domain_flags.append(gd)
    bad_domain_flags.append(bd)

df["model_score"] = model_scores
df["rule_score"] = rule_scores
df["positive_hits"] = pos_hits
df["negative_hits"] = neg_hits
df["good_domain"] = good_domain_flags
df["bad_domain"] = bad_domain_flags

# ========= 7) Комбинированный score =========
# model_score: 0..1
# rule_score: -1..1  -> переведем в 0..1
df["rule_score_01"] = (df["rule_score"] + 1) / 2

# Можно менять веса
df["final_score"] = 0.7 * df["model_score"] + 0.3 * df["rule_score_01"]

# ========= 8) Классы =========
def assign_label(row):
    fs = row["final_score"]
    neg = row["negative_hits"]
    pos = row["positive_hits"]

    # жесткий оффтопик
    if neg >= 2 and pos == 0 and fs < 0.55:
        return "spam"

    if fs >= 0.72:
        return "useful"
    elif fs >= 0.45:
        return "unknown"
    else:
        return "spam"

df["label"] = df.apply(assign_label, axis=1)

# ========= 9) Сохраняем =========
#df.to_csv("brave_scored.csv", index=False, encoding="utf-8-sig")
df.to_csv("yandex_scored.csv", index=False, encoding="utf-8-sig")

# ========= 10) Просмотр первых результатов =========
preview_cols = [
    "db", "query", "url", "model_score", "rule_score",
    "final_score", "positive_hits", "negative_hits", "label"
]

print(df[preview_cols].head(30).to_string(index=False))