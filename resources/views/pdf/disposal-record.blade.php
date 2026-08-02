@php
    /** @var \App\Models\Disposal\DisposalJob $job */
    /** @var \App\Models\Organization|null $organization */
    /** @var string $hash */
    /** @var string|null $signatureDataUri */
    /** @var \Illuminate\Support\Carbon $generatedAt */
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ __('disposal.pdf.title') }} {{ $job->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #111; }
        h1 { font-size: 18pt; margin: 0 0 2mm 0; }
        h2 { font-size: 13pt; margin: 6mm 0 2mm 0; border-bottom: 1px solid #888; }
        h3 { font-size: 11pt; margin: 4mm 0 1mm 0; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        .meta th { text-align: left; width: 40mm; padding: 1mm 2mm; background: #f3f3f3; }
        .meta td { padding: 1mm 2mm; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 2mm; }
        table.items th, table.items td { border: 1px solid #ccc; padding: 1.5mm; vertical-align: top; font-size: 10pt; }
        table.items th { background: #eee; text-align: left; }
        .right { text-align: right; }
        .hazard { font-weight: bold; }
        .muted { color: #555; font-size: 9pt; }
        .sig-table { width: 100%; border-collapse: collapse; margin-top: 4mm; }
        .sig-table td { width: 50%; padding: 2mm; border: 1px solid #888; vertical-align: top; }
        .sig-table img { max-height: 25mm; }
        .footer { position: fixed; bottom: 8mm; left: 8mm; right: 8mm; font-size: 8pt; color: #555; border-top: 1px solid #ccc; padding-top: 1mm; }
    </style>
</head>
<body>
<h1>{{ __('disposal.pdf.title') }}</h1>
<div class="muted">{{ $organization?->name }}</div>

<table class="meta">
    <tr><th>{{ __('disposal.pdf.number') }}</th><td>{{ $job->number }}</td></tr>
    <tr><th>{{ __('disposal.pdf.customer') }}</th><td>{{ $job->customer->name ?? '—' }}@if ($job->site !== null) — {{ $job->site->name }}@endif</td></tr>
    <tr><th>{{ __('disposal.pdf.picked_up_on') }}</th><td>{{ $job->picked_up_on?->fdate() ?? '—' }}</td></tr>
    <tr><th>{{ __('disposal.pdf.responsible') }}</th><td>{{ $job->responsible->name ?? ($job->creator->name ?? '—') }}</td></tr>
    <tr><th>{{ __('disposal.pdf.status') }}</th><td>{{ $job->status->label() }}</td></tr>
    @if ($job->total_weight_kg !== null)
        <tr><th>{{ __('disposal.pdf.total_weight') }}</th><td>{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $job->total_weight_kg, 3, withThousandsSeparator: true) }} kg</td></tr>
    @endif
</table>

<h2>{{ __('disposal.pdf.items') }}</h2>
<table class="items">
    <thead>
        <tr>
            <th style="width: 6mm">#</th>
            <th>{{ __('disposal.pdf.col.category') }}</th>
            <th>{{ __('disposal.pdf.col.device') }}</th>
            <th>{{ __('disposal.pdf.col.serial') }}</th>
            <th class="right">{{ __('disposal.pdf.col.quantity') }}</th>
            <th class="right">{{ __('disposal.pdf.col.weight') }}</th>
            <th>{{ __('disposal.pdf.col.avv') }}</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($job->items as $idx => $item)
        <tr>
            <td>{{ $idx + 1 }}</td>
            <td>{{ $item->category }}</td>
            <td>{{ trim(($item->manufacturer ?? '') . ' ' . ($item->model ?? '')) ?: '—' }}</td>
            <td>{{ $item->serial_number ?? '—' }}</td>
            <td class="right">{{ $item->quantity }}</td>
            <td class="right">{{ $item->weight_kg !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $item->weight_kg, 3, withThousandsSeparator: true) : '—' }}</td>
            <td>
                @if ($item->is_hazardous)
                    <span class="hazard">{{ $item->avv_code }}</span> {{ __('disposal.pdf.hazardous_suffix') }}
                @else
                    {{ $item->avv_code }}
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

@if ($job->items->contains(fn ($item) => $item->treatments->isNotEmpty()))
    <h2>{{ __('disposal.pdf.treatments') }}</h2>
    @foreach ($job->items as $item)
        @if ($item->treatments->isNotEmpty())
            <h3>{{ $item->category }}@if ($item->serial_number !== null) ({{ $item->serial_number }})@endif</h3>
            <table class="items">
                <thead>
                    <tr>
                        <th>{{ __('disposal.pdf.col.media_type') }}</th>
                        <th>{{ __('disposal.pdf.col.method') }}</th>
                        <th>{{ __('disposal.pdf.col.din') }}</th>
                        <th>{{ __('disposal.pdf.col.protection_class') }}</th>
                        <th>{{ __('disposal.pdf.col.treated_at') }}</th>
                        <th>{{ __('disposal.pdf.col.performed_by') }}</th>
                        <th>{{ __('disposal.pdf.col.evidence') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($item->treatments as $treatment)
                    <tr>
                        <td>{{ $treatment->media_type->label() }}</td>
                        <td>{{ $treatment->method->label() }}</td>
                        <td>{{ $treatment->dinLevel() }}</td>
                        <td>{{ $treatment->protection_class ?? '—' }}</td>
                        <td>{{ $treatment->treated_at->fdatetime() }}</td>
                        <td>{{ $treatment->performer->name ?? '—' }}</td>
                        <td>{{ $treatment->evidence_reference ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
@endif

@if ($job->handovers->isNotEmpty())
    <h2>{{ __('disposal.pdf.handovers') }}</h2>
    <table class="items">
        <thead>
            <tr>
                <th>{{ __('disposal.pdf.col.disposer') }}</th>
                <th>{{ __('disposal.pdf.col.proof_type') }}</th>
                <th>{{ __('disposal.pdf.col.document_number') }}</th>
                <th>{{ __('disposal.pdf.col.handed_over_on') }}</th>
                <th>{{ __('disposal.pdf.col.certificate') }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($job->handovers as $handover)
            <tr>
                <td>{{ $handover->disposer->name ?? '—' }}</td>
                <td>{{ $handover->proof_type->label() }}</td>
                <td>{{ $handover->document_number }}</td>
                <td>{{ $handover->handed_over_on->fdate() }}</td>
                <td>{{ $handover->certificate_reference ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<h2>{{ __('disposal.pdf.confirmation') }}</h2>
<table class="sig-table">
    <tr>
        <td>
            <strong>{{ __('disposal.pdf.customer_signature') }}</strong><br>
            @if ($signatureDataUri !== null)
                <img src="{{ $signatureDataUri }}" alt="{{ __('disposal.pdf.customer_signature') }}"><br>
            @endif
            @if ($job->signer_name !== null)
                {{ $job->signer_name }}@if ($job->signed_at !== null) — {{ $job->signed_at->fdatetime() }}@endif
            @else
                <span class="muted">{{ __('disposal.pdf.not_signed') }}</span>
            @endif
        </td>
        <td>
            <strong>{{ __('disposal.pdf.provider') }}</strong><br>
            {{ $organization?->name }}<br>
            {{ $job->responsible->name ?? ($job->creator->name ?? '—') }}
            @if ($job->completed_at !== null)
                <br><span class="muted">{{ __('disposal.pdf.completed_at') }}: {{ $job->completed_at->fdatetime() }}</span>
            @endif
        </td>
    </tr>
</table>

{{-- Seitenzahl kommt vom DocumentDesignRenderer (dd-pagenum), kein eigener Zähler. --}}
<div class="footer">
    {{ __('disposal.pdf.footer.hash') }}: {{ $hash }}<br>
    {{ __('disposal.pdf.footer.generated', ['at' => $generatedAt->fdatetime()]) }}
</div>
</body>
</html>
