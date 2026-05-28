from parser_url import parse_url_brave, parse_url_yandex
from baseline_class import SearchResultBaseline
from analysis import analyze_results
import pprint


def test_pipeline(source: str, parse_func, query: str):
    
    total, df = parse_func(query)
    
    print(f"Total results for '{query}' from {source}: {total}")
    
    pprint.pprint(df)
    
    pipeline = SearchResultBaseline(device="cuda")
    
    results_df = pipeline.classify_search_results(df)
    
    analyze_results(results_df, source)

query = "Big data PostgreSQL"

if __name__ == "__main__":
    test_pipeline("Yandex", parse_url_yandex, query)
    test_pipeline("Brave", parse_url_brave, query)


