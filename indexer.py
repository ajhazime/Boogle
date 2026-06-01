import os
import re
import certifi
import math
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

#fetch pages from MongoDB 
def fetch_pages():
    db = get_db()
    return db[PAGES_COLLECTION].find()
    
#fetch backlinks from MongoDB
def fetch_backlinks():
    db = get_db()
    return db[BACKLINKS_COLLECTION].find()

#extract HTML text
def extract_text( html: str) -> str:
    soup=BeautifulSoup(html, "lxml")
    return soup.get_text()

#cleans text by lowercasing, removing symbols, and stopwords
def clean_text(text: str) -> list[str]:
    text = text.lower()
    text = re.sub(r"[^a-z\s]", "", text) # remove anything that isnt a-z or whitespace 
    wordList = text.split()
    stopwords = ["the", "a", "is", "and", "in", "of", "to", "it", "or", "an", "on", "at", "for", "with", "as", "by"]
    return [word for word in wordList if word not in stopwords] #return wordlist \ stopwords

def build_word_frequency(words: list[str]) -> dict[str, int]:
    freq = {}
    for word in words:
        freq[word] = freq.get(word, 0) + 1
    return freq

#compute TF-IDF score for each word on the page
def compute_tfidf(freq: dict[str, int], all_freqs: list[dict[str,int]]) -> dict[str, float]:
    #total # of words on page
    totalWords = sum(freq.values())
    #total # of docs
    totalDocuments = len(all_freqs)
    results = {} 
    for word in freq:
        TF = freq[word] / totalWords
        docsWithWord = sum(1 for doc in all_freqs if word in doc)
        IDF = math.log(totalDocuments / docsWithWord)
        results[word] = float(TF * IDF)
    return results

#saves inverted index to MongoDB 
def save_index(index: dict[str, dict[str, float]]) -> None:
    db = get_db()
    for word in index: #loop through each word and its url score
        db["index"].update_one(
            {"word": word}, # find document where word matches
            {"$set": {  # set these fields
            "word": word,
            "urls": index[word] # the url -> score dictionary
            }},
        upsert=True # create if doesn't exist
        )
    print(f"{len(index)} inserted into DB")


if __name__ == "__main__":
    pages = list(fetch_pages())

    all_freqs =[]
    urls = []

    for page in pages:
        text = extract_text(page["html"]) #extract text
        cleanText = clean_text(text) #clean text
        wordFrequency = build_word_frequency(cleanText)
        all_freqs.append(wordFrequency)
        urls.append(page["url"])


    index = {}
    for i, freq in enumerate(all_freqs):
        tfidf = compute_tfidf(freq, all_freqs)
        for word, score in tfidf.items():
            if word not in index:
                index[word] = {}
            index[word][urls[i]] = score
    
    save_index(index)
            
