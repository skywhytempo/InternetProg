from parser_url import parse_url_brave, parse_url_yandex
from baseline_class import SearchResultBaseline
from analysis import analyze_results


def test_pipeline(source: str, parse_func, query: str):
    
    total, df = parse_func(query)
    
    print(f"Total results for '{query}' from {source}: {total}")
    
    pipeline = SearchResultBaseline(device="cuda")
    
    results_df = pipeline.classify_search_results(df)
    
    analyze_results(results_df, source)

query = "Big data Postgress"

if __name__ == "__main__":
    test_pipeline("Yandex", parse_url_yandex, query)
    test_pipeline("Brave", parse_url_brave, query)


