import torch
import pandas as pd
from sentence_transformers import CrossEncoder

df = pd.read_csv("bdz/brave_url.csv")

print("COLUMNS:", df.columns.tolist())
print("ROWS:", len(df))
print(df.head(3).to_dict(orient="records"))

model = CrossEncoder(
    "cross-encoder/ms-marco-MiniLM-L6-v2",
    max_length=512,
    device="cuda",
    activation_fn=torch.nn.Sigmoid()
)

sample = df.head(20).copy()

# 5. Формируем пары (query, document)
pairs = []
for _, row in sample.iterrows():
    query_text = f"{row['db']} {row['query']}"
    doc_text = f"{row['url']} {row['snippet']}"
    pairs.append((query_text, doc_text))

# 6. Считаем score
scores = model.predict(pairs)

# 7. Сохраняем score и печатаем удобно
sample["score"] = scores

for _, row in sample.iterrows():
    print("=" * 120)
    print("DB    :", row["db"])
    print("QUERY :", row["query"])
    print("URL   :", row["url"])
    print("SCORE :", float(row["score"]))
    print("SNIPPET:", str(row["snippet"])[:300])