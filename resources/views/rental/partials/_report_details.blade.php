{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _report_details.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Positionen eines Übergabe- oder Rücknahmeprotokolls (MVP-1604): Checkliste,
  Zubehör, Zustandspositionen, Verbrauch. $internal = false blendet Notizen
  und Verbrauch aus (Kundenportal).
--}}
@php
    $internal = $internal ?? true;
    $checklist = $report->checklist;
    $consumables = $internal ? array_values(array_filter((array) ($report->consumables ?? []), 'is_array')) : [];
    $hasDetails = ($checklist !== null && ! $checklist->isEmpty())
        || $report->accessoryItems->isNotEmpty()
        || $report->conditionItems->isNotEmpty()
        || $consumables !== [];
@endphp
@if ($hasDetails)
    <details class="mt-1 text-xs">
        <summary class="cursor-pointer text-muted">{{ __('rental.report.details') }}</summary>
        <div class="mt-1 grid gap-2">
            @if ($checklist !== null && ! $checklist->isEmpty())
                <div>
                    <p class="font-semibold">{{ __('rental.report.checklist') }} ({{ $checklist->progress()['done'] }}/{{ $checklist->progress()['total'] }})</p>
                    <ul>
                        @foreach ($checklist->items() as $item)
                            <li class="flex items-center gap-1">
                                <x-icon :name="$item['value'] === true ? 'check_circle' : 'radio_button_unchecked'" class="text-sm {{ $item['value'] === true ? 'text-success' : 'text-muted' }}" />
                                <span>{{ $item['field']->label }}</span>
                                <span class="sr-only">{{ $item['value'] === true ? __('rental.report.point_done') : __('rental.report.point_open') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($report->accessoryItems->isNotEmpty())
                <div>
                    <p class="font-semibold">{{ __('rental.report.accessories') }}</p>
                    <ul>
                        @foreach ($report->accessoryItems as $item)
                            <li>
                                {{ $item->quantity }} × {{ $item->label }}
                                @unless ($item->present)
                                    <span class="text-warning">· {{ __('rental.report.accessory_missing') }}</span>
                                @endunless
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($report->conditionItems->isNotEmpty())
                <div>
                    <p class="font-semibold">{{ __('rental.report.condition_items') }}</p>
                    <ul>
                        @foreach ($report->conditionItems as $item)
                            <li>
                                {{ $item->label }}: {{ $item->state->label() }}
                                @if ($internal && filled($item->note))
                                    <span class="text-muted">— {{ $item->note }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($consumables !== [])
                <div>
                    <p class="font-semibold">{{ __('rental.report.consumables') }}</p>
                    <ul>
                        @foreach ($consumables as $consumable)
                            <li>{{ $consumable['label'] ?? '—' }}@if (filled($consumable['quantity'] ?? null)): {{ $consumable['quantity'] }} {{ $consumable['unit'] ?? '' }}@endif</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </details>
@endif
