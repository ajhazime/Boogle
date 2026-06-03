package main

import (
	"context"
	"fmt"
	"os"

	"go.mongodb.org/mongo-driver/v2/bson"
	"go.mongodb.org/mongo-driver/v2/mongo"
	"go.mongodb.org/mongo-driver/v2/mongo/options"
)

const (
	DAMPING    = 0.85 //Damping for pagerank function
	ITERATIONS = 50
)

type Page struct { //to represent a pag from MongoDB
	URL      string
	Outlinks []string
}

func main() {
	uri := os.Getenv("MONGO_URI")                                //get mongo URI
	client, err := mongo.Connect(options.Client().ApplyURI(uri)) //Connect to mongodb
	if err != nil {
		panic(err)
	}
	defer client.Disconnect(context.TODO()) //defer disconnect

	/* Connecting DB and fetching collections */
	db := client.Database("boogle")
	pagesCol := db.Collection("pages")

	cursor, err := pagesCol.Find(context.TODO(), bson.D{}) //bson.D{} for empty queries
	if err != nil {
		panic(err)
	}

	/* Decode cursor results into slice of page structs*/
	var pages []Page
	if err = cursor.All(context.TODO(), &pages); err != nil {
		panic(err)
	}

	outlinks := make(map[string][]string)
	for _, page := range pages { //discard index
		outlinks[page.URL] = page.Outlinks
	}

	/*
		=================================
		FUN PART: PAGERANK ALGO
		=================================
	*/

	/* Initialize pagerank scores */
	totalPages := len(pages)
	scores := make(map[string]float64)
	for _, page := range pages {
		scores[page.URL] = 1.0 / float64(totalPages)
	}

	/* iterate pagerank ITERATIONS times */
	for i := 0; i < ITERATIONS; i++ {
		//create new scores for curr iteration
		newScores := make(map[string]float64)

		//loop through each page
		for _, page := range pages {
			//base scoring to start
			newScores[page.URL] = (1.0 - DAMPING) / float64(totalPages)
		}

		//distribute scores through outlinks
		for url, links := range outlinks {
			//for each outlink add score contribution
			for _, link := range links {
				newScores[link] += DAMPING * (scores[url] / float64(len(links)))
			}
		}
		scores = newScores
	}
	//Save pagerank score to MonogDb
	pagerankCol := db.Collection("pagerank")
	for url, score := range scores {
		//upset each url and score
		filter := bson.D{{Key: "url", Value: url}}
		update := bson.D{{Key: "$set", Value: bson.D{
			{Key: "url", Value: url},
			{Key: "score", Value: score},
		}}}
		_, err := pagerankCol.UpdateOne(context.TODO(), filter, update, options.UpdateOne().SetUpsert(true))
		if err != nil {
			fmt.Printf("Error saving %s: %v\n", url, err)
		}
	}

	fmt.Printf("Got to the end. pagerank successful")
}
