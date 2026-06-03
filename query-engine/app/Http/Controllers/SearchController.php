<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request){
        $searchRequest = $request->input('q');
        $words = explode(' ', $searchRequest); //split searchRequest into words

        /* Connecting MongoDB */
        $mongo = new \MongoDB\Client(env('MONGO_URI'), [], ['tlsCAFile' => '/opt/homebrew/etc/openssl@3/cert.pem']);
        $db = $mongo->boogle;
        $indexCollection = $db->index;

        $results = [];
        foreach($words as $word){
            $doc = $indexCollection->findOne(['word' => $word]); //search for docs containing word
            if ($doc){ //if a doc has the word search its urls and add score to results
                foreach($doc['urls'] as $url => $score){
                    if(isset($results[$url])){
                        $results[$url] += $score; //add to url's score
                    } 
                    else {
                        $results[$url] = $score; //add url and score to results
                    }
                }
            }
        }

        /* 
        ==================================================
        adding pagerank (after commit 9, before commit 10) 
        ==================================================
        */

        //get pagerank collection
        $pagerankCollection = $db->pagerank;
        //multiply each result score by its pagerank score
        foreach ($results as $url => $score){
            $pr = $pagerankCollection->findOne(['url' => $url]);
            if($pr){
                $results[$url] = $score * $pr['score'];
            }
        }
        /* end pagerank addition */

        arsort($results); //sort scores in descending order 
        return response()->json($results);
    }
}

