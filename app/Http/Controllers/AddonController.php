<?php

namespace App\Http\Controllers;

use Imdb\Title;
use GuzzleHttp\Client;
use RealDebrid\Auth\Token;
use RealDebrid\RealDebrid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Vedmant\FeedReader\Facades\FeedReader;

/**
 * Class addonController
 * @package App\Http\Controllers
 */
class AddonController extends Controller
{
    public function test()
    {

        dd($this->stream('movie', 'tt11813216'));
        $type = 'movie';
        $name = 'Babylon 2022';
        $rss = FeedReader::read('http://192.168.1.2:9117/api/v2.0/indexers/yggtorrent/results/torznab?apikey=' . env('JACKETT_API_KEY') . '&t=' . $type . '&q=' . $name . '&limit=5');
        dd($rss->get_items());
        $this->stream('serie', 'tt3581920:1:2');
        return $this->realDebrid('http://192.168.1.2:9117/dl/yggtorrent/?jackett_apikey=50a3psycd00jocn6e02w2s7c6khuziho&path=Q2ZESjhNQmVyVm96NjU5THAzS0F4azc0T0Z5MEdsRkxHYlhXa0tha2oyaVg5SkxmUDhlZ0lnbFV4NS1VVGlhQjg3azF1enNRb1c0LVIxQkk0eGxtdGJvUnhxbTlnWjB0aG1hMV9mNWh2VWJZQ0owc01uYVNGbGtNOWRqWUN6VFY2TjF2REhGc3RETTJxNm4yNkhtdENpejR4eUs1OHdibl9tZzBsZEhXa3RBQUdZMjYxOUVBT0RxVjloTFY1NXhvdDFicE5R&file=Black+Panther+Wakanda+Forever+(2022)+Hybrid+MULTi+VFF+2160p+10bit+4KLight+DV+HDR+BluRay+DDP+5.1+Atmos+x265-QTZ');
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
        if ($type == 'serie') {
            $serie = explode(':', $id);
            $id = $serie[0];
            if (count($serie) > 1) {
                $season = $serie[count($serie) - 2];
                $season = (strlen($season) == 1 ? '0' . $season : $season);
                $episode = $serie[count($serie) - 1];
                $episode = (strlen($episode) == 1 ? '0' . $episode : $episode);
                Log::debug('s: ' . $season . '=> ep: ' . $episode);
            }
        }
        $infos = $this->getInfos($id);
        $name = ($type == 'serie') ? $infos["l"] . ' S' . $season . 'E' . $episode : $infos["l"] . ' ' . $infos["y"];
        Log::debug($name);

        return response()->json([
            "streams" => $this->getTorrent($name, $type)
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
        $type = ($type == 'movie' ? 'movie' : 'tvsearch');
        $rss = FeedReader::read('http://192.168.1.2:9117/api/v2.0/indexers/yggtorrent/results/torznab?apikey=' . env('JACKETT_API_KEY') . '&t=' . $type . '&q=' . $name . '&limit=' . env('RESULT_LIMIT'));
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
                "name" => 'Guillaume Addon',
                "description" => $item->data['child'][""]["title"][0]['data'],
                "url" => $this->realDebrid($item->data['child'][""]["link"][0]['data'])
            ];
        }

        return $data;
    }

    public function realDebrid($torrentLink)
    {
        $token = new Token(env('REALDREBRID_API_KEY'));
        $realDebrid = new RealDebrid($token);
        $torrentList = (array)$realDebrid->torrents->get();


        $filename = 'temp.torrent';
        $torrent = tempnam(sys_get_temp_dir(), $filename);
        copy($torrentLink, $torrent);
        $torrent = (array)$realDebrid->torrents->addTorrent($torrent);
        $torrentInfo = (array)$realDebrid->torrents->torrent($torrent['id']);


        foreach ($torrentList as $old) {
            $old = (array)$old;
            if ($old['filename'] == $torrentInfo['filename']) {
                $torrentInfo = (array)$realDebrid->torrents->torrent($old['id']);
                $realDebrid->torrents->delete($torrent['id']);
            }
        }

        if ($torrentInfo['status'] != 'downloaded') {
            Log::debug("To Download: " . $torrentInfo['id']);
            return route('download', ['id' => $torrentInfo['id']]);
        }

        $link = (array)$realDebrid->unrestrict->link($torrentInfo['links'][0]);

        return $link['download'];
    }

    public function download($id)
    {
        Log::debug("Download: " . $id);
        $token = new Token(env('REALDREBRID_API_KEY'));
        $realDebrid = new RealDebrid($token);
        $realDebrid->torrents->selectFiles($id);


        $torrentInfo = (array)$realDebrid->torrents->torrent($id);


        while ($torrentInfo['status'] != 'downloaded') {
            $torrentInfo = (array)$realDebrid->torrents->torrent($id);
            sleep(0.25);
        }

        $link = (array)$realDebrid->unrestrict->link($torrentInfo['links'][0]);

        return redirect()->to($link['download']);
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
