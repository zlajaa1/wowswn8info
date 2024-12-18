@extends('layout.layout')

@section('title', 'Home Page')

@section('content')
  <iframe src="https://api.wn8.info/tools/wows/twitchlive.php" title="description"></iframe>
  <div>
    <table>
      <thead>
        <tr class="bg-gray-100 text-left">
          <th class="border-b">Player</th>
          <th class="border-b">WN8</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($statistics['topPlayersLast24Hours'] as $player)
          <tr class="border-b">
            <td class="py-2 px-4">{{ $player['name'] }}</td>
            <td class="py-2 px-4">{{ $player['wn8'] }}</td>  
          </tr>
        @endforeach
      </tbody>
    </table>
    <table>
      <thead>
        <tr class="bg-gray-100 text-left">
          <th class="border-b">Player</th>
          <th class="border-b">WN8</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($statistics['topPlayersLastSevenDays'] as $player)
          <tr class="border-b">
            <td class="py-2 px-4">{{ $player['name'] }}</td>
            <td class="py-2 px-4">{{ $player['wn8'] }}</td>  
          </tr>
        @endforeach
      </tbody>
    </table>
    <table>
      <thead>
        <tr class="bg-gray-100 text-left">
          <th class="border-b">Player</th>
          <th class="border-b">WN8</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($statistics['topPlayersLastMonth'] as $player)
          <tr class="border-b">
            <td class="py-2 px-4">{{ $player['name'] }}</td>
            <td class="py-2 px-4">{{ $player['wn8'] }}</td>  
          </tr>
        @endforeach
      </tbody>
    </table>
    <table>
      <thead>
        <tr class="bg-gray-100 text-left">
          <th class="border-b">Player</th>
          <th class="border-b">WN8</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($statistics['topPlayersOverall'] as $player)
          <tr class="border-b">
            <td class="py-2 px-4">{{ $player['name'] }}</td>
            <td class="py-2 px-4">{{ $player['wn8'] }}</td>  
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <iframe src="https://api.wn8.info/tools/wows/ytvids.php" title="description"></iframe>
@endsection