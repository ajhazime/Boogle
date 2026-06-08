package main

import (
	"context"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"os"
	"strings"
	"time"

	"github.com/redis/go-redis/v9"
	"go.mongodb.org/mongo-driver/v2/bson"
	"go.mongodb.org/mongo-driver/v2/mongo"
	"go.mongodb.org/mongo-driver/v2/mongo/options"
	"golang.org/x/net/html"
)

const (
	SEED_URL  = "https://en.wikipedia.org/wiki/Main_Page"
	MAX_PAGES = 500
	DELAY = 500 * time.Millisecond
)

type Page struct {
	URL string
	Outlinks []string
	Images []string
	HTML string
}

func main() {
	/* Redis Connection */
	rdb := redis.NewClient(&redis.Options{Addr: "localhost:6379"})

	/* mongodb connection */
	uri := os.Getenv("MONGO_URI")
	mongoClient, err := mongo.Connect(options.Client().ApplyURI(uri))
	if err != nil {
		panic(err)
	}
	defer mongoClient.Disconnect(context.Background()) //disconnect when main exits
	db := mongoClient.Database("boogle")

	/* Start crawl*/
	fmt.Println("Starting crawl from:", SEED_URL)
	crawl(SEED_URL, db, rdb)
	fmt.Println("Crawl complete.")
}

/*Crawl function*/
func crawl(seed string, db *mongo.Database, rdb *redis.Client) {

	ctx := context.Background()
	//clear old redis data and push seed URL
	rdb.Del(ctx, "crawl_queue", "visited")
	rdb.ZAdd(ctx, "crawl_queue", redis.Z{Score: -1, Member: seed})

	//crawl loop
	crawled := 0
	for crawled < MAX_PAGES {
		//pop highest priority url from queue
		results, err := rdb.ZPopMin(ctx, "crawl_queue", 1).Result()
		if err != nil || len(results) == 0 {
			break
		}

		currentURL := results[0].Member.(string)

		//skip if visited
		visited, _ := rdb.SIsMember(ctx, "visited", currentURL).Result()
		if visited {
			continue
		}

		fmt.Printf("[%d/%d] %s\n", crawled+1, MAX_PAGES, currentURL)

		//fetch page
		req, err := http.NewRequest("GET", currentURL, nil)
		if err != nil {
			rdb.SAdd(ctx, "visited", currentURL)
			continue
		}
		req.Header.Set("User-Agent", "Booglebot/1.0 (https://boogle.app; educational search engine)") //User-Agent header
		client := &http.Client{}
		response, err := client.Do(req)

		//read body into string so we can use it twice
		bodyBytes, err := io.ReadAll(response.Body)
		response.Body.Close()
		if err != nil {
			continue
		}
		bodyString := string(bodyBytes)

		//mark as visited and increment crawled
		rdb.SAdd(ctx, "visited", currentURL)
		crawled++

		//parse HTML
		doc, err := html.Parse(strings.NewReader(bodyString))
		if err != nil {
			continue
		}

		//extract links and images
		var outlinks []string
		var images []string

		//traverse HTML nodes
		var traverse func(*html.Node)
		traverse = func(n *html.Node) {
			if n.Type == html.ElementNode {
				if n.Data == "a" {
					//extract href
					for _, attr := range n.Attr {
						if attr.Key == "href" {

							link := resolveURL(currentURL, attr.Val)
							if link != "" && (strings.HasPrefix(link, "http://") || strings.HasPrefix(link, "https://")) {								outlinks = append(outlinks, link)
							}
						}
					}
				}
				if n.Data == "img" {
					//extract src
					for _, attr := range n.Attr {
						if attr.Key == "src" {
							images = append(images, resolveURL(currentURL, attr.Val))
						}
					}
				}
			}
			for c := n.FirstChild; c != nil; c = c.NextSibling {
				traverse(c)
			}
		}
		traverse(doc)

		//update redis priority queue with outlinks
		for _, link := range outlinks {
			score, _ := rdb.ZScore(ctx, "crawl_queue", link).Result()
			newScore := score - 1
			isVisited, _ := rdb.SIsMember(ctx, "visited", link).Result()
			if !isVisited {
				rdb.ZAdd(ctx, "crawl_queue", redis.Z{Score: newScore, Member: link})
			}
		}

		//save page to mongodb
		page := Page{
			URL: currentURL,
			Outlinks: outlinks,
			Images: images,
			HTML: bodyString,
		}
		savePage(db, page)

		//delay between requests
		time.Sleep(DELAY)

	}
}

/*resolveURL - resolves relative urls to absolute*/
func resolveURL(base, href string) string {
	if strings.HasPrefix(href, "#") || href == "" {
		return ""
	}
	baseURL, err := url.Parse(base)
	if err != nil {
		return ""
	}
	refURL, err := url.Parse(href)
	if err != nil {
		return ""
	}
	resolved := baseURL.ResolveReference(refURL)
	return resolved.String()
}

/* saves a page to mongodb */
func savePage(db *mongo.Database, page Page) {
	ctx := context.Background()
	collection := db.Collection("pages")
	filter := bson.D{{Key: "url", Value: page.URL}}
	update := bson.D{{Key: "$set", Value: page}}
	opts := options.UpdateOne().SetUpsert(true)
	_, err := collection.UpdateOne(ctx, filter, update, opts)
	if err != nil {
		fmt.Println("Error saving page:", err)
	}
}
