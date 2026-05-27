import undetected_chromedriver as uc
from selenium.webdriver.common.by import By
import time
from urllib.parse import quote_plus
import random
import os
from dotenv import load_dotenv
import xml.etree.ElementTree as ET
from yandex_ai_studio_sdk import AIStudio
import pandas as pd
import requests
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC


# Тестовый парсинг ссылок
load_dotenv()
        
    # Инициализация SDK
sdk = AIStudio(
    folder_id=os.getenv("YANDEX_FOLDER_ID"),
    auth=os.getenv("YANDEX_API_KEY"),
)

#Наследие
def parse_urls_google(query: str, starts:list[int] = [0, 10, 20]):
    #Парсинг при помощи Selenium
    
    encoded_query = quote_plus(query)

    urls = []

    for start in starts:
        
        driver = uc.Chrome()
        driver.implicitly_wait(5)
        
        driver.get(f"https://www.google.com/search?q={encoded_query}&start={start}")
        
        time.sleep(random.uniform(3, 7))    
        # Извлечение ссылок — CSS-селектор для результатов Google
        results = driver.find_elements(By.CSS_SELECTOR, "div[jscontroller][data-hveid] a:has(> h3)")
        page_urls = [r.get_attribute("href") for r in results if r.get_attribute("href")]
        urls.extend(page_urls)        
        driver = None
        
    return urls

def extract_total_from_xml(xml_data) -> str:
    if isinstance(xml_data, bytes):
        xml_data = xml_data.decode("utf-8", errors="replace")

    root = ET.fromstring(xml_data)
    el = root.find(".//found[@priority='all']")
    return el.text.strip() if el is not None and el.text else "0"

def _extract_snippet_from_doc(doc_el: ET.Element) -> str:
    """
    Извлекает snippet из <doc>/<passages>.
    Собирает текст всех <passage>, включая вложенные <hlword>.
    """
    passages_el = doc_el.find("passages")
    if passages_el is None:
        return ""

    snippets = []
    for passage_el in passages_el.findall("passage"):
        text = "".join(passage_el.itertext()).strip()
        if text:
            snippets.append(text)

    return " ... ".join(snippets)

def _extract_rows_from_xml(xml_text: str) -> list[dict[str, str]]:
    """
    Извлекает из XML-ответа список словарей:
    [{"url": "...", "snippet": "..."}, ...]
    """
    root = ET.fromstring(xml_text)

    error = root.find(".//error")
    if error is not None:
        raise ValueError(
            f"API вернул ошибку: {error.text} (код: {error.attrib.get('code')})"
        )

    rows = []
    for doc_el in root.findall(".//doc"):
        url_el = doc_el.find("url")
        if url_el is None or not url_el.text:
            continue

        rows.append({
            "url": url_el.text.strip(),
            "snippet": _extract_snippet_from_doc(doc_el)
        })

    return rows


def parse_url_yandex(query: str, pages: int = 3):
    sdk.setup_default_logging()
    
    db_parts = query.split()[2:]
    db = db_parts[0] if len(db_parts) == 1 else " ".join(db_parts)

    all_rows = []

    pages = 3
    
    search = sdk.search_api.web(search_type="RU")

    for page in range(pages):
        search_result = search.run(query,format="xml", page=page)
        if page == 0:
            total = extract_total_from_xml(search_result)
            
        page_rows = _extract_rows_from_xml(search_result)

        for row in page_rows:
            row["db"] = db
            row["query"] = query

        all_rows.extend(page_rows)
    
    df = pd.DataFrame(all_rows, columns=["db", "query", "url", "snippet"])
        
    return int(total), df

def parse_url_brave(query: str, pages: int = 3):
    
    url = "https://api.search.brave.com/res/v1/web/search"
    
    db_parts = query.split()[2:]
    db = db_parts[0] if len(db_parts) == 1 else " ".join(db_parts)

    
    
    headers = {
    "Accept": "application/json",
    "Accept-Encoding": "gzip",
    "X-Subscription-Token": os.getenv("BRAVE_API_KEY")
    }
    
    rows = []
    for page in range(pages):
        params = {
            "q": query,
            "count": 20,
            "offset": page
        }
        response = requests.get(url, headers=headers, params=params)
        response.raise_for_status()
        print(f"Brave API - Page {page+1} - Status: {response.status_code}")
        data = response.json()
        
        results = data.get("web", {}).get("results", {})
        
        for item in results:
            rows.append({
                "db": db,
                "query": query,
                "url": item.get("url", ""),
                "snippet": item.get("description", "")
            })
    return len(rows), pd.DataFrame(rows, columns=["db", "query", "url", "snippet"])
