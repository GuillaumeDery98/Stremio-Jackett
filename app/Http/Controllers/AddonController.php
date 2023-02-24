<?php

namespace App\Http\Controllers;

use Imdb\Title;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Vedmant\FeedReader\Facades\FeedReader;

/**
 * Class addonController
 * @package App\Http\Controllers
 */
class AddonController extends Controller
{
    public function test()
    {
        Log::debug('test');
        dd($this->stream('movie', 'tt9764362'));
    }
    /**
     * Summarized collection of meta items. Catalogs are displayed on the Stremio's Board, Discover and Search.
     * @param $type
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function catalog($type, $id, $extra = null)
    {
        Log::debug('catalog => type: ' . $type . ' => id : ' . $id . ' => extra : ' . $extra);
        return response()->json([
            "metas" => [
                [
                    "id" => "BigBuckBunny",
                    "name" => "Big Buck Bunny",
                    "year" => 2008,
                    "poster" => "https://image.tmdb.org/t/p/w600_and_h900_bestv2/uVEFQvFMMsg4e6yb03xOfVsDz4o.jpg",
                    "posterShape" => "regular",
                    "banner" => "https://image.tmdb.org/t/p/original/aHLST0g8sOE1ixCxRDgM35SKwwp.jpg",
                    "isFree" => true,
                    "type" => "movie"
                ]
            ]
        ]);
    }

    /**
     * Detailed description of meta item. This description is displayed when the user selects an item form the catalog.
     * @param $type
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function meta($type, $id, $extra = null)
    {
        Log::debug('meta => type: ' . $type . '=> id: ' . $id . '=> extra: ' . $extra);
        return response()->json([
            "meta" => [
                "id" => "BigBuckBunny",
                "name" => "Big Buck Bunny",
                "year" => 2008,
                "poster" => "https://image.tmdb.org/t/p/w600_and_h900_bestv2/uVEFQvFMMsg4e6yb03xOfVsDz4o.jpg",
                "posterShape" => "regular",
                "logo" => "https://fanart.tv/fanart/movies/10378/hdmovielogo/big-buck-bunny-5054df8a36bfa.png",
                "background" => "https://image.tmdb.org/t/p/original/aHLST0g8sOE1ixCxRDgM35SKwwp.jpg",
                "isFree" => true,
                "type" => "movie"
            ]
        ]);
    }

    /**
     * Tells Stremio how to obtain the media content. It may be torrent info hash, HTTP URL, etc
     * @param $type
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function stream($type, $id, $extra = null)
    {

        Log::debug('stream => type: ' . $type . '=> id: ' . $id . '=> extra: ' . $extra);
        $infos = $this->getInfos($id);
        Log::debug($infos["l"]);
        $name = $infos["l"];

        return response()->json([
            "streams" => [
                $this->getTorrent($name, $type)[0]
            ]
        ]);
    }

    public function getInfos($id)
    {
        $client = new Client();
        $response = end(json_decode($client->request("GET", 'https://imdb-movies-web-series-etc-search.p.rapidapi.com/' . $id . '.json', [
            'headers' => [
                'X-RapidAPI-Key' => env('IMDB_API_KEY'),
                'X-RapidAPI-Host' => 'imdb-movies-web-series-etc-search.p.rapidapi.com'
            ]
        ])->getBody()->getContents(), true)["d"]);

        return $response;
    }

    public function getTorrent($name, $type, $year = null)
    {
        $rss = FeedReader::read('http://192.168.1.2:9117/api/v2.0/indexers/yggtorrent/results/torznab?apikey=' . env('JACKETT_API_KEY') . '&t=' . $type . '&q=' . $name);
        $data = [];
        /* Full datas 
        "title" => $item->data['child'][""]["title"][0]['data'],
                "guid" => $item->data['child'][""]["guid"][0]['data'],
                "jackettindexer" => $item->data['child'][""]["jackettindexer"][0]['data'],
                "type" => $item->data['child'][""]["type"][0]['data'],
                "comments" => $item->data['child'][""]["comments"][0]['data'],
                "pubDate" => $item->data['child'][""]["pubDate"][0]['data'],
                "size" => $item->data['child'][""]["size"][0]['data'],
                "grabs" => $item->data['child'][""]["grabs"][0]['data'],
                "description" => $item->data['child'][""]["description"][0]['data'],
                "link" => $item->data['child'][""]["link"][0]['data'],
                "category" => $item->data['child'][""]["category"][0]['data'],
                "enclosure" => $item->data['child'][""]["enclosure"][0]['data'],
        */
        foreach ($rss->get_items() as $item) {
            Log::debug("torrent: " . $item->data['child'][""]["link"][0]['data']);
            $data[] = [
                "name" => 'YggTorrent',
                "url" => $item->data['child'][""]["link"][0]['data']
            ];
        }

        return $data;
    }

    /**
     * Subtitles resource for the chosen media.
     * @param $type
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function subtitles($type, $id, $extra = null)
    {
        return response()->json([]);
    }

    /**
     * Generate a manifest.json file
     * @return \Illuminate\Http\JsonResponse
     */
    public function manifest()
    {
        return response()->json([
            "id" => "org.stremio.laravel",
            "version" => "0.0.1",
            "description" => "Laravel Stremio Add-on",
            "name" => "Laravel Add-on",
            "resources" => [
                "catalog",
                "meta",
                "stream"
            ],
            "types" => [
                "movie",
                "series"
            ],
            "catalogs" => [
                [
                    "type" => "movie",
                    "id" => "moviecatalog"
                ]
            ],
            "idPrefixes" => ["tt"]
        ]);
    }
}
