import undetected_chromedriver as uc
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
import time
import requests
from bs4 import BeautifulSoup


# Тестовый парсинг ссылок
driver = uc.Chrome()
query = "Большие данные MySQL"
driver.get(f"https://www.google.com/search?q={query}&num=30")
time.sleep(2)

# Извлечение ссылок — CSS-селектор для результатов Google
results = driver.find_elements(By.CSS_SELECTOR, "div[jscontroller][data-hveid] a:has(> h3)")
urls = [r.get_attribute("href") for r in results if r.get_attribute("href")]

print("Найденные URL:")
print("\n".join(urls))

driver.quit()

options = Options()
options.add_argument("--headless=new")
driver = webdriver.Chrome(options=options)


for url in urls:
    driver.get(url)
    html = driver.page_source
    print(html[:1000])
    
