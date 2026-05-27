import pandas as pd

def analyze_results(source: str):
    df = pd.read_csv(f"{source}_scored.csv")
    
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

''''df_brave = pd.read_csv("brave_scored.csv")

spam_df_brave = df_brave.iloc[df_brave["label"] == "spam"]

useful_df_brave = df_brave.iloc[df_brave["label"] == "useful"]

spam_part_brave = len(spam_df_brave) / len(df_brave)
useful_part_brave = len(useful_df_brave) / len(df_brave)

print("=================================================")

print("BRAVE RESULTS:")

print(f"Всего URL: {len(df_brave)}")
print(f"Спам URL: {len(spam_df_brave)}")
print(f"Доля спама: {spam_part_brave:.2%}")
print(f"Полезные URL: {len(useful_df_brave)}")
print(f"Доля полезных URL: {useful_part_brave:.2%}")

df_yandex = pd.read_csv("yandex_scored.csv")

spam_df_yandex = df_yandex.iloc[df_yandex["label"] == "spam"]

useful_df_yandex = df_yandex.iloc[df_yandex["label"] == "useful"]

spam_part_yandex = len(spam_df_yandex) / len(df_yandex)
useful_part_yandex = len(useful_df_yandex) / len(df_yandex)

print("=================================================")

print("YANDEX RESULTS:")
print(f"Всего URL: {len(df_yandex)}")
print(f"Спам URL: {len(spam_df_yandex)}")
print(f"Доля спама: {spam_part_yandex:.2%}")
print(f"Полезные URL: {len(useful_df_yandex)}")
print(f"Доля полезных URL: {useful_part_yandex:.2%}")'''

analyze_results("brave")
analyze_results("yandex")

print("=================================================")

