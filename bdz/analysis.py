import pandas as pd

def analyze_results(df: pd.DataFrame, source: str):
    total_urls = len(df)
    spam_df = df[df["label"] == "spam"]
    useful_df = df[df["label"] == "useful"]
    
    spam_percentage = (len(spam_df) / total_urls)
    useful_percentage = (len(useful_df) / total_urls)
    
    print("=================================================")

    print(f"{source.upper()} RESULTS:")

    print(f"Всего URL: {len(df)}")
    print(f"Спам URL: {len(spam_df)}")
    print(f"Доля спама: {spam_percentage:.2%}")
    print(f"Полезные URL: {len(useful_df)}")
    print(f"Доля полезных URL: {useful_percentage:.2%}")
    
    print("=================================================")
