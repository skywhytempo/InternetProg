from yandex_ai_studio_sdk import AIStudio
from dotenv import load_dotenv
import os
from typing import Literal, cast
import xml.etree.ElementTree as ET

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

def parse_url_yandex(query: str):
    load_dotenv()
        
    # Инициализация SDK
    sdk = AIStudio(
        folder_id=os.getenv("YANDEX_FOLDER_ID"),
        auth=os.getenv("YANDEX_API_KEY"),
    )

    sdk.setup_default_logging()

    urls = []

    pages = 3
    
    search = sdk.search_api.web(search_type="RU")

    for page in range(pages):
        
        search_result = search.run(query,format="xml", page=page)
        page_urls = _extract_urls_from_xml(search_result)
        urls.extend(page_urls)
        
    return urls






'''options = uc.ChromeOptions()
options.add_argument("--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36")
options.add_argument("--lang=ru-RU,ru;q=0.9,en;q=0.8")
options.add_argument("--window-size=1366,768")

driver = uc.Chrome(options=options)
driver.implicitly_wait(5)'''

