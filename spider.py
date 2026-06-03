import time
import os
import requests
import certifi
import redis

from bs4 import BeautifulSoup
from urllib.parse import urljoin, urlparse
from dotenv import load_dotenv
from pymongo import MongoClient, UpdateOne

load_dotenv()


"""
GLOBAL VARIABLES
"""
SEED_URL="https://books.toscrape.com"
MAX_PAGES=50
DELAY=0.1

#
#END OF GLOBAL VAR
#


#connect to redis
r = redis.Redis(host='localhost', port=6379)

#connects to mongodb and returns boogle database
def get_db():
    uri=os.getenv("MONGO_URI")
    client=MongoClient(uri, tlsCAFile=certifi.where())
    db=client["boogle"]
    return db


#saves crawl data to mongodb pages and backlinks collections
def save_to_mongo(data: dict) -> None:
    db=get_db()

    outlinks=data["outlinks"]
    backlinks=data["backlinks"]
    images=data["images"]

    #one document per crawled url containing its outlinks and images
    pages_ops=[]
    for url, links in outlinks.items():
        pages_ops.append(UpdateOne(
            {"url": url},
            {"$set": {
                "url": url,
                "html": data["html"].get(url, ""),
                "outlinks": links,
                "images":   images.get(url, []),
            }},
            upsert=True
        ))

    if pages_ops:
        db["pages"].bulk_write(pages_ops)
        print(f"  Saved {len(pages_ops)} pages to MongoDB")

    #one document per url tracking which pages link to it
    backlinks_ops=[]
    for url, sources in backlinks.items():
        backlinks_ops.append(UpdateOne(
            {"url": url},
            {"$addToSet": {"sources": {"$each": sources}}},
            upsert=True
        ))

    if backlinks_ops:
        db["backlinks"].bulk_write(backlinks_ops)
        print(f"  Saved {len(backlinks_ops)} backlink entries to MongoDB")


#gets url's domain
def get_domain(url: str) -> str:
    return urlparse(url).netloc


#strips fragments so #section links dont create duplicate entries
def normalize(url: str) -> str:
    return urlparse(url)._replace(fragment="").geturl()


def crawl(seed: str, max_pages: int=MAX_PAGES) -> dict:
    """
    Priority-queue BFS crawl starting from seed.

    URLs seen more often across pages get crawled first.

    Returns a dict with three stores:
        outlinks: { url: [urls this page links to] }
        html_store: {url, [text from url]}
        backlinks: { url: [urls that link TO this page] }
        images: { url: [image src urls found on this page] }
    """
    seed_domain=get_domain(seed)
    seed=normalize(seed)

    #Clear old redis data and push seed url 
    # SHOULD BE REMOVED IN PRODUCTION 
    r.delete('crawl_queue')
    r.delete('visted')
    r.zadd('crawl_queue', {seed: -1})

    # pqueue removed in commit 13
    
    outlinks: dict[str, list[str]]={}
    backlinks: dict[str, list[str]]={}
    images: dict[str, list[str]]={}
    html_store: dict[str, str] = {}

    #updated to use redis (commit 13)
    crawled = 0
    while r.zcard('crawl_queue') > 0 and crawled < max_pages:
        #pop highest result priority from redis queue
        result = r.zpopmin('crawl_queue', 1)
        if not result:
            break
        current_url = result[0][0].decode('utf-8')
        current_url=normalize(current_url)

        #skip if visited
        if r.sismember('visited', current_url):
            continue

        
        print(f"[{crawled+1}/{max_pages}] {current_url}")
        try:
            response=requests.get(current_url, timeout=5)
            response.raise_for_status()
        except Exception as e:
            print(f"  Failed: {e}")
            r.sadd('visited', current_url)
            continue

        r.sadd('visited', current_url)
        crawled += 1
        html_store[current_url] = response.text # save html text
        soup=BeautifulSoup(response.text, "lxml")

        #collecting outlinks
        page_outlinks: list[str]=[]

        for tag in soup.find_all("a", href=True):
            #cast to string for .strip
            href=str(tag["href"]).strip()
            absolute=normalize(urljoin(current_url, href))

            #stay on the same domain
            if get_domain(absolute) != seed_domain:
                continue
            if not absolute.startswith("http"):
                continue

            #deduplicate within this page's outlinks
            if absolute not in page_outlinks:
                page_outlinks.append(absolute)

            #updating backlinks
            if absolute not in backlinks:
                backlinks[absolute]=[]
            if current_url not in backlinks[absolute]:
                backlinks[absolute].append(current_url)

            #update priority and enqueue in redis
            #update priority and enqueue in redis
            score=r.zscore('crawl_queue', absolute)
            new_score=(score - 1) if score else -1
            if not r.sismember('visited', absolute):
                r.zadd('crawl_queue', {absolute: new_score})

        outlinks[current_url]=page_outlinks

        #image collection
        page_images: list[str]=[]
        for img in soup.find_all("img", src=True):
            #cast to str for strip
            src=str(img["src"]).strip()
            absolute_src=urljoin(current_url, src)
            page_images.append(absolute_src)

        images[current_url]=page_images

        time.sleep(DELAY)

    return {"outlinks": outlinks, "backlinks": backlinks, "images": images, "html": html_store}


def print_summary(data: dict) -> None:
    outlinks=data["outlinks"]
    backlinks=data["backlinks"]
    images=data["images"]

    print("\n" + "="*60)
    print(f"Pages crawled : {len(outlinks)}")
    print(f"Unique backlinks tracked: {len(backlinks)}")
    print(f"Images found : {sum(len(v) for v in images.values())}")
    print("="*60)

    print("\n── Top 5 most linked-to pages ──")
    sorted_bl=sorted(backlinks.items(), key=lambda x: len(x[1]), reverse=True)
    for url, sources in sorted_bl[:5]:
        print(f"  <-- {len(sources):3}  {url}")

    print("\n── Sample outlinks (first 3 pages) ──")
    for url, links in list(outlinks.items())[:3]:
        print(f"  {url}")
        for link in links[:3]:
            print(f" --> {link}")


if __name__ == "__main__":
    print(f"Starting priority-queue BFS crawl from: {SEED_URL}\n")
    data=crawl(SEED_URL)
    print_summary(data)

    print("\nSaving to MongoDB...")
    save_to_mongo(data)
    print("Done.")