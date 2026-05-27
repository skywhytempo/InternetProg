import pandas as pd
import pprint


yandex_df = pd.read_csv("bdz/yandex_url.csv")
brave_df = pd.read_csv("bdz/brave_url.csv")

intersection_df = pd.merge(yandex_df, brave_df, on=["url"], how="inner")