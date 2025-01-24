@php
  use App\Helpers\FrontendHelper;
@endphp
@extends('layout.layout')

@section('metaTitle', $metaSite['metaTitle'])
@section('metaDescription', $metaSite['metaDescription'])
@section('metaKeywords', $metaSite['metaKeywords'])

@section('content')
    <div class="page-padding">
        <!-- Player info -->
        <p class="player-title">{{ $playerInfo['name'] }}
        @if ($playerInfo['clanName'] !== '')
            <p>
                <a class="pointer gray-link" href="{{ route('clan.page', [
                    'name' => urlencode($playerInfo['clanName']),
                    'id' => $playerInfo['clanId']
                ]) }}">{{ $playerInfo['clanName'] }}</a>
            </p>
        @endif
        </p>
        <p class="player-info">Created at: {{ $playerInfo['createdAt'] }}</p>
        <!-- ### Player info -->
        <!-- Player statistics -->
        <!-- <div v-if="playerStatistics === null">Loading</div> -->
        <div class="shadow4 mb-40">
            <table class="table table-striped table-bordered customRedefine playerTable">
                <thead>
                    <tr class="bg-gray-100 text-left">
                        <th class="border-b">Stats</th>
                        <th class="border-b">Overall</th>
                        <th class="border-b">Last Day</th>
                        <th class="border-b">Last 7 days</th>
                        <th class="border-b">Last month</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b">
                        <td class="py-2 px-4">Battles</td>
                        <td class="py-2 px-4">{{ $playerStatistics['overall']['battles'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastDay']['battles'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastWeek']['battles'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastMonth']['battles'] }}</td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-2 px-4">Wins</td>
                        <td class="py-2 px-4">{{ $playerStatistics['overall']['wins'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastDay']['wins'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastWeek']['wins'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastMonth']['wins'] }}</td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-2 px-4">Tier Ø</td>
                        <td class="py-2 px-4">{{ $playerStatistics['overall']['tier'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastDay']['tier'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastWeek']['tier'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastMonth']['tier'] }}</td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-2 px-4">Survived</td>
                        <td class="py-2 px-4">{{ $playerStatistics['overall']['survived'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastDay']['survived'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastWeek']['survived'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastMonth']['survived'] }}</td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-2 px-4">Damage Ø</td>
                        <td class="py-2 px-4">{{ $playerStatistics['overall']['damage'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastDay']['damage'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastWeek']['damage'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastMonth']['damage'] }}</td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-2 px-4">Frags Ø</td>
                        <td class="py-2 px-4">{{ $playerStatistics['overall']['frags'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastDay']['frags'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastWeek']['frags'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastMonth']['frags'] }}</td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-2 px-4">Spotted Ø</td>
                        <td class="py-2 px-4">{{ $playerStatistics['overall']['spotted'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastDay']['spotted'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastWeek']['spotted'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastMonth']['spotted'] }}</td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-2 px-4">Experience Ø</td>
                        <td class="py-2 px-4">{{ $playerStatistics['overall']['xp'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastDay']['xp'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastWeek']['xp'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastMonth']['xp'] }}</td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-2 px-4">Capture Ø</td>
                        <td class="py-2 px-4">{{ $playerStatistics['overall']['capture'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastDay']['capture'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastWeek']['capture'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastMonth']['capture'] }}</td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-2 px-4">Defend Ø</td>
                        <td class="py-2 px-4">{{ $playerStatistics['overall']['defend'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastDay']['defend'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastWeek']['defend'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastMonth']['defend'] }}</td>
                    </tr>
                    {{-- <tr class="border-b">
                        <td class="py-2 px-4">PR</td>
                        <td class="py-2 px-4">{{ $playerStatistics['overall']['pr'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastDay']['pr'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastWeek']['pr'] }}</td>
                        <td class="py-2 px-4">{{ $playerStatistics['lastMonth']['pr'] }}</td>
                    </tr> --}}
                    <tr class="border-b">
                        <td class="py-2 px-4">WN8</td>
                        <td class="py-2 px-4 {{ 'table-' . FrontendHelper::getWN8Color($playerStatistics['overall']['wn8']) }}">{{ $playerStatistics['overall']['wn8'] }}</td>
                        <td class="py-2 px-4 {{ 'table-' . FrontendHelper::getWN8Color($playerStatistics['lastDay']['wn8']) }}">{{ $playerStatistics['lastDay']['wn8'] }}</td>
                        <td class="py-2 px-4 {{ 'table-' . FrontendHelper::getWN8Color($playerStatistics['lastWeek']['wn8']) }}">{{ $playerStatistics['lastWeek']['wn8'] }}</td>
                        <td class="py-2 px-4 {{ 'table-' . FrontendHelper::getWN8Color($playerStatistics['lastMonth']['wn8']) }}">{{ $playerStatistics['lastMonth']['wn8'] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <!-- ### Player statistics -->
        <!-- Player vehicles -->
        <!-- <div v-if="playerVehicles.length === 0">Loading</div> -->
        <div class="shadow4 table-container">
            <table class="table table-striped table-bordered customRedefine playerTable">
                <thead>
                    <tr class="bg-gray-100 text-left">
                        <th class="border-b">Nation</th>
                        <th class="border-b">Name</th>
                        <th class="border-b">Tier</th>
                        <th class="border-b">Battles</th>
                        <th class="border-b">Frags Ø</th>
                        <th class="border-b">Damage Ø</th>
                        <th class="border-b">Wins</th>
                        <th class="border-b">WN8</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($playerVehicles as $vehicle)
                        <tr class="border-b">
                            <td class="py-2 px-4">{{ $vehicle['nation'] }}</td>
                            <td class="py-2 px-4">{{ $vehicle['name'] }}</td>
                            <td class="py-2 px-4">{{ $vehicle['tier'] }}</td>
                            <td class="py-2 px-4">{{ $vehicle['battles'] }}</td>
                            <td class="py-2 px-4">{{ $vehicle['frags'] }}</td>
                            <td class="py-2 px-4">{{ $vehicle['damage'] }}</td>
                            <td class="py-2 px-4">{{ $vehicle['wins'] }}</td>
                            <td class="py-2 px-4 {{ 'table-' . FrontendHelper::getWN8Color($vehicle['wn8']) }}">{{ $vehicle['wn8'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <!-- ### Player vehicles -->
    </div>
@endsection
