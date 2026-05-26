from yandex_ai_studio_sdk import AsyncAIStudio
import os
from dotenv import load_dotenv
import xml.etree.ElementTree as ET


async_sdk = AsyncAIStudio(
    folder_id=os.getenv("YANDEX_FOLDER_ID"),
    auth=os.getenv("YANDEX_API_KEY"),
)

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


def extract_total_from_xml(xml_data) -> str:
    if isinstance(xml_data, bytes):
        xml_data = xml_data.decode("utf-8", errors="replace")

    root = ET.fromstring(xml_data)
    el = root.find(".//found[@priority='all']")
    return el.text.strip() if el is not None and el.text else "0"



async def async_yandex_urls(search_query:str, pages: int = 3):
    
    async_sdk.setup_default_logging()

    search = async_sdk.search_api.web('RU')
    
    operation = search.run_deferred(search_query, format="xml", page=0)

    search_result = await search.run(search_query, format="xml", page=0)
    assert isinstance(search_result, bytes)
    
    urls = _extract_urls_from_xml(search_result)
    
    total = extract_total_from_xml(search_result)
    
    for page in range(1, pages):
        
        search_result = await search.run(search_query, format="xml", page=page)
        
        page_urls = _extract_urls_from_xml(search_result)
        urls.extend(page_urls)
    
    return total, urls