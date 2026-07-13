<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ __('VVT') }} – {{ $snapshot['organization'] ?? '' }}</title>
    <style>
        body { font-family: 'IBM Plex Sans', system-ui, sans-serif; color: #111; margin: 2rem; }
        h1 { font-size: 1.3rem; } h2 { font-size: 1rem; margin: 1.2rem 0 .3rem; border-bottom: 1px solid #ccc; }
        .meta { color: #555; font-size: .8rem; margin-bottom: 1rem; }
        .act { page-break-inside: avoid; margin-bottom: 1rem; }
        .k { font-weight: 600; } .row { font-size: .85rem; margin: .1rem 0; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
    <button class="noprint" data-print>{{ __('Drucken') }}</button>
    <h1>{{ __('Verzeichnis von Verarbeitungstätigkeiten') }}</h1>
    <p class="meta">{{ $snapshot['organization'] ?? '' }} · {{ __('Stand') }}: {{ \Illuminate\Support\Carbon::parse($snapshot['generated_at'] ?? now())->format('d.m.Y H:i') }}</p>

    @forelse ($snapshot['activities'] ?? [] as $a)
        <div class="act">
            <h2>{{ $a['name'] }}</h2>
            <div class="row"><span class="k">{{ __('Zweck') }}:</span> {{ $a['purpose'] ?? '—' }}</div>
            <div class="row"><span class="k">{{ __('Rolle') }}:</span> {{ $a['controller_role'] }} · <span class="k">{{ __('Status') }}:</span> {{ $a['status'] }} · <span class="k">{{ __('Review') }}:</span> {{ $a['review_due_at'] ?? '—' }}</div>
            @php $p = $a['current_version']['payload'] ?? []; @endphp
            @if ($p)
                <div class="row"><span class="k">{{ __('Datenkategorien') }}:</span> {{ $p['data_categories'] ?? '—' }}</div>
                <div class="row"><span class="k">{{ __('Rechtsgrundlagen') }}:</span> {{ $p['legal_basis'] ?? '—' }}</div>
                <div class="row"><span class="k">{{ __('Empfänger') }}:</span> {{ $p['recipients'] ?? '—' }}</div>
                <div class="row"><span class="k">{{ __('Drittland') }}:</span> {{ $p['transfers'] ?? '—' }}</div>
                <div class="row"><span class="k">{{ __('Aufbewahrung') }}:</span> {{ $p['retention'] ?? '—' }}</div>
                <div class="row"><span class="k">{{ __('TOM') }}:</span> {{ $p['tom'] ?? '—' }}</div>
                @if (! empty($p['tom_snapshot']))
                    <div class="row"><span class="k">{{ __('TOM (Snapshot)') }}:</span> {{ collect($p['tom_snapshot'])->pluck('name')->implode(', ') }}</div>
                @endif
            @endif
        </div>
    @empty
        <p>{{ __('Keine Verarbeitungstätigkeiten.') }}</p>
    @endforelse
@include('partials.print-script')
</body>
</html>
