
**A full-stack search engine built from scratch, live at boogle.app.**  



Architecture  



Spider (Go) → Redis Queue → MongoDB Atlas → Indexer (Python) → Query Engine (Laravel/PHP) <br>
<br>
                                      ↓ <br>
                               PageRank (Go) <br>
                                      ↓ <br>
                           AWS ECS + Nginx + ALB <br>

                         
<br><br>**Components**  


Spider — Go
BFS web crawler with a Redis-backed priority queue. Pages linked to by many other pages are crawled first. Deduplication via Redis Sets prevents multiple spiders from crawling the same URL.

Priority queue using Redis Sorted Sets
User-Agent header for polite crawling
Stores outlinks, images, and raw HTML to MongoDB
Supports parallel crawling — run multiple instances simultaneously

Indexer — Python
Processes raw HTML into a TF-IDF inverted index.

Strips HTML, lowercases, removes stopwords and symbols
Computes TF-IDF scores per word per page
Stores inverted index: word → {url: score} in MongoDB

PageRank — Go
Iterative PageRank algorithm over the crawled link graph.

50 iterations with 0.85 damping factor
Reads outlinks from MongoDB pages collection
Writes PageRank scores back to MongoDB

Query Engine — Laravel (PHP)
REST API that accepts search queries and returns ranked results.

Splits query into words
Looks up each word in the inverted index
Combines TF-IDF scores across query words
Multiplies by PageRank score for final ranking
Returns results sorted by cumulative score

**Infrastructure**

MongoDB Atlas — persistent storage for pages, index, and PageRank scores <br>
Redis — in-memory URL queue and visited set for distributed crawling <br>
Docker — containerized query engine  <br>
AWS ECS Fargate — serverless container hosting (2 instances) <br>
AWS ALB — load balancer distributing traffic across instances <br>
AWS Route 53 — DNS management for boogle.app <br>
AWS ACM — free SSL/TLS certificate <br>
Nginx — local load balancer for development <br>
