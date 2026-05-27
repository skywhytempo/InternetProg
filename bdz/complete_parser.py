from parser_url import parse_url_brave, parse_url_yandex
import pandas as pd
from collections import defaultdict
import time
import csv


with open("data/queries.txt", "r", encoding="utf-8") as file:
    queries = file.read().strip().split("\n")


def parse_yandex():
    total_data = {}

    df_url = pd.DataFrame(columns=["db", "query", "url", "snippet"])

    for query in queries:
        
        total, df = parse_url_yandex(query)
        df_url = pd.concat([df_url, df], ignore_index=True)

        print(f"{query} - {total} results")
        
        db = query.split(" ")[2:]
        
        db = db[0] if len(db) == 1 else ' '.join(db)
        
        if db in total_data:
            total_data[db] += total
        else:
            total_data[db] = total

    df_url.drop_duplicates(subset=["url"], keep='first', inplace=True)
    df_total = pd.DataFrame(total_data.items(), columns=['db', 'total'])
    df_total.to_csv("data/yandex_total.csv", index=False)
    df_url.to_csv("data/yandex_url.csv", index=False)


def parse_brave():
    df_url = pd.DataFrame(columns=["db", "query", "url", "snippet"])

    for query in queries:
        
        total, df = parse_url_brave(query)
        df_url = pd.concat([df_url, df], ignore_index=True)

        print(f"{query} - parsed")
        
        db = query.split(" ")[2:]
        
        db = db[0] if len(db) == 1 else ' '.join(db)

    df_url.drop_duplicates(subset=["url"], keep='first', inplace=True)
    df_url.to_csv("data/brave_url.csv", index=False)


if __name__ == "__main__":
    #parse_yandex()
    parse_brave()




