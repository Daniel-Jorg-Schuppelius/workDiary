@php
    /** @var \App\Models\Protocol $protocol */
    /** @var string $hash */
    /** @var \Illuminate\Support\Carbon $generatedAt */
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ __('protocol.pdf.title', ['title' => $protocol->title]) }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #111; }
        h1 { font-size: 18pt; margin: 0 0 6mm 0; }
        h2 { font-size: 13pt; margin: 6mm 0 2mm 0; border-bottom: 1px solid #888; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        .meta th { text-align: left; width: 30mm; padding: 1mm 2mm; background: #f3f3f3; }
        .meta td { padding: 1mm 2mm; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 2mm; }
        table.items th, table.items td { border: 1px solid #ccc; padding: 1.5mm; vertical-align: top; font-size: 10pt; }
        table.items th { background: #eee; text-align: left; }
        .signature { margin-top: 4mm; padding: 2mm; border: 1px solid #888; }
        .footer { position: fixed; bottom: 8mm; left: 8mm; right: 8mm; font-size: 8pt; color: #555; border-top: 1px solid #ccc; padding-top: 1mm; }
        .badge { display: inline-block; padding: 0 1.5mm; border-radius: 1mm; font-size: 9pt; }
        .ok { background: #d4edda; }
        .notok { background: #f8d7da; }
        .n_a { background: #e2e3e5; }
        .open { background: #fff3cd; }
    </style>
</head>
<body>
<h1>{{ $protocol->title }}</h1>

<table class="meta">
    <tr><th>{{ __('protocol.field.type') }}</th><td>{{ $protocol->type->label() }}</td></tr>
    <tr><th>{{ __('protocol.field.occurredAt') }}</th><td>{{ $protocol->occurred_at?->fdatetime() }}</td></tr>
    <tr><th>{{ __('protocol.field.revision') }}</th><td>{{ $protocol->revision }}</td></tr>
    <tr><th>{{ __('protocol.field.status') }}</th><td>{{ $protocol->status->label() }}</td></tr>
    <tr><th>{{ __('protocol.field.createdBy') }}</th><td>{{ $protocol->creator?->name }}</td></tr>
    @if ($protocol->weatherSnapshot)
        @php($w = $protocol->weatherSnapshot)
        <tr><th>{{ __('weather.block.title') }}</th><td>{{ $w->temp_min }}–{{ $w->temp_max }} °C · {{ __('weather.precipitation') }} {{ $w->precipitation_mm }} mm · {{ __('weather.wind') }} {{ $w->wind_gust_kmh }} km/h <span style="color:#666">({{ __('weather.source') }}: {{ \App\Support\Trans::or('weather.providers.' . $w->provider, $w->provider) }}, {{ $w->fetched_at?->format('d.m.Y H:i') }})</span></td></tr>
    @endif
</table>

@if ($protocol->description)
    <h2>{{ __('protocol.field.description') }}</h2>
    <p>{{ $protocol->description }}</p>
@endif

@if ($protocol->state_initial || $protocol->state_final)
    <h2>{{ __('protocol.pdf.state') }}</h2>
    <table class="meta">
        @if ($protocol->state_initial)<tr><th>{{ __('protocol.field.stateInitial') }}</th><td>{{ $protocol->state_initial }}</td></tr>@endif
        @if ($protocol->state_final)<tr><th>{{ __('protocol.field.stateFinal') }}</th><td>{{ $protocol->state_final }}</td></tr>@endif
    </table>
@endif

<h2>{{ __('protocol.pdf.items') }}</h2>
<table class="items">
    <thead>
        <tr>
            <th style="width: 6mm">#</th>
            <th>{{ __('protocol.pdf.col.label') }}</th>
            <th>{{ __('protocol.pdf.col.type') }}</th>
            <th>{{ __('protocol.pdf.col.value') }}</th>
            <th>{{ __('protocol.pdf.col.result') }}</th>
            <th>{{ __('protocol.pdf.col.note') }}</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($protocol->items->sortBy('sort_order') as $idx => $item)
        <tr>
            <td>{{ $idx + 1 }}</td>
            <td><strong>{{ $item->label }}</strong>@if ($item->description)<br><small>{{ $item->description }}</small>@endif</td>
            <td>{{ $item->item_type->label() }}</td>
            <td><code>{{ json_encode($item->value_json, JSON_UNESCAPED_UNICODE) }}</code></td>
            <td>@if ($item->result)<span class="badge {{ $item->result->value }}">{{ $item->result->label() }}</span>@endif</td>
            <td>{{ $item->note }}</td>
        </tr>
        {{-- Vollaudit 2026-07 (H7): max-4-Vorschau je Punkt mit Phase + Caption
             (protokoll-fotos.md §3.2, Akzeptanzkriterium 6); Rest als „+n weitere".
             Daten kommen fertig aus dem Renderer ($itemPhotoPreviews). --}}
        @if (! empty($itemPhotoPreviews[$item->id]['previews']))
            <tr>
                <td></td>
                <td colspan="5">
                    @foreach ($itemPhotoPreviews[$item->id]['previews'] as $preview)
                        <div style="display: inline-block; width: 34mm; margin: 0 2mm 2mm 0; vertical-align: top;">
                            {!! $preview['src'] ? '<img src="' . e($preview['src']) . '" style="width: 34mm; max-height: 26mm; object-fit: cover;">' : '' !!}
                            <div style="font-size: 8pt; color: #444;">
                                {{ $preview['phase'] }}{{ $preview['caption'] ? ': „' . $preview['caption'] . '“' : '' }}
                            </div>
                        </div>
                    @endforeach
                    @if ($itemPhotoPreviews[$item->id]['more'] > 0)
                        <div style="font-size: 8pt; color: #666;">{{ __('protocol.pdf.photos.more', ['count' => $itemPhotoPreviews[$item->id]['more']]) }}</div>
                    @endif
                </td>
            </tr>
        @endif
    @endforeach
    </tbody>
</table>

@if ($protocol->signatures->isNotEmpty())
    <h2>{{ __('protocol.pdf.signatures') }}</h2>
    @foreach ($protocol->signatures as $sig)
        <div class="signature">
            <strong>{{ $sig->signer_name }}</strong> — {{ $sig->role->label() }}<br>
            {{ $sig->method->label() }} • {{ $sig->signed_at->fdatetime() }}
            @if ($sig->ip)<br><small>{{ $sig->ip?->getValue() }}</small>@endif
        </div>
    @endforeach
@endif

<div class="footer">
    {{ __('protocol.pdf.footer.hash') }}: {{ $hash }}<br>
    {{ __('protocol.pdf.footer.generated', ['at' => $generatedAt->fdatetime()]) }}
</div>
</body>
</html>
