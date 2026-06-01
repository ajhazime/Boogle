import os
import re
import certifi
from bs4 import BeautifulSoup
from dotenv import load_dotenv
from pymongo import MongoClient, UpdateOne

DB_NAME="boogle"
PAGES_COLLECTION="pages"
BACKLINKS_COLLECTION="backlinks"

load_dotenv()

#connects to mongodb and returns boogle database
def get_db():
    uri=os.getenv("MONGO_URI")
    client=MongoClient(uri, tlsCAFile=certifi.where())
    db=client[DB_NAME]
    return db

#fetch pages from MongoDB so indexer can process them
def fetch_pages():
    db = get_db()
    return db[PAGES_COLLECTION].find()
    

def fetch_backlinks():
    db = get_db()
    return db[BACKLINKS_COLLECTION].find()

def extract_text( html: str) -> str:
    str
