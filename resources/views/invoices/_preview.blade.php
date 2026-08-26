{{--
  Created on   : Fri Aug 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _preview.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Rechnungs-Vorschau (MVP-462): Server-Partial für den Erstell-Dialog —
  Positionsblöcke, Summen (H:MM + dezimal), Warnungen und Einzel-Einträge
  mit Ausschluss-Checkboxen. Read-only; verbraucht nichts.
--}}

@if ($blocked !== null)
    <div class="rounded-box border border-warning/40 bg-warning/5 px-3 py-2 text-sm">{{ $blocked }}</div>
@elseif ($preview !== null)
    @if ($preview['totals']['count'] === 0 && $preview['travel']['count'] === 0)
        <div class="rounded-box border border-base-300 bg-base-200/40 px-3 py-2 text-sm text-base-content/70">
            {{ __('invoicing.preview.empty') }}
        </div>
    @else
        <div class="space-y-3 rounded-box border border-base-300 bg-base-200/30 p-3">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                <span class="font-medium">{{ __('invoicing.preview.heading') }}</span>
                <span>{{ trans_choice('invoicing.preview.entry_count', $preview['totals']['count'], ['count' => $preview['totals']['count']]) }}</span>
                <x-duration :minutes="$preview['totals']['minutes']" />
                <span class="font-medium tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($preview['totals']['amount'], 2, withThousandsSeparator: true) }} €</span>
                @if ($preview['travel']['count'] > 0)
                    <span class="text-muted">{{ __('invoicing.preview.travel', ['count' => $preview['travel']['count']]) }}</span>
                @endif
            </div>

            @if ($preview['warnings']['late_count'] > 0)
                <div class="rounded-box border border-warning/40 bg-warning/5 px-3 py-2 text-xs">
                    {{ trans_choice('invoicing.preview.warning_late', $preview['warnings']['late_count'], ['count' => $preview['warnings']['late_count']]) }}
                </div>
            @endif

            @if ($preview['lines'] !== [])
                <div class="overflow-x-auto">
                    <table class="table table-xs">
                        <thead>
                            <tr>
                                <th>{{ __('invoicing.preview.column.description') }}</th>
                                <th class="text-right">{{ __('invoicing.preview.column.duration') }}</th>
                                <th class="text-right">{{ __('invoicing.preview.column.rate') }}</th>
                                <th class="text-right">{{ __('invoicing.preview.column.amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($preview['lines'] as $line)
                                <tr>
                                    <td class="max-w-sm truncate" title="{{ $line['description'] }}">{{ $line['description'] }}</td>
                                    <td class="whitespace-nowrap text-right"><x-duration :minutes="$line['minutes']" /></td>
                                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($line['rate'], 2, withThousandsSeparator: true) }}</td>
                                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($line['amount'], 2, withThousandsSeparator: true) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($preview['entries']->isNotEmpty())
                <details>
                    <summary class="cursor-pointer text-xs font-medium text-base-content/70">
                        {{ __('invoicing.preview.entries_heading') }}
                    </summary>
                    <ul class="mt-2 max-h-48 space-y-1 overflow-y-auto pr-1">
                        @foreach ($preview['entries'] as $entry)
                            <li class="flex items-center gap-2 text-xs">
                                <label class="flex cursor-pointer items-center gap-2">
                                    <input type="checkbox" class="checkbox checkbox-xs"
                                           name="excluded_time_entry_ids[]" value="{{ $entry->sqid }}">
                                    <span class="text-muted">{{ __('invoicing.preview.exclude') }}</span>
                                </label>
                                <span class="whitespace-nowrap">{{ $entry->date?->format(\App\Support\Formats::date()) }}</span>
                                <span class="text-muted">{{ $entry->user->name ?? '—' }}</span>
                                <span class="max-w-xs truncate" title="{{ $entry->description }}">{{ $entry->description }}</span>
                                <x-duration :minutes="$entry->minutes" class="ml-auto" />
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-1 text-xs text-muted">{{ __('invoicing.preview.exclude_hint') }}</p>
                </details>
            @endif
        </div>
    @endif
@endif
