{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : pick-list.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $list (PickList), $organization, $number --}}
<x-pdf-layout pdf-type="report" :pdf-title="__('inventory.pick_list.title') . ' ' . $number">
    <h1>{{ __('inventory.pick_list.title') }} {{ $number }}</h1>
    <div class="meta">
        {{ now()->format('d.m.Y H:i') }}
        @if ($list->sourceLabel())
            · {{ __('inventory.pick_list.source') }}: <strong>{{ $list->sourceLabel() }}</strong>
        @endif
        · {{ __('inventory.pick_list.lines') }}: <strong>{{ count($list->lines) }}</strong>
    </div>

    <table style="margin-top: 12pt;">
        <thead>
            <tr>
                <th>{{ __('inventory.pick_list.position') }}</th>
                <th>{{ __('inventory.field.warehouse') }}</th>
                <th>{{ __('inventory.field.bin') }}</th>
                <th>{{ __('inventory.field.lot') }}</th>
                <th>{{ __('article.field.sku') }}</th>
                <th>{{ __('inventory.field.variant') }}</th>
                <th style="text-align: right;">{{ __('inventory.field.quantity') }}</th>
                <th>{{ __('inventory.field.unit') }}</th>
                <th style="text-align: right;">{{ __('inventory.pick_list.available') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($list->lines as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->warehouse->name }}</td>
                    <td>{{ $line->bin?->code ?? '—' }}</td>
                    <td>{{ $line->lot?->lot_no ?? '—' }}@if ($line->lot?->best_before) ({{ $line->lot->best_before->format('d.m.Y') }})@endif</td>
                    <td>{{ $line->sku() !== '' ? $line->sku() : '—' }}</td>
                    <td>{{ $line->label() }}</td>
                    <td style="text-align: right;">{{ rtrim(rtrim($line->qty, '0'), '.') }}</td>
                    <td>{{ $line->unit }}</td>
                    <td style="text-align: right;">{{ rtrim(rtrim($line->available, '0'), '.') }}</td>
                    <td style="width: 24pt; border: 1px solid #999;"></td>
                </tr>
            @empty
                <tr><td colspan="10">{{ __('inventory.empty.pick_list') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="meta" style="margin-top: 16pt;">{{ __('inventory.pick_list.footer_note') }}</div>
</x-pdf-layout>
