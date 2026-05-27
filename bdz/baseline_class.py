import re
import pandas as pd
import torch
from sentence_transformers import CrossEncoder


class SearchResultBaseline:
    def __init__(
        self,
        model_name="cross-encoder/ms-marco-MiniLM-L6-v2",
        device="cuda"
    ):
        self.model = CrossEncoder(
            model_name,
            max_length=512,
            device=device,
            activation_fn=torch.nn.Sigmoid()
        )

        self.POSITIVE_TERMS = [
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

        self.NEGATIVE_TERMS = [
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

        self.POSITIVE_URL_PATTERNS = [
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

        self.NEGATIVE_URL_PATTERNS = [
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

        self.GOOD_DOMAINS = [
            "oracle.com",
            "docs.oracle.com",
            "cloud.oracle.com",
            "oci.oraclecloud.com",
            "enterprisedb.com",
            "postgresql.org",
            "percona.com",
            "cybertec-postgresql.com"
        ]

        self.BAD_DOMAINS = [
            "youtube.com",
            "youtu.be",
            "pinterest.com",
            "coursera.org",
            "classcentral.com"
        ]

    def _clean_text(self, x):
        x = "" if pd.isna(x) else str(x)
        x = x.lower()
        x = re.sub(r"<[^>]+>", " ", x)
        x = re.sub(r"\s+", " ", x).strip()
        return x

    def _detect_lang_simple(self, text):
        text = self._clean_text(text)
        cyr = len(re.findall(r"[а-яё]", text))
        lat = len(re.findall(r"[a-z]", text))
        if cyr > lat * 1.3 and cyr >= 3:
            return "ru"
        if lat > cyr * 1.3 and lat >= 3:
            return "en"
        return "mixed"

    def _count_terms(self, text, terms):
        return sum(1 for t in terms if t in text)

    def _count_url_patterns(self, url, patterns):
        url = self._clean_text(url)
        return sum(1 for p in patterns if p in url)

    def _is_good_domain(self, url):
        url = self._clean_text(url)
        return any(d in url for d in self.GOOD_DOMAINS)

    def _is_bad_domain(self, url):
        url = self._clean_text(url)
        return any(d in url for d in self.BAD_DOMAINS)

    def _compute_rule_features(self, row):
        url = self._clean_text(row["url"])
        snippet = self._clean_text(row["snippet"])
        query = self._clean_text(row["query"])
        db = self._clean_text(row["db"])

        full_text = f"{db} {query} {url} {snippet}"

        pos_hits = self._count_terms(full_text, self.POSITIVE_TERMS)
        neg_hits = self._count_terms(full_text, self.NEGATIVE_TERMS)

        pos_url_hits = self._count_url_patterns(url, self.POSITIVE_URL_PATTERNS)
        neg_url_hits = self._count_url_patterns(url, self.NEGATIVE_URL_PATTERNS)

        good_domain = self._is_good_domain(url)
        bad_domain = self._is_bad_domain(url)

        query_lang = self._detect_lang_simple(query)
        snippet_lang = self._detect_lang_simple(snippet)
        mixed_lang = query_lang != snippet_lang and "mixed" not in (query_lang, snippet_lang)

        score = 0.0

        score += pos_hits * 0.16
        score -= neg_hits * 0.28
        score += pos_url_hits * 0.22
        score -= neg_url_hits * 0.30

        if good_domain:
            score += 0.10
        if bad_domain:
            score -= 0.15

        if good_domain and (pos_url_hits >= 1 or pos_hits >= 2):
            score += 0.18

        rescue_flag = False
        if mixed_lang and good_domain and (pos_url_hits >= 1 or pos_hits >= 2) and neg_hits == 0 and neg_url_hits == 0:
            score += 0.28
            rescue_flag = True

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
            "rescue_flag": rescue_flag,
        }

    def _assign_label(self, row):
        fs = row["final_score"]
        neg = row["neg_hits"]
        pos = row["pos_hits"]
        pos_url = row["pos_url_hits"]
        neg_url = row["neg_url_hits"]
        rescue = row["rescue_flag"]
        good_domain = row["good_domain"]

        if (neg + neg_url) >= 2 and (pos + pos_url) == 0:
            return "spam"

        if neg >= 1 and neg_url >= 1 and fs < 0.70:
            return "spam"

        if rescue and good_domain and (pos + pos_url) >= 2 and fs >= 0.52:
            return "useful"

        if fs >= 0.68:
            return "useful"
        elif fs >= 0.44:
            return "unknown"
        else:
            return "spam"

    def classify_search_results(self, results: pd.DataFrame) -> pd.DataFrame:
        """
        results: DataFrame with columns "db", "query", "url", "snippet"

        """
        df = results.copy()

        required = ["db", "query", "url", "snippet"]
        missing = [c for c in required if c not in df.columns]
        if missing:
            raise ValueError(f"Не хватает полей: {missing}")

        pairs = []
        for _, row in df.iterrows():
            query_text = f"{row['db']} | {row['query']}"
            doc_text = f"{row['url']} | {row['snippet']}"
            pairs.append((query_text, doc_text))

        model_scores = self.model.predict(pairs, batch_size=32, show_progress_bar=False)
        model_scores = [float(max(0.0, min(1.0, s))) for s in model_scores]
        df["model_score"] = model_scores

        features = df.apply(self._compute_rule_features, axis=1, result_type="expand")
        df = pd.concat([df, features], axis=1)

        df["rule_score_01"] = (df["rule_score"] + 1.0) / 2.0
        df["final_score"] = 0.45 * df["model_score"] + 0.55 * df["rule_score_01"]
        df["label"] = df.apply(self._assign_label, axis=1)

        return df.sort_values("final_score", ascending=False).reset_index(drop=True)