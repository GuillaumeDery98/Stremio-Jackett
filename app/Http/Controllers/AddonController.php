<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use RealDebrid\Auth\Token;
use RealDebrid\RealDebrid;
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
        dd($this->stream('series', 'tt3581920:1:2'));
        return view('welcome')->with('movie', $this->stream('movie', 'tt4209788'));
        return $this->stream('movie', 'tt14208870');
        $type = 'movie';
        $name = 'Babylon 2022';
        $rss = FeedReader::read('http://' . env("JACKETT_URL") . '/api/v2.0/indexers/yggtorrent/results/torznab?apikey=' . env('JACKETT_API_KEY') . '&t=' . $type . '&q=' . $name . '&limit=5');
        dd($rss->get_items());
        return $this->realDebrid('http://' . env("JACKETT_URL") . '/dl/yggtorrent/?jackett_apikey=50a3psycd00jocn6e02w2s7c6khuziho&path=Q2ZESjhNQmVyVm96NjU5THAzS0F4azc0T0Z5MEdsRkxHYlhXa0tha2oyaVg5SkxmUDhlZ0lnbFV4NS1VVGlhQjg3azF1enNRb1c0LVIxQkk0eGxtdGJvUnhxbTlnWjB0aG1hMV9mNWh2VWJZQ0owc01uYVNGbGtNOWRqWUN6VFY2TjF2REhGc3RETTJxNm4yNkhtdENpejR4eUs1OHdibl9tZzBsZEhXa3RBQUdZMjYxOUVBT0RxVjloTFY1NXhvdDFicE5R&file=Black+Panther+Wakanda+Forever+(2022)+Hybrid+MULTi+VFF+2160p+10bit+4KLight+DV+HDR+BluRay+DDP+5.1+Atmos+x265-QTZ');
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
        if ($type == 'series') {
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
        //$title = ($infos->orig_title()) ? $infos->orig_title() : $infos->title();
        $title = $infos->title();
        $name = ($type == 'series') ? $title . ' S' . $season . 'E' . $episode : $title;
        $name = $this->normalizeName($name);
        Log::debug($name);



        return response()->json([
            "streams" => $this->getTorrent($name, $type, $infos->year())
        ]);
    }

    public function normalizeName($name)
    {
        $name = str_replace(' ', '.', $name);
        $name = str_replace('&', 'and', $name);
        $name = str_replace(':', '.', $name);
        $name = str_replace('_', '.', $name);
        $name = str_replace("d's", 'ds', $name);

        return $name;
    }

    public function getInfos($id)
    {
        $config = new \Imdb\Config();
        //$config->language = 'fr-FR,fr,en';
        $config->language = 'en';
        $title = new \Imdb\Title($id);

        return $title;

        /* Custom request via api
        $client = new Client();
        $response = end(json_decode($client->request("GET", 'https://imdb-movies-web-series-etc-search.p.rapidapi.com/' . $id . '.json', [
            'headers' => [
                'X-RapidAPI-Key' => env('IMDB_API_KEY'),
                'X-RapidAPI-Host' => 'imdb-movies-web-series-etc-search.p.rapidapi.com'
            ]
        ])->getBody()->getContents(), true)["d"]);

        return $response; */
    }

    public function getTorrent($name, $type, $year)
    {
        $typeJackett = ($type == 'movie' ? 'movie' : 'tvsearch');
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

        if ($type == 'series') {
            $nameSeason = substr($name, 0, -3);
            Log::debug("Comple season: " . $name);
            $rss = FeedReader::read('http://' . env("JACKETT_URL") . '/api/v2.0/indexers/yggtorrent/results/torznab?apikey=' . env('JACKETT_API_KEY') . '&t=' . $typeJackett . '&q=' . $nameSeason . '&limit=' . env('RESULT_LIMIT'));
            $data[] = $this->chooseTorrent($rss->get_items(), $nameSeason, $type, $year, true);
            $data = ($data[0]) ? $data : [];
        }

        if (count($data) < 1) {
            $rss = FeedReader::read('http://' . env("JACKETT_URL") . '/api/v2.0/indexers/yggtorrent/results/torznab?apikey=' . env('JACKETT_API_KEY') . '&t=' . $typeJackett . '&q=' . $name . '&limit=' . env('RESULT_LIMIT'));
            $data[] = $this->chooseTorrent($rss->get_items(), $name, $type, $year);
        }


        return $data;
    }

    public function chooseTorrent($torrents, $name, $type, $year, $completeSeason = false)
    {
        $choosed = [
            "score" => 0
        ];
        foreach (array_reverse($torrents) as $torrent) {
            $torrent = $torrent->data['child'][""];
            $title = $torrent["title"][0]['data'];
            $score = 0;
            if (str_contains($title, $year) && $type == 'movie') {
                $score++;
            }
            if (str_contains($title, '2160p') || str_contains($title, '4K')) {
                $score++;
            }
            if ($completeSeason && str_contains($title, $name . 'E')) {
                $score = -1;
            }
            if ($score >= $choosed['score']) {
                $choosed = [
                    "score" => $score,
                    "torrent" => $torrent
                ];
            }
        }
        return (isset($choosed['torrent'])) ? [
            "name" => 'Guillaume Addon',
            "description" => $choosed['torrent']["title"][0]['data'],
            "url" => $this->realDebrid($choosed['torrent']["link"][0]['data'], $name, $type)
        ] : null;
    }

    public function realDebrid($torrentLink, $name = null, $type = 'movie')
    {
        $token = new Token(env('REALDREBRID_API_KEY'));
        $realDebrid = new RealDebrid($token);
        $torrentList = (array)$realDebrid->torrents->get();


        $filename = 'temp.torrent';
        $torrent = tempnam(sys_get_temp_dir(), $filename);
        copy($torrentLink, $torrent);
        $torrent = (array)$realDebrid->torrents->addTorrent($torrent);
        $torrentInfo = (array)$realDebrid->torrents->torrent($torrent['id']);


        return route('download', ['id' => $torrentInfo['id'], 'name' => $name, 'type' => $type]);
        /*
        foreach ($torrentList as $old) {
            $old = (array)$old;
            if ($old['filename'] == $torrentInfo['filename']) {
                $torrentInfo = (array)$realDebrid->torrents->torrent($old['id']);
                $realDebrid->torrents->delete($torrent['id']);
            }
        }*/

        /*
        if ($torrentInfo['status'] != 'downloaded') {
            Log::debug("To Download: " . $torrentInfo['id']);
            return route('download', ['id' => $torrentInfo['id'], 'name' => $name, 'type' => $type]);
        }

        $link = (array)$realDebrid->unrestrict->link($torrentInfo['links'][0]);

        return $link['download'];*/
    }

    public function download($id, $name = null, $type = 'movie', $redirect = true)
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

        if ($type == 'series') {
            Log::debug("Download Series");
            foreach ($torrentInfo['links'] as $link) {
                $link = (array)$realDebrid->unrestrict->link($link);
            }
            $downloads = (array)$realDebrid->downloads->get();
            foreach ($downloads as $download) {
                $download = (array)$download;
                Log::debug(strtoupper($this->normalizeName($download['filename'])) . ' try to match with ' . strtoupper($name));
                if (str_contains(strtoupper($this->normalizeName($download['filename'])), strtoupper($name))) {
                    Log::debug(strtoupper($this->normalizeName($download['filename'])) . ' MATCH WITH ' . strtoupper($name));
                    return redirect()->to($download['download']);
                }
            }
            Log::debug("no match... launching first file download");
        }


        $link = (array)$realDebrid->unrestrict->link($torrentInfo['links'][0]);

        if (!$redirect) {
            return $link['download'];
        }

        return redirect()->to($link['download']);
    }

    public function catalog($type, $id, $extra = null)
    {
        return null;
    }

    public function getTrailer($name)
    {
        return null;
        /*
        $trailer = $name . ' bande annonce vf';
        $trailer = str_replace(' ', '%20', $trailer);
        $client = new Client();
        $response = json_decode($client->request("GET", 'https://youtube-search-results.p.rapidapi.com/youtube-search/', [
            'headers' => [
                'X-RapidAPI-Key' => env('YOUTUBE_API_KEY'),
                'X-RapidAPI-Host' => 'youtube-search-results.p.rapidapi.com'
            ],
            'query' => [
                'q' => $trailer
            ]
        ])->getBody()->getContents(), true);


        $trailer = $response["items"][0]["id"] ?? null;

        return [[
            "name" => 'Guillaume Trailer',
            "description" => 'Bande annonce vf',
            "ytId" => $trailer,
            "source" => $trailer
        ]];*/
    }

    /**
     * Detailed description of meta item. This description is displayed when the user selects an item form the catalog.
     * @param $type
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function meta($type, $id, $extra = null)
    {
        if ($type == 'series') {
            return null;
        }
        $meta = $this->getInfos($id);
        return response()->json([
            "meta" => [
                "id" => $id,
                "name" => $meta->title(),
                "description" => $meta->storyline(),
                "genres" => $meta->genres(),
                "year" => $meta->year(),
                "poster" => $meta->photo(false),
                "posterShape" => "poster",
                "logo" => $meta->photo(),
                "runtime" => $meta->runtimes()[0]['time'] . ' min',
                "background" => $meta->photo(false),
                "type" => $type,
                "trailers" => $this->getTrailer($meta->title()),
                "imdbRating" => $meta->rating(),
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
            "id" => "org.stremio.guillaume",
            "version" => "0.0.1",
            "description" => "Addon for get torrent from Jackett and have real-debrid flux",
            "name" => "Guillaume Add-on",
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
