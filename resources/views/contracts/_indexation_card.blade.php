{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _indexation_card.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Indexanpassungen am Vertrag (MVP-952). Erwartet: $contract, $indexationPreview --}}
@php
    $num = static fn ($v, int $d = 2): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, $d, withThousandsSeparator: true);
@endphp
<x-card :title="__('contract.indexation.title')" padding="p-0">
    <x-slot:actions>
        @can('update', $contract)
            <x-icon-btn icon="tune" size="sm" data-entry-modal-trigger :href="route('contracts.indexation.edit', $contract)" show-label>{{ __('contract.indexation.configure') }}</x-icon-btn>
            @if ($contract->indexation_base_value !== null)
                <form method="POST" action="{{ route('contracts.indexation.propose', $contract) }}">
                    @csrf
                    <x-icon-btn icon="calculate" size="sm" type="submit" show-label>{{ __('contract.indexation.check') }}</x-icon-btn>
                </form>
            @endif
        @endcan
    </x-slot:actions>
    <div class="px-4 py-3 text-sm">
        @if ($contract->indexation_base_value === null)
            <p class="text-muted">{{ __('contract.indexation.not_configured') }}</p>
        @else
            <p>
                {{ __('contract.indexation.base_line', ['value' => $num($contract->indexation_base_value, 1), 'period' => $contract->indexation_base_period_on?->format('m/Y') ?? '—']) }}
                @if ($indexationPreview !== null)
                    · {{ __('contract.indexation.preview_line', ['value' => $num($indexationPreview['index_value'], 1), 'period' => $indexationPreview['index_period_on']->format('m/Y'), 'change' => $num($indexationPreview['effective_percent'], 2), 'amount' => $num($indexationPreview['new_amount']), 'currency' => $contract->currency->value]) }}
                @endif
            </p>
        @endif
    </div>
    @if ($contract->indexations->isNotEmpty())
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('contract.indexation.field.index') }}</th>
                    <th class="text-right">{{ __('contract.indexation.field.change') }}</th>
                    <th class="text-right">{{ __('contract.indexation.field.old_amount') }}</th>
                    <th class="text-right">{{ __('contract.indexation.field.new_amount') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($contract->indexations as $indexation)
                <tr>
                    <td>{{ $num($indexation->base_value, 1) }} ({{ $indexation->base_period_on->format('m/Y') }}) → {{ $num($indexation->index_value, 1) }} ({{ $indexation->index_period_on->format('m/Y') }})</td>
                    <td class="text-right tabular-nums">{{ $num($indexation->change_percent, 2) }} %</td>
                    <td class="text-right tabular-nums">{{ $num($indexation->old_amount) }} {{ $indexation->currency }}</td>
                    <td class="text-right tabular-nums">{{ $num($indexation->new_amount) }} {{ $indexation->currency }}</td>
                    <td>
                        <span class="wd-badge badge-{{ $indexation->status->tone() }}">{{ $indexation->status->label() }}</span>
                        @if ($indexation->effective_on !== null)<span class="text-xs text-muted">{{ __('contract.indexation.effective', ['date' => $indexation->effective_on->fdate()]) }}</span>@endif
                    </td>
                    <td class="text-right">
                        @if ($indexation->status === \App\Enums\Contract\ContractIndexationStatus::Proposed)
                            @can('update', $contract)
                                <div class="flex justify-end gap-1">
                                    <form method="POST" action="{{ route('contracts.indexation.apply', $indexation) }}" data-confirm-dialog data-confirm-message="{{ __('contract.indexation.confirm_apply', ['amount' => $num($indexation->new_amount) . ' ' . $indexation->currency]) }}">
                                        @csrf
                                        <x-icon-btn icon="check" size="xs" type="submit" :title="__('contract.indexation.apply')" />
                                    </form>
                                    <form method="POST" action="{{ route('contracts.indexation.dismiss', $indexation) }}">
                                        @csrf
                                        <x-icon-btn icon="close" size="xs" type="submit" :title="__('contract.indexation.dismiss')" />
                                    </form>
                                </div>
                            @endcan
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-card>
