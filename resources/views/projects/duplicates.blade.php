{{--
  Created on   : Tue Jun 30 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : duplicates.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Projekt-Abgleich'))
@section('nav-title', __('Projekt-Abgleich'))

@php
    use App\Services\Stammdaten\ProjectDuplicateFinder;

    $reasonLabels = [
        'number' => __('Projektnr.'),
        'name' => __('Name identisch'),
        'name_similar' => __('Name ähnlich'),
    ];
    $confidenceLabels = [
        ProjectDuplicateFinder::CONF_EXACT  => __('Eindeutig'),
        ProjectDuplicateFinder::CONF_LIKELY => __('Wahrscheinlich'),
        ProjectDuplicateFinder::CONF_FUZZY  => __('Möglich'),
    ];
    $compareFields = [
        'name' => __('Name'),
        'number' => __('Projektnr.'),
        'description' => __('Beschreibung'),
    ];
    // Daten für die kundengefilterte manuelle Auswahl. Projekte tragen ihren
    // Kunden-Schlüssel (Sqid bzw. "0" = ohne Kunde); die UI zeigt erst nach
    // Kundenwahl nur dessen Projekte — gleichnamige Projekte anderer Kunden
    // tauchen gar nicht erst auf.
    $customerMap = [];
    $manualProjects = $projects->map(function ($p) use (&$customerMap) {
        $ck = $p->customer ? $p->customer->sqid : '0';
        $customerMap[$ck] = $p->customer?->name ?: __('Intern (ohne Kunde)');

        return [
            'sqid' => $p->sqid,
            // Fremdkunde (Endkunde) mit anzeigen — gleichnamige Projekte
            // verschiedener Endkunden derselben Firma bleiben unterscheidbar.
            'label' => $p->name
                . ($p->number ? ' · ' . $p->number : '')
                . ($p->foreignCustomer ? ' — ' . $p->foreignCustomer->name : ''),
            'ck' => $ck,
        ];
    })->values();
    $manualCustomers = collect($customerMap)
        ->map(fn($name, $key) => ['key' => $key, 'name' => $name])
        ->sortBy('name')->values();
@endphp

@section('content')
<x-index-page :subtitle="__('Doppelte Projekte (z. B. mehrfach „Wartung“ nach dem Toggl-Import) werden hier je Kunde gegenübergestellt. Pro Paar entscheiden Sie, welches Projekt bestehen bleibt — alle Zeiten, Aufträge, Rechnungen und Import-Referenzen werden auf es umgehängt, das andere wird gelöscht. Künftige Importe ordnen sich dann automatisch dem Ziel zu.')">
    <x-slot:actions>
        <form method="GET" action="{{ route('projects.duplicates.index') }}" class="flex items-center gap-2">
            <select name="confidence" class="select select-sm select-bordered" data-autosubmit>
                <option value="all" @selected($confidence === 'all')>{{ __('Alle Stufen') }}</option>
                <option value="{{ ProjectDuplicateFinder::CONF_EXACT }}"  @selected($confidence === ProjectDuplicateFinder::CONF_EXACT)>{{ $confidenceLabels[ProjectDuplicateFinder::CONF_EXACT] }}</option>
                <option value="{{ ProjectDuplicateFinder::CONF_LIKELY }}" @selected($confidence === ProjectDuplicateFinder::CONF_LIKELY)>{{ $confidenceLabels[ProjectDuplicateFinder::CONF_LIKELY] }}</option>
                <option value="{{ ProjectDuplicateFinder::CONF_FUZZY }}"  @selected($confidence === ProjectDuplicateFinder::CONF_FUZZY)>{{ $confidenceLabels[ProjectDuplicateFinder::CONF_FUZZY] }}</option>
            </select>
        </form>
    </x-slot:actions>

    <x-validation-errors class="mb-4" />

    <x-card class="mb-4">
        <details>
            <summary class="cursor-pointer text-sm font-medium">
                {{ __('Manuell zusammenführen') }}
                <span class="ml-1 text-muted">{{ __('— zwei Projekte desselben Kunden frei wählen') }}</span>
            </summary>
            <form method="GET" action="{{ route('projects.duplicates.compare') }}"
                  {{-- Logik in Alpine.data("projectManualMerge") (components.js) — CSP-Build-konform. --}}
                  x-data="projectManualMerge"
                  data-config="{{ json_encode(['customers' => $manualCustomers, 'projects' => $manualProjects]) }}"
                  class="mt-3 space-y-3">
                <div class="fieldset">
                    <label class="fieldset-label" for="manual-customer">{{ __('Kunde') }}</label>
                    <select id="manual-customer" x-model="customerKey" @change="resetProjects()"
                            class="select select-bordered w-full md:max-w-md">
                        <option value="">{{ __('— Kunde wählen —') }}</option>
                        <template x-for="c in customers" :key="c.key">
                            <option :value="c.key" x-text="c.name"></option>
                        </template>
                    </select>
                </div>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end">
                    <div class="fieldset">
                        <label class="fieldset-label" for="manual-target">
                            <x-status-badge tone="success" size="xs" class="mr-1">{{ __('Bleibt') }}</x-status-badge>{{ __('Ziel-Projekt') }}
                        </label>
                        <select name="target" id="manual-target" required x-model="target" :disabled="!customerKey"
                                class="select select-bordered w-full">
                            <option value="">{{ __('— wählen —') }}</option>
                            <template x-for="p in filtered" :key="'t-' + p.sqid">
                                <option :value="p.sqid" x-text="p.label"></option>
                            </template>
                        </select>
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-label" for="manual-source">
                            <x-status-badge tone="error" size="xs" class="mr-1">{{ __('Wird gelöscht') }}</x-status-badge>{{ __('Quell-Projekt') }}
                        </label>
                        <select name="source" id="manual-source" required x-model="source" :disabled="!customerKey"
                                class="select select-bordered w-full">
                            <option value="">{{ __('— wählen —') }}</option>
                            <template x-for="p in filtered" :key="'s-' + p.sqid">
                                <option :value="p.sqid" x-text="p.label"></option>
                            </template>
                        </select>
                    </div>
                    <x-button type="submit" ::disabled="!target || !source || target === source">{{ __('Vergleichen →') }}</x-button>
                </div>
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
                    <form method="POST" action="{{ route('projects.duplicates.bulk-merge') }}"
                          data-confirm-dialog
                          data-confirm-message="{{ __('Alle ausgewählten Paare zusammenführen? Die jeweils markierten Quell-Projekte werden gelöscht — das kann nicht rückgängig gemacht werden.') }}"
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
                                ProjectDuplicateFinder::CONF_EXACT => 'error',
                                ProjectDuplicateFinder::CONF_LIKELY => 'warning',
                                default => 'ghost',
                            };
                        @endphp
                        <x-status-badge :tone="$confTone">{{ $confidenceLabels[$conf] ?? $conf }}</x-status-badge>
                        @foreach ($pair['reasons'] as $reason)
                            <x-status-badge tone="plain" outline>{{ $reasonLabels[$reason] ?? $reason }}</x-status-badge>
                        @endforeach
                        <x-status-badge>{{ $target->customer?->name ?: __('Intern') }}</x-status-badge>
                        @if ($target->foreignCustomer)
                            <x-status-badge tone="accent" outline>{{ __('Endkunde') }}: {{ $target->foreignCustomer->name }}</x-status-badge>
                        @endif
                    </div>

                    <x-table>
                        <x-slot:head>
                                <tr>
                                    <th class="w-40">{{ __('Feld') }}</th>
                                    <th>
                                        <x-status-badge tone="success">{{ __('Bleibt') }}</x-status-badge>
                                        <a href="{{ route('projects.show', $target) }}" class="link ml-1">{{ $target->name }}</a>
                                    </th>
                                    <th>
                                        <x-status-badge>{{ __('Wird gelöscht') }}</x-status-badge>
                                        <a href="{{ route('projects.show', $source) }}" class="link ml-1">{{ $source->name }}</a>
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
                                <tr>
                                    <td class="text-muted">{{ __('Zeiten') }}</td>
                                    <td>{{ (int) ($target->time_entries_count ?? 0) }}</td>
                                    <td>{{ (int) ($source->time_entries_count ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">{{ __('Aufträge') }}</td>
                                    <td>{{ (int) ($target->diary_entries_count ?? 0) }}</td>
                                    <td>{{ (int) ($source->diary_entries_count ?? 0) }}</td>
                                </tr>
                    </x-table>

                    <div class="mt-3 flex flex-wrap justify-end gap-2">
                        <x-button :href="route('projects.duplicates.compare', ['target' => $target->sqid, 'source' => $source->sqid])" tone="ghost">{{ __('Felder wählen…') }}</x-button>
                        <form method="POST" action="{{ route('projects.duplicates.merge') }}"
                              data-confirm-dialog
                              data-confirm-message="{{ __('„:source“ endgültig in „:target“ zusammenführen? Das Quell-Projekt wird gelöscht.', ['source' => $source->name, 'target' => $target->name]) }}"
                              data-confirm-icon="merge" data-confirm-tone="primary" data-confirm-label="{{ __('Zusammenführen') }}">
                            @csrf
                            <input type="hidden" name="source" value="{{ $source->sqid }}">
                            <input type="hidden" name="target" value="{{ $target->sqid }}">
                            <x-button type="submit">{{ __('Zusammenführen →') }}</x-button>
                        </form>
                        <form method="POST" action="{{ route('projects.duplicates.merge') }}"
                              data-confirm-dialog
                              data-confirm-message="{{ __('Richtung tauschen: „:source“ in „:target“ zusammenführen? Das Quell-Projekt wird gelöscht.', ['source' => $target->name, 'target' => $source->name]) }}"
                              data-confirm-icon="swap_horiz" data-confirm-tone="warning" data-confirm-label="{{ __('Zusammenführen') }}">
                            @csrf
                            <input type="hidden" name="source" value="{{ $target->sqid }}">
                            <input type="hidden" name="target" value="{{ $source->sqid }}">
                            <x-button type="submit" tone="outline">{{ __('Umgekehrt') }}</x-button>
                        </form>
                        <form method="POST" action="{{ route('projects.duplicates.dismiss') }}">
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
@endsection
