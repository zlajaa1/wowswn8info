<?php

namespace App\Services;

use App\Models\Clan;
use App\Models\ClanMember;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClanMemberService
{
    protected $clansUrl = "https://api.worldofwarships.eu/wows/clans/info/";

    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.wargaming.api_key');
    }

    public function fetchAndStoreClanMembers()
    {

        Log::info('fetchClanMembers method called');

        try {
            ClanMember::truncate();

            $clanIds = Clan::pluck('clan_id')->all();
            Log::info("Starting clan members update process", [
                'total_clans' => count($clanIds)
            ]);

            foreach ($clanIds as $clanId) {
                Log::info("Fetching data for clan", ['clan_id' => $clanId]);

                // Get clan info and member details including clan_name
                $clanResponse = Http::get($this->clansUrl, [
                    'application_id' => $this->apiKey,
                    'clan_id' => $clanId,
                    'extra' => 'members'
                ]);

                if ($clanResponse->successful()) {
                    $clanData = $clanResponse->json();

                    if (isset($clanData['data'][$clanId])) {
                        $clanInfo = $clanData['data'][$clanId];
                        $clanName = $clanInfo['name'];
                        $members = $clanInfo['members'] ?? []; // Default to empty array if `members` is null

                        Log::info("Found members in clan", [
                            'clan_id' => $clanId,
                            'member_count' => is_array($members) ? count($members) : 0
                        ]);

                        if (is_array($members) && count($members) > 0) {
                            foreach ($members as $memberId => $player) {
                                try {
                                    ClanMember::updateOrCreate(
                                        ['account_id' => $player['account_id']],
                                        [
                                            'account_name' => $player['account_name'],
                                            'clan_id' => $clanId,
                                            'clan_name' => $clanName,
                                            'joined_at' => now()->setTimestamp($player['joined_at']),
                                            'role' => $player['role']
                                        ]
                                    );

                                    Log::info("Updated/Created clan member", [
                                        'account_id' => $player['account_id'],
                                        'account_name' => $player['account_name'],
                                        'clan_id' => $clanId,
                                        'clan_name' => $clanName
                                    ]);
                                } catch (\Exception $e) {
                                    Log::error("Error saving clan member", [
                                        'account_id' => $player['account_id'],
                                        'clan_id' => $clanId,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                            }
                        } else {
                            Log::info("No members found for this clan", ['clan_id' => $clanId]);
                        }
                    } else {
                        Log::warning("No valid data found for clan", [
                            'clan_id' => $clanId,
                            'response_data' => $clanData
                        ]);
                    }
                } else {
                    Log::error("Failed to fetch clan data", [
                        'clan_id' => $clanId,
                        'status' => $clanResponse->status(),
                        'response' => $clanResponse->json()
                    ]);
                }
            }

            Log::info("Completed clan members update process");
        } catch (\Exception $e) {
            Log::error("Critical error in fetchAndStoreClanMembers", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
