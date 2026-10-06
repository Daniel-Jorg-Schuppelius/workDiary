{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _retentions.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Einbehalte an der Eingangsrechnung (MVP-953). Erwartet: $incoming --}}
@php
    $num = static fn ($v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 2, withThousandsSeparator: true);
    $canManage = auth()->user()?->canManageBilling() ?? false;
@endphp
<x-card :title="__('sepa.retention.title')" padding="p-0">
    @if ($incoming->retentions->isNotEmpty())
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('sepa.retention.field.kind') }}</th>
                    <th class="text-right">{{ __('sepa.column.amount') }}</th>
                    <th>{{ __('sepa.retention.field.due_on') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($incoming->retentions as $retention)
                <tr>
                    <td>{{ $retention->kind->label() }}@if ($retention->percent !== null) <span class="text-xs text-muted">({{ $num($retention->percent) }} %)</span>@endif</td>
                    <td class="text-right tabular-nums">{{ $num($retention->amount) }} {{ $retention->currency }}</td>
                    <td>
                        {{ $retention->due_on?->fdate() ?? '—' }}
                        @if ($retention->isOverdue())<span class="wd-badge badge-warning">{{ __('sepa.retention.due') }}</span>@endif
                    </td>
                    <td>
                        <span class="wd-badge badge-{{ $retention->status->tone() }}">{{ $retention->status->label() }}</span>
                        @if ($retention->paid_in_run_id !== null)<span class="text-xs text-muted">{{ __('sepa.retention.in_run') }}</span>@endif
                    </td>
                    <td class="text-right">
                        @if ($canManage && $retention->status === \App\Enums\Invoicing\RetentionStatus::Open)
                            <div class="flex justify-end gap-1">
                                <form method="POST" action="{{ route('finance.incoming-invoices.retentions.release', $retention) }}" data-confirm-dialog data-confirm-message="{{ __('sepa.retention.confirm_release') }}">
                                    @csrf
                                    <x-icon-btn icon="lock_open" size="xs" type="submit" :title="__('sepa.retention.release')" />
                                </form>
                                @if ($incoming->paid_in_run_id === null)
                                    <form method="POST" action="{{ route('finance.incoming-invoices.retentions.destroy', $retention) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-btn icon="delete" size="xs" type="submit" :title="__('Entfernen')" />
                                    </form>
                                @endif
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
    @else
        <x-empty-state icon="savings" :message="__('sepa.retention.empty')" compact class="m-4" />
    @endif
    @if ($canManage && $incoming->paid_in_run_id === null)
        <form method="POST" action="{{ route('finance.incoming-invoices.retentions.store', $incoming) }}" class="flex flex-wrap items-end gap-2 border-t border-base-300 px-4 py-3" data-entry-form>
            @csrf
            <x-select-field name="kind" :label="__('sepa.retention.field.kind')" required>
                @foreach (\App\Enums\Invoicing\RetentionKind::cases() as $kind)
                    <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                @endforeach
            </x-select-field>
            <x-input-field name="percent" type="number" step="0.01" min="0.01" max="100" :label="__('sepa.retention.field.percent')" />
            <x-input-field name="amount" type="number" step="0.01" min="0.01" :label="__('sepa.retention.field.amount')" />
            <x-input-field name="due_on" type="date" :label="__('sepa.retention.field.due_on')" />
            <x-input-field name="note" :label="__('sepa.retention.field.note')" />
            <x-button type="submit" tone="plain">{{ __('sepa.retention.add') }}</x-button>
        </form>
    @endif
</x-card>
