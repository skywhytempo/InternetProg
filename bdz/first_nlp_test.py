from sentence_transformers import CrossEncoder

model = CrossEncoder(
    "DiTy/cross-encoder-russian-msmarco",
    max_length=512,
    device="cuda"
)

#Первоначальные тесты

pairs = [
    [
        "MySQL Big Data",
        "https://dba.stackexchange.com/questions/114539/mysql-big-data-solutions It depends on your table structures, data layout and usage pattern. However I wouldn't consider 12 gb ... I work with MySQL servers happily running with terabytes of data."
    ],
    [
        "MySQL Big Data",
        "https://www.coursera.org/learn/analytics-mysql Offered by Duke University. This course is an introduction to how to use relational databases in business analysis."
    ],
    [
        "Berkeley DB Big Data",
        "https://debrabernier.com/navigating-the-depths-oracle-berkeley-db-and-big-data-management Oracle Berkeley DB ... robust solution for handling large volumes of data in big data management."
    ],
    [
        "Berkeley DB Big Data",
        "https://bd.studentorg.berkeley.edu Big Data at Berkeley is a UC Berkeley student organization dedicated to promoting data science..."
    ],
    [
        "PostgreSQL Big Data",
        "https://www.enterprisedb.com/blog/powering-big-data-processing-postgres-apache-spark FDWs act as pipelines connecting Postgres with external database solutions including Hadoop."
    ]
]

scores = model.predict(pairs)

for pair, score in zip(pairs, scores):
    print("=" * 80)
    print("QUERY:", pair[0])
    print("TEXT :", pair[1][:200], "...")
    print("SCORE:", float(score))
    


#Более структурированные тесты

tests = [
    {
        "name": "mysql_good",
        "query_a": "MySQL Big Data",
        "doc_a": "https://dba.stackexchange.com/questions/114539/mysql-big-data-solutions It depends on your table structures, data layout and usage pattern. However I wouldn't consider 12 gb or actually anything that can fit into RAM big data. I work with MySQL servers happily running with terabytes of data.",
        "query_b": "database: MySQL; topic: Big Data",
        "doc_b": "domain: dba.stackexchange.com; url: https://dba.stackexchange.com/questions/114539/mysql-big-data-solutions; snippet: It depends on your table structures, data layout and usage pattern. I work with MySQL servers happily running with terabytes of data."
    },
    {
        "name": "mysql_course",
        "query_a": "MySQL Big Data",
        "doc_a": "https://www.coursera.org/learn/analytics-mysql Offered by Duke University. This course is an introduction to how to use relational databases in business analysis.",
        "query_b": "database: MySQL; topic: Big Data",
        "doc_b": "domain: coursera.org; url: https://www.coursera.org/learn/analytics-mysql; snippet: Course introduction to relational databases in business analysis."
    },
    {
        "name": "berkeley_good",
        "query_a": "Berkeley DB Big Data",
        "doc_a": "https://debrabernier.com/navigating-the-depths-oracle-berkeley-db-and-big-data-management Oracle Berkeley DB robust solution for handling large volumes of data in big data management.",
        "query_b": "database: Berkeley DB; topic: Big Data",
        "doc_b": "domain: debrabernier.com; url: https://debrabernier.com/navigating-the-depths-oracle-berkeley-db-and-big-data-management; snippet: Oracle Berkeley DB is presented as a solution for handling large volumes of data in big data management."
    },
    {
        "name": "berkeley_false_match",
        "query_a": "Berkeley DB Big Data",
        "doc_a": "https://bd.studentorg.berkeley.edu Big Data at Berkeley is a UC Berkeley student organization dedicated to promoting data science.",
        "query_b": "database: Berkeley DB; topic: Big Data",
        "doc_b": "domain: bd.studentorg.berkeley.edu; url: https://bd.studentorg.berkeley.edu; snippet: Big Data at Berkeley is a UC Berkeley student organization dedicated to promoting data science."
    },
    {
        "name": "postgres_good",
        "query_a": "PostgreSQL Big Data",
        "doc_a": "https://www.enterprisedb.com/blog/powering-big-data-processing-postgres-apache-spark FDWs act as pipelines connecting Postgres with external database solutions including Hadoop.",
        "query_b": "database: PostgreSQL; topic: Big Data",
        "doc_b": "domain: enterprisedb.com; url: https://www.enterprisedb.com/blog/powering-big-data-processing-postgres-apache-spark; snippet: FDWs connect Postgres with external systems including Hadoop for big data processing."
    }
]

pairs_a = [(t["query_a"], t["doc_a"]) for t in tests]
pairs_b = [(t["query_b"], t["doc_b"]) for t in tests]

scores_a = model.predict(pairs_a)
scores_b = model.predict(pairs_b)

for t, sa, sb in zip(tests, scores_a, scores_b):
    print("=" * 100)
    print("CASE:", t["name"])
    print("A_SCORE:", float(sa))
    print("B_SCORE:", float(sb))
    print("DELTA  :", float(sb - sa))
    
    
    
#Тесты с альтернативной моделью

tests = [
    {
        "name": "mysql_good",
        "query": "MySQL Big Data",
        "doc": "https://dba.stackexchange.com/questions/114539/mysql-big-data-solutions It depends on your table structures, data layout and usage pattern. However I wouldn't consider 12 gb or actually anything that can fit into RAM big data. I work with MySQL servers happily running with terabytes of data."
    },
    {
        "name": "mysql_course",
        "query": "MySQL Big Data",
        "doc": "https://www.coursera.org/learn/analytics-mysql Offered by Duke University. This course is an introduction to how to use relational databases in business analysis."
    },
    {
        "name": "berkeley_good",
        "query": "Berkeley DB Big Data",
        "doc": "https://debrabernier.com/navigating-the-depths-oracle-berkeley-db-and-big-data-management Oracle Berkeley DB robust solution for handling large volumes of data in big data management."
    },
    {
        "name": "berkeley_false_match",
        "query": "Berkeley DB Big Data",
        "doc": "https://bd.studentorg.berkeley.edu Big Data at Berkeley is a UC Berkeley student organization dedicated to promoting data science."
    },
    {
        "name": "postgres_good",
        "query": "PostgreSQL Big Data",
        "doc": "https://www.enterprisedb.com/blog/powering-big-data-processing-postgres-apache-spark FDWs act as pipelines connecting Postgres with external database solutions including Hadoop."
    }
]

pairs = [(t["query"], t["doc"]) for t in tests]

models = {
    "dity_ru": "DiTy/cross-encoder-russian-msmarco",
    "minilm_en": "cross-encoder/ms-marco-MiniLM-L6-v2"
}

all_scores = {}

for model_name, model_path in models.items():
    print(f"\nLoading: {model_name} -> {model_path}")
    model = CrossEncoder(model_path, max_length=512, device="cuda")
    scores = model.predict(pairs)
    all_scores[model_name] = scores

print("\n" + "=" * 120)
print(f"{'CASE':<24} {'DiTy_RU':>12} {'MiniLM_EN':>12} {'DELTA(EN-RU)':>14}")
print("=" * 120)

for i, t in enumerate(tests):
    ru = float(all_scores["dity_ru"][i])
    en = float(all_scores["minilm_en"][i])
    print(f"{t['name']:<24} {ru:>12.6f} {en:>12.6f} {en - ru:>14.6f}")