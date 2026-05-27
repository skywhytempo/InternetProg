"""import re
import pandas as pd
import torch
from sentence_transformers import CrossEncoder

# ========= 1) Загрузка данных =========
#df = pd.read_csv("data/brave_url.csv")

# При желании потом поменяешь на yandex_url.csv
df = pd.read_csv("data/yandex_url.csv")

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
#df.to_csv("data/brave_scored.csv", index=False, encoding="utf-8-sig")
df.to_csv("data/yandex_scored.csv", index=False, encoding="utf-8-sig")

# ========= 10) Просмотр первых результатов =========
preview_cols = [
    "db", "query", "url", "model_score", "rule_score",
    "final_score", "positive_hits", "negative_hits", "label"
]

print(df[preview_cols].head(30).to_string(index=False))"""

import re
import pandas as pd
import torch
from sentence_transformers import CrossEncoder



# =========================
# 1) Загрузка
# =========================
#df = pd.read_csv("data/brave_url.csv")
df = pd.read_csv("data/yandex_url.csv")

df.columns = [c.strip().lower() for c in df.columns]

required = ["db", "query", "url", "snippet"]
missing = [c for c in required if c not in df.columns]
if missing:
    raise ValueError(f"Нет колонок: {missing}. Есть: {df.columns.tolist()}")


# =========================
# 2) Модель
# =========================
model = CrossEncoder(
    "cross-encoder/ms-marco-MiniLM-L6-v2",
    max_length=512,
    device="cuda",
    activation_fn=torch.nn.Sigmoid()
)

# =========================
# 3) Словари
# =========================
POSITIVE_TERMS = [
    "big data", "bigdata", "большие данные",
    "data lake", "озеро данных",
    "hadoop", "spark", "hdfs", "hive", "kafka", "hbase",
    "analytics", "analytic", "аналитик", "анализ данных",
    "data warehouse", "warehouse",
    "etl", "elt",
    "nosql", "distributed", "распредел",
    "oci big data", "oracle big data",
    "big data appliance", "big data service", "big data sql",
    "data platform", "massive volumes of data"
]

NEGATIVE_TERMS = [
    "lob", "blob", "clob", "nclob", "bfile", "oraclelob",
    "large object", "binary large object",
    "размер бд", "размер базы", "размер таблиц", "размер таблицы",
    "largest tables", "big table", "большие таблицы",
    "sql file", ".sql", "import sql", "импорт файла", "sql developer",
    "chunk parameter", "dba_data_files", "dba_segments", "v$datafile",
    "просмотр размера", "найти самые большие таблицы",
    "datatype limits", "limits for datatypes",
    "табличное пространство", "tablespace"
]

POSITIVE_URL_PATTERNS = [
    "/big-data/",
    "big-data-sql",
    "big-data-appliance",
    "big-data-service",
    "datawarehouse-bigdata",
    "big-data-cloud",
    "big-data-discovery",
    "iaas/bigdata",
    "/bigdata/"
]

NEGATIVE_URL_PATTERNS = [
    "oracle-lobs",
    "lob_oracle",
    "razmer",
    "razmer-bazy",
    "razmer-tabl",
    "blob",
    "clob",
    "sql-fayl",
    "import-bazy",
    "datatype-limits"
]

GOOD_DOMAINS = [
    "oracle.com",
    "docs.oracle.com",
    "cloud.oracle.com",
    "oci.oraclecloud.com",
    "enterprisedb.com",
    "postgresql.org",
    "percona.com",
    "cybertec-postgresql.com"
    "en.wikipedia.org",
    "ru.wikipedia.org"
]

BAD_DOMAINS = [
    "youtube.com",
    "youtu.be",
    "pinterest.com",
    "coursera.org",
    "classcentral.com"
]

# =========================
# 4) Вспомогательные функции
# =========================
def clean_text(x):
    x = "" if pd.isna(x) else str(x)
    x = x.lower()
    x = re.sub(r"<[^>]+>", " ", x)
    x = re.sub(r"\s+", " ", x).strip()
    return x

def contains_cyrillic(text):
    return bool(re.search(r"[а-яё]", text.lower()))

def contains_latin(text):
    return bool(re.search(r"[a-z]", text.lower()))

def detect_lang_simple(text):
    text = clean_text(text)
    cyr = len(re.findall(r"[а-яё]", text))
    lat = len(re.findall(r"[a-z]", text))
    if cyr > lat * 1.3 and cyr >= 3:
        return "ru"
    if lat > cyr * 1.3 and lat >= 3:
        return "en"
    return "mixed"

def count_terms(text, terms):
    return sum(1 for t in terms if t in text)

def count_url_patterns(url, patterns):
    url = clean_text(url)
    return sum(1 for p in patterns if p in url)

def is_good_domain(url):
    url = clean_text(url)
    return any(d in url for d in GOOD_DOMAINS)

def is_bad_domain(url):
    url = clean_text(url)
    return any(d in url for d in BAD_DOMAINS)

def compute_rule_features(row):
    url = clean_text(row["url"])
    snippet = clean_text(row["snippet"])
    query = clean_text(row["query"])
    db = clean_text(row["db"])

    full_text = f"{db} {query} {url} {snippet}"

    pos_hits = count_terms(full_text, POSITIVE_TERMS)
    neg_hits = count_terms(full_text, NEGATIVE_TERMS)

    pos_url_hits = count_url_patterns(url, POSITIVE_URL_PATTERNS)
    neg_url_hits = count_url_patterns(url, NEGATIVE_URL_PATTERNS)

    good_domain = is_good_domain(url)
    bad_domain = is_bad_domain(url)

    query_lang = detect_lang_simple(query)
    snippet_lang = detect_lang_simple(snippet)
    mixed_lang = query_lang != snippet_lang and "mixed" not in (query_lang, snippet_lang)

    score = 0.0

    # Базовые сигналы
    score += pos_hits * 0.16
    score -= neg_hits * 0.28

    score += pos_url_hits * 0.22
    score -= neg_url_hits * 0.30

    if good_domain:
        score += 0.10
    if bad_domain:
        score -= 0.15

    # Особое усиление: official big data page
    if good_domain and (pos_url_hits >= 1 or pos_hits >= 2):
        score += 0.18

    # Если mixed-language, но strong big data сигналы есть, а оффтопика нет — спасаем
    rescue_flag = False
    if mixed_lang and good_domain and (pos_url_hits >= 1 or pos_hits >= 2) and neg_hits == 0 and neg_url_hits == 0:
        score += 0.28
        rescue_flag = True

    # Если mixed-language, но при этом LOB/BLOB/size — не спасаем
    if mixed_lang and (neg_hits >= 1 or neg_url_hits >= 1):
        score -= 0.08

    score = max(-1.0, min(1.0, score))

    return {
        "rule_score": score,
        "pos_hits": pos_hits,
        "neg_hits": neg_hits,
        "pos_url_hits": pos_url_hits,
        "neg_url_hits": neg_url_hits,
        "good_domain": good_domain,
        "bad_domain": bad_domain,
        "query_lang": query_lang,
        "snippet_lang": snippet_lang,
        "mixed_lang": mixed_lang,
        "rescue_flag": rescue_flag
    }

# =========================
# 5) Пары для модели
# =========================
pairs = []
for _, row in df.iterrows():
    query_text = f"{row['db']} | {row['query']}"
    doc_text = f"{row['url']} | {row['snippet']}"
    pairs.append((query_text, doc_text))

model_scores = model.predict(pairs, batch_size=32, show_progress_bar=True)
model_scores = [float(max(0.0, min(1.0, s))) for s in model_scores]

df["model_score"] = model_scores

# =========================
# 6) Rule features
# =========================
features = df.apply(compute_rule_features, axis=1, result_type="expand")
df = pd.concat([df, features], axis=1)

# rule_score: [-1, 1] -> [0, 1]
df["rule_score_01"] = (df["rule_score"] + 1.0) / 2.0

# =========================
# 7) Финальный score
# =========================
# Уменьшаем роль модели, потому что mixed-language пары ломают scorer
df["final_score"] = (
    0.45 * df["model_score"] +
    0.55 * df["rule_score_01"]
)

# =========================
# 8) Итоговая метка
# =========================
def assign_label(row):
    fs = row["final_score"]
    neg = row["neg_hits"]
    pos = row["pos_hits"]
    pos_url = row["pos_url_hits"]
    neg_url = row["neg_url_hits"]
    rescue = row["rescue_flag"]
    good_domain = row["good_domain"]

    # Жесткий оффтопик
    if (neg + neg_url) >= 2 and (pos + pos_url) == 0:
        return "spam"

    # LOB/BLOB/size темы обычно не Big Data
    if neg >= 1 and neg_url >= 1 and fs < 0.70:
        return "spam"

    # Mixed-language rescue для official big data страниц
    if rescue and good_domain and (pos + pos_url) >= 2 and fs >= 0.52:
        return "useful"

    if fs >= 0.68:
        return "useful"
    elif fs >= 0.44:
        return "unknown"
    else:
        return "spam"

df["label"] = df.apply(assign_label, axis=1)

# =========================
# 9) Сохранение
# =========================
out_cols = [
    "db", "query", "url", "snippet",
    "model_score", "rule_score", "rule_score_01", "final_score",
    "pos_hits", "neg_hits", "pos_url_hits", "neg_url_hits",
    "good_domain", "bad_domain",
    "query_lang", "snippet_lang", "mixed_lang", "rescue_flag",
    "label"
]

#df[out_cols].to_csv("data/brave_scored.csv", index=False, encoding="utf-8-sig")

df[out_cols].to_csv("data/yandex_scored.csv", index=False, encoding="utf-8-sig")

# =========================
# 10) Быстрая проверка
# =========================
preview = df[out_cols].head(40)
print(preview.to_string(index=False))

print("\nРаспределение классов:")
print(df["label"].value_counts(dropna=False))

print("\nДоли классов:")
print((df["label"].value_counts(normalize=True, dropna=False) * 100).round(2))