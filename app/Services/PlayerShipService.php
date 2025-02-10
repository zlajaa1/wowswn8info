<?php

namespace App\Services;

use App\Models\ClanMember;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\Ship;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;


class PlayerShipService
{
    protected $apiKey;
    protected $apiUrl = "https://api.worldofwarships.eu/wows/ships/stats/";
    protected $apiUrlNames = "https://api.worldofwarships.eu/wows/account/info/";

    protected $baseUrls;


    protected $expectedValues;
    public function __construct()
    {
        $this->apiKey = config('services.wargaming.api_key');

        $this->baseUrls = [
            'eu' => 'https://api.worldofwarships.eu',
            'na' => 'https://api.worldofwarships.com',
            'asia' => 'https://api.worldofwarships.asia',
        ];

        ini_set('memory_limit', '512M');
    }

    public function loadExpectedValues()
    {
        $path = resource_path('expected_values.json');
        if (!File::exists($path)) {
            Log::error("Expected values file not found at: $path");
            throw new \Exception("Expected values file not found");
        }

        $jsonContent = File::get($path);
        $decodedData = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("Invalid JSON in expected values file", [
                'error' => json_last_error_msg(),
                'path' => $path
            ]);
            throw new \Exception("Invalid JSON in expected values file");
        }

        $this->expectedValues = $decodedData;
    }

    private function calculateWN8($ship, $totalBattles, $totalFrags, $totalWins, $totalDamageDealt)
    {
        $shipId = $ship->ship_id; // Extract the ship_id from the model

        //check if it's missing or empty
        if (
            !isset($this->expectedValues['data'][$shipId]) ||
            empty($this->expectedValues['data'][$shipId])
        ) {
            Log::warning("Expected values not found or empty for ship_id: $shipId");
            return null;
        }

        //store expected values for each ship in a variable
        $expected = $this->expectedValues['data'][$shipId];

        //get final expected values by multiplying expected values with number of battles
        $expectedDamage = $expected['average_damage_dealt'] * $totalBattles;
        $expectedFrags = $expected['average_frags'] * $totalBattles;
        $expectedWins = ($expected['win_rate'] / 100) * $totalBattles;

        // Ratios
        $rDmg = $expectedDamage > 0 ? $totalDamageDealt / $expectedDamage : 0;
        $rFrags = $expectedFrags > 0 ? $totalFrags / $expectedFrags : 0;
        $rWins = $expectedWins > 0 ? $totalWins / $expectedWins : 0;

        // Normalize
        $nDmg = max(0, ($rDmg - 0.4) / (1 - 0.4));
        $nFrags = max(0, ($rFrags - 0.1) / (1 - 0.1));
        $nWins = max(0, ($rWins - 0.7) / (1 - 0.7));


        // WN8 formula
        $wn8 = (1000 * $nDmg) + (100 * $nFrags) + (200 * $nWins);


        return $wn8;
    }

    public function totalPlayerWN8($playerId)
    {
        $playerShips = PlayerShip::where('account_id', $playerId)->get();


        $total_weighted_wn8 = 0;
        $total_battles = 0;

        foreach ($playerShips as $playerShip) {
            //condition: if there's battles played at all for that player 
            //and corresponding wn8 for the ship played
            if ($playerShip->battles_played > 0 && $playerShip->wn8 !== null) {
                //weighted by total battles to get the total
                $total_weighted_wn8 += $playerShip->wn8 * $playerShip->battles_played;
                $total_battles += $playerShip->battles_played;
            }
        }

        $player_total_wn8 = $total_battles > 0 ? $total_weighted_wn8 / $total_battles : 0;



        return $player_total_wn8;
    }


    private function calculatePR($ship, $totalBattles, $totalFrags, $totalWins, $totalDamageDealt)
    {
        //PR FORMULA - DIFFERENT RATIOS BUT SAME PARAMETERS AS WN8
        $shipId = $ship->ship_id;

        if (
            !isset($this->expectedValues['data'][$shipId]) ||
            empty($this->expectedValues['data'][$shipId])
        ) {
            Log::warning("Expected values not found or empty for ship_id: $shipId");
            return null;
        }

        //store expected values for each ship in a varibale
        $expected = $this->expectedValues['data'][$shipId];

        //get final expected values by multiplying expected values with number of battles
        $expectedDamage = $expected['average_damage_dealt'] * $totalBattles;
        $expectedFrags = $expected['average_frags'] * $totalBattles;
        $expectedWins = ($expected['win_rate'] / 100) * $totalBattles;

        // Ratios
        $rDmg = $expectedDamage > 0 ? $totalDamageDealt / $expectedDamage : 0;
        $rFrags = $expectedFrags > 0 ? $totalFrags / $expectedFrags : 0;
        $rWins = $expectedWins > 0 ? $totalWins / $expectedWins : 0;

        // Normalize
        $nDmg = max(0, ($rDmg - 0.4) / (1 - 0.4));
        $nFrags = max(0, ($rFrags - 0.1) / (1 - 0.1));
        $nWins = max(0, ($rWins - 0.7) / (1 - 0.7));


        // PR formula
        $pr = ceil((700 * $nDmg) + (300 * $nFrags) + (150 * $nWins));

        Log::info("Caclulated some stats for player's ship:  $nDmg, $nFrags, $nWins");
        Log::info("Caclulated the total PR for player's ship:  $pr");

        return $pr;
    }

    public function totalPlayerPR($playerId)
    {
        $playerShips = PlayerShip::where('account_id', $playerId)->get();

        $total_weighted_pr = 0;
        $total_battles = 0;

        foreach ($playerShips as $playerShip) {
            if ($playerShip->battles_played > 0 && $playerShip->pr !== null) {
                $total_weighted_pr += $playerShip->pr * $playerShip->battles_played;
                $total_battles += $playerShip->battles_played;
            }
        }


        $player_total_pr = ceil($total_battles > 0 ? $total_weighted_pr / $total_battles : 0);
        Log::info("Caclulated the total PR for player:  $player_total_pr");

        return $player_total_pr;
    }

    private function extractBattleStats($stats, $battleType)
    {
        return [
            'battles' => $stats[$battleType]['battles'] ?? 0,
            'wins' => $stats[$battleType]['wins'] ?? 0,
            'damage_dealt' => $stats[$battleType]['damage_dealt'] ?? 0,
            'frags' => $stats[$battleType]['frags'] ?? 0,
            'xp' => $stats[$battleType]['xp'] ?? 0,
            'survived_battles' => $stats[$battleType]['survived_battles'] ?? 0,
            'distance' => $stats[$battleType]['distance'] ?? 0,
            'ships_spotted' => $stats[$battleType]['ships_spotted'] ?? 0,
            'capture_points' => $stats[$battleType]['capture_points'] ?? 0,
            'dropped_capture_points' => $stats[$battleType]['dropped_capture_points'] ?? 0,
        ];
    }

    public function cacheTopPlayersList()
    {
        $stats24h = $this->getTopPlayersLast24Hours();
        $stats7d = $this->getTopPlayersLast7Days();
        $stats30d = $this->getTopPlayersLastMonth();

        Cache::put('stats_24h', $stats24h, now()->addDay());
        Cache::put('stats_7d', $stats7d, now()->addWeek());
        Cache::put('stats_30d', $stats30d, now()->addMonth());
    }



    public function getTopPlayersLast24Hours()
    {

        return Cache::remember('stats_24h', now()->addDay(), function () {
            $last24Hours = now()->subHours(24);

            return PlayerShip::select('account_id', DB::raw('MAX(player_name) as player_name'), DB::raw('MAX(total_player_wn8) as total_player_wn8'))
                ->where('ship_tier', '>', 5)
                ->where('battles_played', '>', 5)
                ->groupBy('account_id')
                ->orderByDesc('total_player_wn8')
                ->limit(10)
                ->get()
                ->map(function ($player) {
                    return [
                        'name' => $player->player_name,
                        'wid' => $player->account_id,
                        'wn8' => $player->total_player_wn8,
                    ];
                })
                ->toArray();
        });
    }



    public function getTopPlayersLast7Days()
    {

        return Cache::remember('stats_7d', now()->addWeek(), function () {
            $last7days = now()->subDays(6);

            return PlayerShip::select('account_id', DB::raw('MAX(player_name) as player_name'), DB::raw('MAX(total_player_wn8) as total_player_wn8'))
                ->where('ship_tier', '>', 5)
                ->where('battles_played', '>', 30)
                ->groupBy('account_id')
                ->orderByDesc('total_player_wn8')
                ->limit(10)
                ->get()
                ->map(function ($player) {
                    return [
                        'name' => $player->player_name,
                        'wid' => $player->account_id,
                        'wn8' => $player->total_player_wn8,
                    ];
                })
                ->toArray();
        });
    }

    public function getTopPlayersLastMonth()
    {

        return Cache::remember('stats_30d', now()->addMonth(), function () {
            $lastMonth = now()->subDays(25);

            return PlayerShip::select('account_id', DB::raw('MAX(player_name) as player_name'), DB::raw('MAX(total_player_wn8) as total_player_wn8'))
                ->where('ship_tier', '>', 5)
                ->where('battles_played', '>', 120)
                ->groupBy('account_id')
                ->orderByDesc('total_player_wn8')
                ->limit(10)
                ->get()
                ->map(function ($player) {
                    return [
                        'name' => $player->player_name,
                        'wid' => $player->account_id,
                        'wn8' => $player->total_player_wn8,
                    ];
                })
                ->toArray();
        });
    }

    public function getTopPlayersOverall()
    {

        $overall = now()->subDays(29);

        return PlayerShip::select('account_id', DB::raw('MAX(player_name) as player_name'), DB::raw('MAX(total_player_wn8) as total_player_wn8'))
            ->where('ship_tier', '>', 5)
            ->where('battles_played', '>', 500)
            ->where('updated_at', '>=', $overall)
            ->groupBy('account_id')
            ->orderByDesc('total_player_wn8')
            ->limit(10)
            ->get()
            ->map(function ($player) {
                return [
                    'name' => $player->player_name,
                    'wid' => $player->account_id,
                    'wn8' => $player->total_player_wn8,
                ];
            })
            ->toArray();
    }





    // TO DO: 
    //Ovdje treba proslijediti sa fronta ono što igrač klikne / upiše, 
    //npr. Ako klikne na "Bismarck" brod 
    //onda aplikacija taj string ovdje unese kao parametar
    // $ship_name = 'Bismarck';
    public function getPlayerStatsByVehicle($ship_name)
    {


        return DB::table('player_ships')
            ->select(
                'account_id',
                'player_name',
                'battles_played',
                'wins_count',
                'ship_tier',
                'ship_type',
                'survival_rate',
                'damage_dealt',
                'frags',
                'xp',
                'capture',
                'defend',
                'spotted',
                'wn8'
            )
            ->where('ship_name', $ship_name)
            ->orderBy('total_player_wn8', 'desc')
            ->get();
    }


    public function getNullNamePlayersNames(): void
    {
        $playerIds = PlayerShip::whereNull('player_name')->pluck('account_id')->unique()->all();

        foreach ($this->baseUrls as $serverKey => $baseUrl) {
            foreach ($playerIds as $playerId) {
                $url = $baseUrl . "/wows/account/info/";
                $response = Http::get($url, [
                    'application_id' => $this->apiKey,
                    'account_id' => $playerId,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['data'][$playerId]['nickname'])) {
                        $playerName = $data['data'][$playerId]['nickname'];
                        PlayerShip::where('account_id', $playerId)->update(['player_name' => $playerName]);
                        Log::info("Updated player name for account_id: $playerId", ['player_name' => $playerName]);
                    } else {
                        Log::warning("Nickname not found in API response for account_id: $playerId", ['server' => strtoupper($serverKey)]);
                    }
                } else {
                    Log::error("Failed to fetch player name from API", [
                        'account_id' => $playerId,
                        'server' => strtoupper($serverKey),
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                }
            }
        }
    }



    public function fetchAndStorePlayerShips()
    {


        try {
            $this->loadExpectedValues();
        } catch (\Exception $e) {
            Log::error("Failed to load expected values", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception("Failed to initialize: " . $e->getMessage());
        }

        Log::info('Starting fetchAndStorePlayerShips');

        try {
            $playerIds = ClanMember::pluck('account_id')->all();
            if (empty($playerIds)) {
                Log::info("No player ids found in database");
                return false;
            }

            Log::info("Data loaded", ['players_count' => count($playerIds)]);
            foreach ($this->baseUrls as $serverKey => $baseUrl) {

                $url = $baseUrl . "/wows/ships/stats/";

                foreach ($playerIds as $playerId) {


                    $response = Http::get($url, [
                        'application_id' => $this->apiKey,
                        'account_id' => $playerId,
                        'extra' => 'pve,club,pve_div2,pve_div3,pve_solo,pvp_solo,pvp_div2,pvp_div3,rank_solo,rank_div2,rank_div3'
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();

                        $playerName = ClanMember::where('account_id', $playerId)->value('account_name');



                        if (isset($data['data'][$playerId])) {
                            foreach ($data['data'][$playerId] as $shipStats) {
                                // Find the ship using ship_id from the API
                                $ship = Ship::where('ship_id', $shipStats['ship_id'])->first();


                                if (!$ship) {
                                    Log::warning("Ship not found in database", [
                                        'api_ship_id' => $shipStats['ship_id'],
                                        'player_id' => $playerId
                                    ]);
                                    continue;
                                }


                                //extract stats from ships table 
                                $shipName = $ship->name ?? 'Unknown ship name';
                                $shipType = $ship->type ?? 'Unknown ship type';
                                $shipTier = $ship->tier ?? 'Unknown ship tier';
                                $shipNation = $ship->nation ?? 'Unkown nation';


                                // Extract statistics for different battle types
                                $pvpStats = [];
                                $pvp2Stats = [];
                                $pvp3Stats = [];
                                $pveStats = [];
                                $pve_soloStats = [];
                                $pve2Stats = [];
                                $pve3Stats = [];
                                $clubStats = [];
                                $rankStats = [];
                                $rank_div2Stats = [];
                                $rank_div3Stats = [];

                                if (isset($shipStats['pvp'])) {
                                    $pvpStats = $this->extractBattleStats($shipStats, 'pvp');
                                }

                                if (isset($shipStats['pvp_div2'])) {
                                    $pvp2Stats = $this->extractBattleStats($shipStats, 'pvp_div2');
                                }

                                if (isset($shipStats['pvp_div3'])) {
                                    $pvp3Stats = $this->extractBattleStats($shipStats, 'pvp_div3');
                                }

                                if (isset($shipStats['pve'])) {
                                    $pveStats = $this->extractBattleStats($shipStats, 'pve');
                                }

                                if (isset($shipStats['pve_solo'])) {
                                    $pve_soloStats = $this->extractBattleStats($shipStats, 'pve_solo');
                                }

                                if (isset($shipStats['pve_div2'])) {
                                    $pve2Stats = $this->extractBattleStats($shipStats, 'pve_div2');
                                }

                                if (isset($shipStats['pve_div3'])) {
                                    $pve3Stats = $this->extractBattleStats($shipStats, 'pve_div3');
                                }

                                if (isset($shipStats['club'])) {
                                    $clubStats = $this->extractBattleStats($shipStats, 'club');
                                }

                                if (isset($shipStats['rank_solo'])) {
                                    $rankStats = $this->extractBattleStats($shipStats, 'rank_solo');
                                }

                                if (isset($shipStats['rank_div2'])) {
                                    $rank_div2Stats = $this->extractBattleStats($shipStats, 'rank_solo');
                                }

                                if (isset($shipStats['rank_div3'])) {
                                    $rank_div3Stats = $this->extractBattleStats($shipStats, 'rank_solo');
                                }




                                // Calculate total battles
                                $totalBattles = ($pvpStats['battles'] ?? 0) + ($pveStats['battles'] ?? 0)
                                    + ($clubStats['battles'] ?? 0) + ($rankStats['battles'] ?? 0)
                                    + ($rank_div2Stats['battles'] ?? 0) + ($rank_div3Stats['battles'] ?? 0)
                                    + ($pve_soloStats['battles'] ?? 0) + ($pve2Stats['battles'] ?? 0)
                                    + ($pve3Stats['battles'] ?? 0) + ($pvp2Stats['battles'] ?? 0)
                                    + ($pvp3Stats['battles'] ?? 0);

                                // Calculate total damage
                                $totalDamageDealt = ($pvpStats['damage_dealt'] ?? 0) + ($pveStats['damage_dealt'] ?? 0)
                                    + ($clubStats['damage_dealt'] ?? 0) + ($rankStats['damage_dealt'] ?? 0)
                                    + ($rank_div2Stats['damage_dealt'] ?? 0) + ($rank_div3Stats['damage_dealt'] ?? 0)
                                    + ($pve_soloStats['damage_dealt'] ?? 0) + ($pve2Stats['damage_dealt'] ?? 0)
                                    + ($pve3Stats['damage_dealt'] ?? 0) + ($pvp2Stats['damage_dealt'] ?? 0)
                                    + ($pvp3Stats['damage_dealt'] ?? 0);


                                $averageDamage = $totalBattles > 0 ? $totalDamageDealt / $totalBattles : 0;

                                //calculate total wins
                                $totalWins = ($pvpStats['wins'] ?? 0) + ($pveStats['wins'] ?? 0)
                                    + ($clubStats['wins'] ?? 0) + ($rankStats['wins'] ?? 0)
                                    + ($rank_div2Stats['wins'] ?? 0) + ($rank_div3Stats['wins'] ?? 0)
                                    + ($pve_soloStats['wins'] ?? 0) + ($pve2Stats['wins'] ?? 0)
                                    + ($pve3Stats['wins'] ?? 0) + ($pvp2Stats['wins'] ?? 0)
                                    + ($pvp3Stats['wins'] ?? 0);

                                //calculate total frags
                                $totalFrags = ($pvpStats['frags'] ?? 0) + ($pveStats['frags'] ?? 0)
                                    + ($clubStats['frags'] ?? 0) + ($rankStats['frags'] ?? 0)
                                    + ($rank_div2Stats['frags'] ?? 0) + ($rank_div3Stats['frags'] ?? 0)
                                    + ($pve_soloStats['frags'] ?? 0) + ($pve2Stats['frags'] ?? 0)
                                    + ($pve3Stats['frags'] ?? 0) + ($pvp2Stats['frags'] ?? 0)
                                    + ($pvp3Stats['frags'] ?? 0);

                                $totalXp = ($pvpStats['xp'] ?? 0) + ($pveStats['xp'] ?? 0)
                                    + ($clubStats['xp'] ?? 0) + ($rankStats['xp'] ?? 0)
                                    + ($rank_div2Stats['xp'] ?? 0) + ($rank_div3Stats['xp'] ?? 0)
                                    + ($pve_soloStats['xp'] ?? 0) + ($pve2Stats['xp'] ?? 0)
                                    + ($pve3Stats['xp'] ?? 0) + ($pvp2Stats['xp'] ?? 0)
                                    + ($pvp3Stats['xp'] ?? 0);


                                $totalCapture = ($pvpStats['capture_points'] ?? 0) + ($pveStats['capture_points'] ?? 0)
                                    + ($clubStats['capture_points'] ?? 0) + ($rankStats['capture_points'] ?? 0)
                                    + ($rank_div2Stats['capture_points'] ?? 0) + ($rank_div3Stats['capture_points'] ?? 0)
                                    + ($pve_soloStats['capture_points'] ?? 0) + ($pve2Stats['capture_points'] ?? 0)
                                    + ($pve3Stats['capture_points'] ?? 0) + ($pvp2Stats['capture_points'] ?? 0)
                                    + ($pvp3Stats['capture_points'] ?? 0);

                                $totalDefend = ($pvpStats['dropped_capture_points'] ?? 0) + ($pveStats['dropped_capture_points'] ?? 0)
                                    + ($clubStats['dropped_capture_points'] ?? 0) + ($rankStats['dropped_capture_points'] ?? 0)
                                    + ($rank_div2Stats['dropped_capture_points'] ?? 0) + ($rank_div3Stats['dropped_capture_points'] ?? 0)
                                    + ($pve_soloStats['dropped_capture_points'] ?? 0) + ($pve2Stats['dropped_capture_points'] ?? 0)
                                    + ($pve3Stats['dropped_capture_points'] ?? 0) + ($pvp2Stats['dropped_capture_points'] ?? 0)
                                    + ($pvp3Stats['dropped_capture_points'] ?? 0);

                                $totalSpotted = ($pvpStats['ships_spotted'] ?? 0) + ($pveStats['ships_spotted'] ?? 0)
                                    + ($clubStats['ships_spotted'] ?? 0) + ($rankStats['ships_spotted'] ?? 0)
                                    + ($rank_div2Stats['ships_spotted'] ?? 0) + ($rank_div3Stats['ships_spotted'] ?? 0)
                                    + ($pve_soloStats['ships_spotted'] ?? 0) + ($pve2Stats['ships_spotted'] ?? 0)
                                    + ($pve3Stats['ships_spotted'] ?? 0) + ($pvp2Stats['ships_spotted'] ?? 0)
                                    + ($pvp3Stats['ships_spotted'] ?? 0);

                                // Calculate survival rate
                                $totalSurvivedBattles = ($pvpStats['survived_battles'] ?? 0) + ($pveStats['survived_battles'] ?? 0) + ($clubStats['survived_battles'] ?? 0) + ($rankStats['survived_battles'] ?? 0);
                                $survivalRate = $totalBattles > 0 ? ($totalSurvivedBattles / $totalBattles) * 100 : 0;

                                //wn8
                                $wn8 =  $this->calculateWN8($ship, $totalBattles, $totalFrags, $totalWins, $totalDamageDealt);

                                //total_player_wn8
                                $total_player_wn8 = $this->totalPlayerWN8($playerId);

                                //pr
                                $pr = $this->calculatePR($ship, $totalBattles, $totalFrags, $totalWins, $totalDamageDealt);
                                $pr = $pr != null ? $pr : 0;

                                //total player pr
                                $total_player_pr = $this->totalPlayerPR($playerId);

                                Log::info("Processing ship for player", [
                                    'player_id' => $playerId,
                                    'ship_id' => $ship->ship_id,
                                    'ship_name' => $ship->name,
                                    'ship_nation' => $ship->nation,
                                    'spotted' => $totalSpotted,
                                    'capture' => $totalCapture,
                                    'defend' => $totalDefend,
                                    'xp' => $totalXp,
                                ]);

                                PlayerShip::updateOrCreate(
                                    [
                                        'account_id' => $playerId,
                                        'ship_id' => $shipStats['ship_id']
                                    ],
                                    [
                                        'player_name' => $playerName,
                                        'battles_played' => $totalBattles,
                                        'last_battle_time' => $shipStats['last_battle_time'],
                                        'wins_count' => $totalWins,
                                        'damage_dealt' => $totalDamageDealt,
                                        'average_damage' => $averageDamage,
                                        'frags' => $totalFrags,
                                        'survival_rate' => $survivalRate,
                                        'xp' => $totalXp,
                                        'ship_name' => $shipName,
                                        'ship_type' => $shipType,
                                        'ship_tier' => $shipTier,
                                        'ship_nation' => $shipNation,
                                        'distance' => $shipStats['distance'],
                                        'wn8' => $wn8,
                                        'total_player_wn8' => $total_player_wn8,
                                        'pr' => $pr,
                                        'total_player_pr' => $total_player_pr,
                                        'capture' => $totalCapture,
                                        'defend' => $totalDefend,
                                        'spotted' => $totalSpotted,
                                        // PVE stats
                                        'pve_battles' => $pveStats['battles'] ?? 0,
                                        'pve_wins' => $pveStats['wins'] ?? 0,
                                        'pve_frags' => $pveStats['frags'] ?? 0,
                                        'pve_xp' => $pveStats['xp'] ?? 0,
                                        'pve_survived_battles' => $pveStats['survived_battles'] ?? 0,
                                        // PVP stats
                                        'pvp_battles' => $pvpStats['battles'] ?? 0,
                                        'pvp_wins' => $pvpStats['wins'] ?? 0,
                                        'pvp_frags' => $pvpStats['frags'] ?? 0,
                                        'pvp_xp' => $pvpStats['xp'] ?? 0,
                                        'pvp_survived_battles' => $pvpStats['survived_battles'] ?? 0,
                                        // Club stats
                                        'club_battles' => $clubStats['battles'] ?? 0,
                                        'club_wins' => $clubStats['wins'] ?? 0,
                                        'club_frags' => $clubStats['frags'] ?? 0,
                                        'club_xp' => $clubStats['xp'] ?? 0,
                                        'club_survived_battles' => $clubStats['survived_battles'] ?? 0,
                                        //Rank stats
                                        'rank_battles' => $rankStats['battles'] ?? 0,
                                        'rank_wins' => $rankStats['wins'] ?? 0,
                                        'rank_frags' => $rankStats['frags'] ?? 0,
                                        'rank_xp' => $rankStats['xp'] ?? 0,
                                        'rank_survived_battles' => $rankStats['survived_battles'] ?? 0,
                                    ]
                                );
                            }
                            Log::info("Successfully updated/created player ship record", [
                                'player_id' => $playerId,
                                'ship_id' => $shipStats['ship_id'],
                            ]);
                            $this->totalPlayerWN8($playerId);
                        }
                    } else {
                        Log::error("Failed to fetch player ships", [
                            'account_id' => $playerId,
                            'status' => $response->status(),
                            'response' => $response->body()
                        ]);
                    }
                }
            }


            return true;
        } catch (\Exception $e) {
            Log::error("Error in fetchAndStorePlayerShips", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    //get stats for each player, based on a period: 24, 7, 30, overall

    public function cachePlayerStats()
    {
        $playerIds = PlayerShip::pluck('account_id')->unique()->all();
        foreach ($playerIds as $account_id) {
            $stats24h = $this->getPlayerStatsLastDay($account_id);
            $stats7d = $this->getPlayerStatsLastWeek($account_id);
            $stats30d = $this->getPlayerStatsLastMonth($account_id);

            Cache::put("stats_24h_{$account_id}", $stats24h, now()->addDay());
            Cache::put("stats_7d_{$account_id}", $stats7d, now()->addWeek());
            Cache::put("stats_30d_{$account_id}", $stats30d, now()->addMonth());
        }
    }
    public function getPlayerStatsLastDay($account_id)
    {
        return Cache::remember("stats_24h_{$account_id}", now()->addDay(), function () use ($account_id) {
            $playerStatistics = PlayerShip::select(
                DB::raw('SUM(battles_played) as battles'),
                DB::raw('SUM(wins_count) as wins'),
                DB::raw('AVG(ship_tier) as tier'),
                DB::raw('AVG(survival_rate) as survived'),
                DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(damage_dealt) / SUM(battles_played)) ELSE 0 END as damage'),
                DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(frags) / SUM(battles_played)) ELSE 0 END as frags'),
                DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(xp) / SUM(battles_played)) ELSE 0 END as xp'),
                DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(spotted) / SUM(battles_played)) ELSE 0 END as spotted'),
                DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(capture) / SUM(battles_played)) ELSE 0 END as capture'),
                DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(defend) / SUM(battles_played)) ELSE 0 END as defend'),
                DB::raw('MAX(total_player_wn8) as wn8'),
                DB::raw('MAX(total_player_pr) as pr')
            )
                ->where('account_id', $account_id)
                ->where('last_battle_time', '>=', now()->subDay())

                ->first();


            return $playerStatistics ? $playerStatistics->toArray() : [
                'battles' => '-',
                'wins' => '-',
                'tier' => '-',
                'survived' => '-',
                'damage' => '-',
                'frags' => '-',
                'xp' => '-',
                'spotted' => '-',
                'capture' => '-',
                'defend' => '-',
                'wn8' => '-',
                'pr' => '-'
            ];
        });
    }
    public function getPlayerStatsLastWeek($account_id)
    {
        return Cache::remember("stats_7d_{$account_id}", now()->addWeek(), function () use ($account_id) {

            $playerStatistics = PlayerShip::select(
                DB::raw('SUM(battles_played) as battles'),
                DB::raw('SUM(wins_count) as wins'),
                DB::raw('AVG(ship_tier) as tier'),
                DB::raw('AVG(survival_rate) as survived'),
                DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(damage_dealt) / SUM(battles_played)) ELSE 0 END as damage'),
                DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(frags) / SUM(battles_played)) ELSE 0 END as frags'),
                DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(xp) / SUM(battles_played)) ELSE 0 END as xp'),
                DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(spotted) / SUM(battles_played)) ELSE 0 END as spotted'),
                DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(capture) / SUM(battles_played)) ELSE 0 END as capture'),
                DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(defend) / SUM(battles_played)) ELSE 0 END as defend'),
                DB::raw('MAX(total_player_wn8) as wn8'),
                DB::raw('MAX(total_player_pr) as pr')
            )
                ->where('account_id', $account_id)
                ->where('last_battle_time', '>=', now()->subWeek())
                ->first();


            return $playerStatistics ? $playerStatistics->toArray() : [
                'battles' => '-',
                'wins' => '-',
                'tier' => '-',
                'survived' => '-',
                'damage' => '-',
                'frags' => '-',
                'xp' => '-',
                'spotted' => '-',
                'capture' => '-',
                'defend' => '-',
                'wn8' => '-',
                'pr' => '-'
            ];
        });
    }

    public function getPlayerStatsLastMonth($account_id)
    {
        return Cache::remember("stats_30d_{$account_id}", now()->addMonth(), function () use ($account_id) {
            $playerStatistics = PlayerShip::select(
                DB::raw('SUM(battles_played) as battles'),
                DB::raw('SUM(wins_count) as wins'),
                DB::raw('AVG(ship_tier) as tier'),
                DB::raw('AVG(survival_rate) as survived'),
                DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(damage_dealt) / SUM(battles_played)) ELSE 0 END as damage'),
                DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(frags) / SUM(battles_played)) ELSE 0 END as frags'),
                DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(xp) / SUM(battles_played)) ELSE 0 END as xp'),
                DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(spotted) / SUM(battles_played)) ELSE 0 END as spotted'),
                DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(capture) / SUM(battles_played)) ELSE 0 END as capture'),
                DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(defend) / SUM(battles_played)) ELSE 0 END as defend'),
                DB::raw('MAX(total_player_wn8) as wn8'),
                DB::raw('MAX(total_player_pr) as pr')
            )
                ->where('account_id', $account_id)
                ->where('last_battle_time', '>=', now()->subMonth())
                ->first();


            return $playerStatistics ? $playerStatistics->toArray() : [
                'battles' => '-',
                'wins' => '-',
                'tier' => '-',
                'survived' => '-',
                'damage' => '-',
                'frags' => '-',
                'xp' => '-',
                'spotted' => '-',
                'capture' => '-',
                'defend' => '-',
                'wn8' => '-',
                'pr' => '-'
            ];
        });
    }


    public function getPlayerStatsOverall($account_id)
    {
        $playerStatistics = PlayerShip::select(
            DB::raw('SUM(battles_played) as battles'),
            DB::raw('SUM(wins_count) as wins'),
            DB::raw('AVG(ship_tier) as tier'),
            DB::raw('AVG(survival_rate) as survived'),
            DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(damage_dealt) / SUM(battles_played)) ELSE 0 END as damage'),
            DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(frags) / SUM(battles_played)) ELSE 0 END as frags'),
            DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(xp) / SUM(battles_played)) ELSE 0 END as xp'),
            DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(spotted) / SUM(battles_played)) ELSE 0 END as spotted'),
            DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(capture) / SUM(battles_played)) ELSE 0 END as capture'),
            DB::raw('CASE WHEN SUM(battles_played) > 0 THEN CEIL(SUM(defend) / SUM(battles_played)) ELSE 0 END as defend'),
            DB::raw('MAX(total_player_wn8) as wn8'),
            DB::raw('MAX(total_player_pr) as pr')
        )
            ->where('account_id', $account_id)
            ->first();
        Log::info($playerStatistics);


        return $playerStatistics ? $playerStatistics->toArray() : [
            'battles' => '-',
            'wins' => '-',
            'tier' => '-',
            'survived' => '-',
            'damage' => '-',
            'frags' => '-',
            'xp' => '-',
            'spotted' => '-',
            'capture' => '-',
            'defend' => '-',
            'wn8' => '-',
            'pr' => '-'
        ];
    }

    public function getPlayerVehicleData($account_id, $name)
    {
        $playerVehicles = PlayerShip::select(
            'ship_nation as nation',
            'ship_name as name',
            'ship_tier as tier',
            'battles_played as battles',
            'frags as frags',
            'damage_dealt as damage',
            'wins_count as wins',
            'wn8 as wn8'
        )
            ->where('account_id', $account_id)
            ->where('player_name', $name)
            ->get()
            ->map(function ($vehicle) {
                return [
                    'nation' => $vehicle->nation,
                    'name' => $vehicle->name,
                    'tier' => $vehicle->tier,
                    'battles' => $vehicle->battles,
                    'frags' => $vehicle->frags,
                    'damage' => $vehicle->damage,
                    'wins' => $vehicle->wins,
                    'wn8' => $vehicle->wn8,
                ];
            })
            ->toArray();
        if (!$playerVehicles) {
            Log::warning("Player vehicle info not found", ['account_id' => $account_id, 'name' => $name]);
            return [];
        }

        Log::info("Fetched vehicle for player $account_id", ['player vehicle data: ' => $playerVehicles]);

        return $playerVehicles;
    }
}
