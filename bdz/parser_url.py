import undetected_chromedriver as uc
from selenium.webdriver.common.by import By
import time
from urllib.parse import quote_plus
import random
import os
from dotenv import load_dotenv
import xml.etree.ElementTree as ET
from yandex_ai_studio_sdk import AIStudio


# Тестовый парсинг ссылок
load_dotenv()
        
    # Инициализация SDK
sdk = AIStudio(
    folder_id=os.getenv("YANDEX_FOLDER_ID"),
    auth=os.getenv("YANDEX_API_KEY"),
)

def parse_urls_google(query: str):
    #Парсинг при помощи Selenium
    
    encoded_query = quote_plus(query)

    starts = [0, 10, 20]

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
        driver.quit()
        driver = None
        
    return urls

def _extract_urls_from_xml(xml_text: str) -> list[str]:
    """
    Извлекает URL из XML-ответа Yandex Search API.
    Структура: <response> -> <results> -> <grouping> -> <group> -> <doc> -> <url>
    """
    root = ET.fromstring(xml_text)

    # Проверяем на ошибку в ответе
    error = root.find(".//error")
    if error is not None:
        raise ValueError(f"API вернул ошибку: {error.text} (код: {error.attrib.get('code')})")

    # Извлекаем все теги <url> из документов
    urls = [url_el.text for url_el in root.findall(".//doc/url") if url_el.text]
    return urls

def parse_url_yandex(query: str, sdk):
    

    sdk.setup_default_logging()

    urls = []

    pages = 3
    
    search = sdk.search_api.web(search_type="RU")

    for page in range(pages):
        
        search_result = search.run(query,format="xml", page=page)
        page_urls = _extract_urls_from_xml(search_result)
        urls.extend(page_urls)
        
    return urls


