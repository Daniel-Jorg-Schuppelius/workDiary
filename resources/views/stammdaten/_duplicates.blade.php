{{--
  Created on   : Sun Oct 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _duplicates.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dubletten-Abgleich der Stammdaten — eine Vorlage für Kunde, Lieferant und
     Artikel (Konsolidierungs-Audit 2026-10, k4-05: drei Kopien, in zweien
     zählte die Zeile „Projekte“ etwas, das es dort nicht gibt).

     Parameter: $finder (Klasse mit CONF_*), $routePrefix, $records (Auswahl
     „manuell“), $reasonLabels, $compareFields, $counters (Beschriftung =>
     Zähl-Attribut), $texts (subtitle, manual_hint, target, source,
     bulk_confirm, merge_confirm, swap_confirm — die letzten zwei mit
     :source/:target). Dazu aus dem Controller: $candidates, $confidence. --}}
@php
    $confidenceLabels = [
        $finder::CONF_EXACT => __('Eindeutig'),
        $finder::CONF_LIKELY => __('Wahrscheinlich'),
        $finder::CONF_FUZZY => __('Möglich'),
    ];
@endphp
<x-index-page :subtitle="$texts['subtitle']">
    <x-slot:actions>
        <form method="GET" action="{{ route($routePrefix . '.duplicates.index') }}" class="flex items-center gap-2">
            <select name="confidence" class="select select-sm select-bordered" data-autosubmit>
                <option value="all" @selected($confidence === 'all')>{{ __('Alle Stufen') }}</option>
                <option value="{{ $finder::CONF_EXACT }}"  @selected($confidence === $finder::CONF_EXACT)>{{ $confidenceLabels[$finder::CONF_EXACT] }}</option>
                <option value="{{ $finder::CONF_LIKELY }}" @selected($confidence === $finder::CONF_LIKELY)>{{ $confidenceLabels[$finder::CONF_LIKELY] }}</option>
                <option value="{{ $finder::CONF_FUZZY }}"  @selected($confidence === $finder::CONF_FUZZY)>{{ $confidenceLabels[$finder::CONF_FUZZY] }}</option>
            </select>
        </form>
    </x-slot:actions>

    <x-validation-errors class="mb-4" />

    <x-card class="mb-4">
        <details @if ($errors->any()) open @endif>
            <summary class="cursor-pointer text-sm font-medium">
                {{ __('Manuell zusammenführen') }}
                <span class="ml-1 text-muted">{{ $texts['manual_hint'] }}</span>
            </summary>
            <form method="GET" action="{{ route($routePrefix . '.duplicates.compare') }}"
                  class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end">
                <div class="fieldset">
                    <label class="fieldset-label" for="manual-target">
                        <x-status-badge tone="success" size="xs" class="mr-1">{{ __('Bleibt') }}</x-status-badge>{{ $texts['target'] }}
                    </label>
                    <select name="target" id="manual-target" required class="select select-bordered w-full">
                        <option value="">{{ __('— wählen —') }}</option>
                        @foreach ($records as $record)
                            <option value="{{ $record->sqid }}">{{ $record->name }}@if ($record->number) ({{ $record->number }})@endif</option>
                        @endforeach
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="manual-source">
                        <x-status-badge tone="error" size="xs" class="mr-1">{{ __('Wird gelöscht') }}</x-status-badge>{{ $texts['source'] }}
                    </label>
                    <select name="source" id="manual-source" required class="select select-bordered w-full">
                        <option value="">{{ __('— wählen —') }}</option>
                        @foreach ($records as $record)
                            <option value="{{ $record->sqid }}">{{ $record->name }}@if ($record->number) ({{ $record->number }})@endif</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit">{{ __('Vergleichen →') }}</x-button>
            </form>
        </details>
    </x-card>

    @if ($candidates->isEmpty())
        <x-empty-state icon="difference" :title="__('Keine Dubletten-Kandidaten im gewählten Filter.')" tone="success" framed />
    @else
        {{-- Logik in Alpine.data("pairSelection") (components.js) — CSP-Build-konform. --}}
        <div x-data="pairSelection"
             data-pairs="{{ json_encode($candidates->map(fn ($pair) => $pair['source']->sqid . ':' . $pair['target']->sqid)->values()) }}">
            <label class="mb-3 inline-flex cursor-pointer items-center gap-2 text-sm">
                <input type="checkbox" class="checkbox checkbox-sm" :checked="allSelected()" @change="toggleAll()">
                {{ __('Alle auswählen') }}
                <span class="text-muted">({{ $candidates->count() }})</span>
            </label>
            <div x-cloak x-show="hasSelection()"
                 class="sticky top-2 z-10 mb-3 flex items-center justify-between gap-2 rounded-box border border-primary/40 bg-base-100 px-4 py-2 shadow-md">
                <span class="text-sm text-base-content/70">
                    <span class="font-semibold text-base-content" x-text="selected.length"></span> {{ __('Paar(e) ausgewählt') }}
                </span>
                <div class="flex items-center gap-2">
                    <x-button tone="ghost" @click="clear()">{{ __('Auswahl leeren') }}</x-button>
                    <form method="POST" action="{{ route($routePrefix . '.duplicates.bulk-merge') }}"
                          data-confirm-dialog
                          data-confirm-message="{{ $texts['bulk_confirm'] }}"
                          data-confirm-icon="merge" data-confirm-tone="primary" data-confirm-label="{{ __('Zusammenführen') }}">
                        @csrf
                        <template x-for="pair in selected" :key="pair">
                            <input type="hidden" name="pairs[]" :value="pair">
                        </template>
                        <x-button type="submit">{{ __('Ausgewählte zusammenführen →') }}</x-button>
                    </form>
                </div>
            </div>

            <div class="space-y-4">
            @foreach ($candidates as $pair)
                @php
                    $target = $pair['target'];
                    $source = $pair['source'];
                    $conf = $pair['confidence'];
                    $pairKey = $source->sqid . ':' . $target->sqid;
                @endphp
                <x-card>
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <input type="checkbox" class="checkbox checkbox-sm" value="{{ $pairKey }}" x-model="selected"
                               aria-label="{{ __('Für Bulk-Zusammenführung auswählen') }}">
                        @php
                            $confTone = match ($conf) {
                                $finder::CONF_EXACT => 'error',
                                $finder::CONF_LIKELY => 'warning',
                                default => 'ghost',
                            };
                        @endphp
                        <x-status-badge :tone="$confTone">{{ $confidenceLabels[$conf] ?? $conf }}</x-status-badge>
                        @foreach ($pair['reasons'] as $reason)
                            <x-status-badge tone="plain" outline>{{ $reasonLabels[$reason] ?? $reason }}</x-status-badge>
                        @endforeach
                    </div>

                    <x-table>
                        <x-slot:head>
                                <tr>
                                    <th class="w-40">{{ __('Feld') }}</th>
                                    <th>
                                        <x-status-badge tone="success">{{ __('Bleibt') }}</x-status-badge>
                                        <a href="{{ route($routePrefix . '.show', $target) }}" class="link ml-1">{{ $target->name }}</a>
                                    </th>
                                    <th>
                                        <x-status-badge>{{ __('Wird gelöscht') }}</x-status-badge>
                                        <a href="{{ route($routePrefix . '.show', $source) }}" class="link ml-1">{{ $source->name }}</a>
                                    </th>
                                </tr>
                        </x-slot:head>
                                @foreach ($compareFields as $field => $label)
                                    @php
                                        $tv = (string) ($target->getAttribute($field) ?? '');
                                        $sv = (string) ($source->getAttribute($field) ?? '');
                                    @endphp
                                    @if ($tv !== '' || $sv !== '')
                                        <tr>
                                            <td class="text-muted">{{ $label }}</td>
                                            <td class="{{ $tv === '' ? 'text-muted' : '' }}">{{ $tv !== '' ? $tv : '—' }}</td>
                                            <td class="{{ $tv !== $sv ? 'text-warning' : 'text-muted' }}">{{ $sv !== '' ? $sv : '—' }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                                @foreach ($counters as $counterLabel => $counterAttribute)
                                    <tr>
                                        <td class="text-muted">{{ $counterLabel }}</td>
                                        <td>{{ (int) ($target->getAttribute($counterAttribute) ?? 0) }}</td>
                                        <td>{{ (int) ($source->getAttribute($counterAttribute) ?? 0) }}</td>
                                    </tr>
                                @endforeach
                    </x-table>

                    <div class="mt-3 flex flex-wrap justify-end gap-2">
                        <x-button :href="route($routePrefix . '.duplicates.compare', ['target' => $target->sqid, 'source' => $source->sqid])"
                                tone="ghost">{{ __('Felder wählen…') }}</x-button>
                        <form method="POST" action="{{ route($routePrefix . '.duplicates.merge') }}"
                              data-confirm-dialog
                              data-confirm-message="{{ strtr($texts['merge_confirm'], [':source' => $source->name, ':target' => $target->name]) }}"
                              data-confirm-icon="merge" data-confirm-tone="primary" data-confirm-label="{{ __('Zusammenführen') }}">
                            @csrf
                            <input type="hidden" name="source" value="{{ $source->sqid }}">
                            <input type="hidden" name="target" value="{{ $target->sqid }}">
                            <x-button type="submit">{{ __('Zusammenführen →') }}</x-button>
                        </form>
                        <form method="POST" action="{{ route($routePrefix . '.duplicates.merge') }}"
                              data-confirm-dialog
                              data-confirm-message="{{ strtr($texts['swap_confirm'], [':source' => $target->name, ':target' => $source->name]) }}"
                              data-confirm-icon="swap_horiz" data-confirm-tone="warning" data-confirm-label="{{ __('Zusammenführen') }}">
                            @csrf
                            <input type="hidden" name="source" value="{{ $target->sqid }}">
                            <input type="hidden" name="target" value="{{ $source->sqid }}">
                            <x-button type="submit" tone="outline">{{ __('Umgekehrt') }}</x-button>
                        </form>
                        <form method="POST" action="{{ route($routePrefix . '.duplicates.dismiss') }}">
                            @csrf
                            <input type="hidden" name="source" value="{{ $source->sqid }}">
                            <input type="hidden" name="target" value="{{ $target->sqid }}">
                            <x-button type="submit" tone="ghost">{{ __('Kein Duplikat') }}</x-button>
                        </form>
                    </div>
                </x-card>
            @endforeach
            </div>
        </div>
    @endif
</x-index-page>
