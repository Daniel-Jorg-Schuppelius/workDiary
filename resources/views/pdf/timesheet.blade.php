<x-pdf-layout pdf-type="timesheet" :pdf-title="__('pdf.timesheet.title_with_id', ['id' => $timesheet->id])">
    @push('pdf-styles')
        <style>
            .sig    { margin-top: 18pt; }
            .sig .sig-box {
                display: inline-block;
                max-width: 94%;
                border: 1px solid #ddd;
                padding: 4pt;
                background: #fff;
                box-sizing: border-box;
            }
            .sig img {
                display: block;
                max-width: 100%;
                width: auto;
                height: auto;
                max-height: 80pt;
                border: 0;
            }
            .grid2  { width: 100%; table-layout: fixed; }
            .grid2 td {
                width: 50%;
                border: 0;
                padding: 0 4pt 0 0;
                vertical-align: top;
                overflow: hidden;
            }
            .sig .small { word-break: break-word; overflow-wrap: anywhere; }
        </style>
    @endpush

    <h1>{{ __('pdf.timesheet.title') }}</h1>
    <div class="meta">
        {{ __('timesheet.fields.date') }}: <strong>{{ optional($timesheet->work_date)->fdate() }}</strong> ·
        {{ __('timesheet.fields.project') }}: <strong>{{ $timesheet->project?->name }}</strong> ·
        {{ __('timesheet.fields.user') }}: <strong>{{ $timesheet->user?->name }}</strong> ·
        {{ __('timesheet.fields.status') }}: {{ $timesheet->statusLabel() }}
    </div>

    <h2>{{ __('timesheet.sections.entries') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('timesheet.fields.started_at') }}</th><th>{{ __('timesheet.fields.ended_at') }}</th><th class="right">{{ __('timesheet.fields.break_minutes') }}</th>
                <th class="right">{{ __('timesheet.fields.duration') }}</th><th>{{ __('timesheet.fields.kind') }}</th><th>{{ __('timesheet.fields.description') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($timesheet->entries as $e)
                <tr>
                    <td>{{ $e->started_at?->ftime() }}</td>
                    <td>{{ $e->ended_at?->ftime() }}</td>
                    <td class="right">{{ (int) $e->break_minutes }}</td>
                    <td class="right">{{ \App\Support\Formats::duration((int) $e->minutes, 'clock', withUnit: false) }}</td>
                    <td>{{ $e->kind?->label() ?? '' }}</td>
                    <td>{{ $e->description }}</td>
                </tr>
            @empty
                <tr><td colspan="6">—</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="right">{{ __('timesheet.totals.work') }}:</td><td class="right" style="width:80pt;"><strong>{{ \App\Support\Formats::duration((int) $timesheet->total_work_minutes, 'clock') }}</strong></td></tr>
        <tr><td class="right">{{ __('timesheet.totals.break') }}:</td><td class="right">{{ (int) $timesheet->total_break_minutes }} min</td></tr>
    </table>

    <h2>{{ __('timesheet.sections.materials') }}</h2>
    <table>
        <thead>
            <tr>
                <th>Bezeichnung</th><th class="right">Menge</th><th>Einheit</th>
                <th class="right">EP netto</th><th class="right">Summe netto</th>
            </tr>
        </thead>
        <tbody>
            @forelse($timesheet->materialUsages as $u)
                <tr>
                    <td>{{ $u->description }}</td>
                    <td class="right">{{ rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($u->quantity?->getValue()->toFloat() ?? 0.0), 3, withThousandsSeparator: true), '0'), ',') }}</td>
                    <td>{{ $u->unit }}</td>
                    <td class="right">{{ $u->unit_price !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($u->unit_price?->toFloat() ?? 0.0), 4, withThousandsSeparator: true).' €' : '—' }}</td>
                    <td class="right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($u->line_total_net?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }} €</td>
                </tr>
            @empty
                <tr><td colspan="5">—</td></tr>
            @endforelse
        </tbody>
    </table>
    <table class="totals">
        <tr><td class="right">{{ __('timesheet.totals.material_net') }}:</td><td class="right" style="width:80pt;"><strong>{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float)$timesheet->total_material_net, 2, withThousandsSeparator: true) }} €</strong></td></tr>
    </table>

    <div class="sig">
        <h2>{{ __('timesheet.sections.customer_release') }}</h2>
        <table class="grid2">
            <tr>
                <td>
                    <div><strong>{{ $timesheet->customer_name ?: '—' }}</strong>
                        @if($timesheet->customer_role) ({{ $timesheet->customer_role }}) @endif
                    </div>
                    @if($timesheet->customer_email)<div>{{ $timesheet->customer_email }}</div>@endif
                    @if($timesheet->signed_at)
                        <div class="small">{{ __('timesheet.signature.signed_at', ['datetime' => $timesheet->signed_at->fdatetime()]) }}
                            @if($timesheet->signed_ip) · {{ __('timesheet.signature.ip', ['ip' => $timesheet->signed_ip]) }} @endif
                        </div>
                        <div class="small">{{ __('timesheet.signature.hash', ['hash' => $timesheet->signature_hash]) }}</div>
                    @endif
                </td>
                <td>
                    @if(! empty($signaturePng))
                        <span class="sig-box"><img src="{{ $signaturePng }}" alt="signature"></span>
                    @else
                        <div class="small">{{ __('timesheet.signature.none') }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    @if($timesheet->notes)
        <h2>{{ __('timesheet.sections.notes') }}</h2>
        <p>{!! nl2br(e($timesheet->notes)) !!}</p>
    @endif
</x-pdf-layout>
