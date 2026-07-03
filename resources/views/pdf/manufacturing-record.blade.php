<x-pdf-layout pdf-type="manufacturing_record" :pdf-title="__('manufacturing.record.title') . ' ' . $number">
    <h1>{{ __('manufacturing.record.title') }} {{ $number }}</h1>
    <div class="meta">
        {{ __('manufacturing.delivery_note.order') }}: <strong>{{ $order->number ?? '—' }}</strong>
        · {{ __('manufacturing.record.generated_at') }}: <strong>{{ $generatedAt->format('d.m.Y H:i') }}</strong>
        · {{ __('Status') }}: <strong>{{ $order->status->label() }}</strong>
    </div>

    <table class="grid2" style="margin-top: 10pt;">
        <tr>
            <td>
                <strong>{{ __('manufacturing.order.field.article') }}</strong><br>
                {{ $order->article?->name }}
                @if ($order->variant)
                    <br>{{ $order->variant->name ?? $order->variant->option_signature }}
                @endif
            </td>
            <td>
                <strong>{{ __('manufacturing.delivery_note.warehouse') }}</strong><br>
                {{ $order->warehouse?->name ?? '—' }}
            </td>
        </tr>
    </table>

    <h2>{{ __('manufacturing.planning.quality') }}</h2>
    <table>
        <thead>
            <tr>
                <th class="right">{{ __('manufacturing.order.field.target_qty') }}</th>
                <th class="right">{{ __('manufacturing.order.field.produced') }}</th>
                <th class="right">{{ __('manufacturing.order.field.good') }}</th>
                <th class="right">{{ __('manufacturing.order.field.scrap') }}</th>
                <th class="right">{{ __('manufacturing.order.field.rework') }}</th>
                <th class="right">{{ __('manufacturing.planning.yield') }}</th>
                <th class="right">{{ __('manufacturing.planning.scrap_rate') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="right">{{ rtrim(rtrim((string) $order->target_qty, '0'), '.') }} {{ $order->unit }}</td>
                <td class="right">{{ rtrim(rtrim($quality['produced'], '0'), '.') ?: '0' }}</td>
                <td class="right">{{ rtrim(rtrim($quality['good'], '0'), '.') ?: '0' }}</td>
                <td class="right">{{ rtrim(rtrim($quality['scrap'], '0'), '.') ?: '0' }}</td>
                <td class="right">{{ rtrim(rtrim($quality['rework'], '0'), '.') ?: '0' }}</td>
                <td class="right">{{ number_format((float) $quality['yield'] * 100, 1) }} %</td>
                <td class="right">{{ number_format((float) $quality['scrap_rate'] * 100, 1) }} %</td>
            </tr>
        </tbody>
    </table>

    <h2>{{ __('manufacturing.order.field.reports') }}</h2>
    @if ($order->reports->isEmpty())
        <div class="meta">{{ __('manufacturing.record.no_reports') }}</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>{{ __('manufacturing.record.reported_at') }}</th>
                    <th>{{ __('manufacturing.record.reported_by') }}</th>
                    <th class="right">{{ __('manufacturing.order.field.produced') }}</th>
                    <th class="right">{{ __('manufacturing.order.field.good') }}</th>
                    <th class="right">{{ __('manufacturing.order.field.scrap') }}</th>
                    <th class="right">{{ __('manufacturing.order.field.rework') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->reports->sortBy('reported_at') as $report)
                    <tr>
                        <td>{{ $report->reported_at?->format('d.m.Y H:i') }}</td>
                        <td>{{ $report->reported_by !== null ? ($reporters[$report->reported_by] ?? '—') : '—' }}</td>
                        <td class="right">{{ rtrim(rtrim((string) $report->produced_qty, '0'), '.') ?: '0' }}</td>
                        <td class="right">{{ rtrim(rtrim((string) $report->good_qty, '0'), '.') ?: '0' }}</td>
                        <td class="right">{{ rtrim(rtrim((string) $report->scrap_qty, '0'), '.') ?: '0' }}</td>
                        <td class="right">{{ rtrim(rtrim((string) $report->rework_qty, '0'), '.') ?: '0' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>{{ __('manufacturing.order.field.materials') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('manufacturing.delivery_note.col.name') }}</th>
                <th class="right">{{ __('manufacturing.order.field.target_qty') }}</th>
                <th class="right">{{ __('inventory.field.reserved') }}</th>
                <th class="right">{{ __('manufacturing.order.field.consumed') }}</th>
                <th class="right">{{ __('manufacturing.order.field.actual_cost') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($order->materials as $material)
                <tr>
                    <td>{{ $material->name_snapshot }}</td>
                    <td class="right">{{ rtrim(rtrim((string) $material->target_qty, '0'), '.') ?: '0' }} {{ $material->unit_snapshot }}</td>
                    <td class="right">{{ rtrim(rtrim((string) $material->reserved_qty, '0'), '.') ?: '0' }}</td>
                    <td class="right">{{ rtrim(rtrim((string) $material->consumed_qty, '0'), '.') ?: '0' }}</td>
                    <td class="right">{{ number_format((float) $material->actual_cost, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="meta">—</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($order->procedureRun)
        <h2>{{ __('manufacturing.record.procedure') }}</h2>
        <div>
            {{ $order->procedureRun->templateVersion?->template?->name }}
            (v{{ $order->procedureRun->templateVersion?->version }})
            · {{ __('Status') }}: {{ $order->procedureRun->status->label() }}
        </div>
    @endif

    <div class="meta" style="margin-top: 16pt;">{{ __('manufacturing.record.footer_note') }}</div>
</x-pdf-layout>
