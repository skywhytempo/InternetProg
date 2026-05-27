import pandas as pd
import pprint


yandex_df = pd.read_csv("data/yandex_url.csv")
brave_df = pd.read_csv("data/brave_url.csv")

intersection_df = pd.merge(yandex_df, brave_df, on=["url"], how="inner")