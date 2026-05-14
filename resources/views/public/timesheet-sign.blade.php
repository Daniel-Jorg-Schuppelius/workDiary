<!doctype html>
<html lang="de" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('Stundenzettel signieren') }}</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200">
<main class="mx-auto max-w-3xl p-4">
    <div class="mb-4 rounded-box bg-base-100 p-4 shadow">
        <h1 class="font-['Space_Grotesk'] text-xl font-semibold">{{ __('Stundenzettel signieren') }}</h1>
        <div class="mt-1 text-sm text-base-content/70">
            {{ $timesheet->project?->name }} · {{ optional($timesheet->work_date)->format('d.m.Y') }}
        </div>
    </div>

    <div class="mb-4 rounded-box bg-base-100 p-4 shadow">
        <table class="table table-sm">
            <thead><tr><th>Start</th><th>Ende</th><th class="text-right">Dauer (min)</th><th>Art</th><th>Beschr.</th></tr></thead>
            <tbody>
                @foreach($timesheet->entries as $e)
                    <tr>
                        <td>{{ optional($e->started_at)->format('H:i') }}</td>
                        <td>{{ optional($e->ended_at)->format('H:i') }}</td>
                        <td class="text-right">{{ (int)$e->minutes }}</td>
                        <td>{{ $e->kind }}</td>
                        <td>{{ $e->description }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if($timesheet->materialUsages->isNotEmpty())
            <h3 class="mt-3 text-sm font-semibold">{{ __('Material') }}</h3>
            <table class="table table-sm">
                <thead><tr><th>Bez.</th><th class="text-right">Menge</th><th>Einheit</th><th class="text-right">Summe netto</th></tr></thead>
                <tbody>
                    @foreach($timesheet->materialUsages as $u)
                        <tr>
                            <td>{{ $u->description }}</td>
                            <td class="text-right">{{ rtrim(rtrim(number_format((float)$u->quantity, 3, ',', '.'), '0'), ',') }}</td>
                            <td>{{ $u->unit }}</td>
                            <td class="text-right">{{ number_format((float)$u->line_total_net, 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <form method="POST" action="{{ route('timesheets.public-sign.submit', $token) }}"
          class="rounded-box bg-base-100 p-4 shadow">
        @include('timesheets._signature_pad', ['action' => route('timesheets.public-sign.submit', $token), 'timesheet' => $timesheet])
    </form>
</main>
</body>
</html>
