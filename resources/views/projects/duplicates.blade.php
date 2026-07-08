@extends('layouts.app')
@section('title', __('Projekt-Abgleich'))
@section('nav-title', __('Projekt-Abgleich'))

@php
    use App\Services\ProjectDuplicateFinder;

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
            'label' => $p->name . ($p->number ? ' · ' . $p->number : ''),
            'ck' => $ck,
        ];
    })->values();
    $manualCustomers = collect($customerMap)
        ->map(fn($name, $key) => ['key' => $key, 'name' => $name])
        ->sortBy('name')->values();
@endphp

@section('content')
<x-index-page :subtitle="__('Doppelte Projekte (z. B. mehrfach „Wartung“ nach dem Toggl-Import) werden hier je Kunde gegenübergestellt. Pro Paar entscheidest du, welches Projekt bestehen bleibt — alle Zeiten, Aufträge, Rechnungen und Import-Referenzen werden auf es umgehängt, das andere wird gelöscht. Künftige Importe ordnen sich dann automatisch dem Ziel zu.')">
    <x-slot:actions>
        <form method="GET" action="{{ route('projects.duplicates.index') }}" class="flex items-center gap-2">
            <select name="confidence" class="select select-sm select-bordered" onchange="this.form.submit()">
                <option value="all" @selected($confidence === 'all')>{{ __('Alle Stufen') }}</option>
                <option value="{{ ProjectDuplicateFinder::CONF_EXACT }}"  @selected($confidence === ProjectDuplicateFinder::CONF_EXACT)>{{ $confidenceLabels[ProjectDuplicateFinder::CONF_EXACT] }}</option>
                <option value="{{ ProjectDuplicateFinder::CONF_LIKELY }}" @selected($confidence === ProjectDuplicateFinder::CONF_LIKELY)>{{ $confidenceLabels[ProjectDuplicateFinder::CONF_LIKELY] }}</option>
                <option value="{{ ProjectDuplicateFinder::CONF_FUZZY }}"  @selected($confidence === ProjectDuplicateFinder::CONF_FUZZY)>{{ $confidenceLabels[ProjectDuplicateFinder::CONF_FUZZY] }}</option>
            </select>
        </form>
    </x-slot:actions>

    @error('source')
        <div class="mb-4 rounded-box border border-error/40 bg-error/10 p-3 text-sm text-error">{{ $message }}</div>
    @enderror

    <div class="mb-4 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <details>
            <summary class="cursor-pointer text-sm font-medium">
                {{ __('Manuell zusammenführen') }}
                <span class="ml-1 text-base-content/50">{{ __('— zwei Projekte desselben Kunden frei wählen') }}</span>
            </summary>
            <form method="GET" action="{{ route('projects.duplicates.compare') }}"
                  x-data="{
                      customerKey: '',
                      target: '',
                      source: '',
                      customers: @js($manualCustomers),
                      projects: @js($manualProjects),
                      get filtered() { return this.projects.filter(p => p.ck === this.customerKey); },
                      resetProjects() { this.target = ''; this.source = ''; },
                  }"
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
                            <span class="badge badge-xs badge-success mr-1">{{ __('Bleibt') }}</span>{{ __('Ziel-Projekt') }}
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
                            <span class="badge badge-xs badge-error mr-1">{{ __('Wird gelöscht') }}</span>{{ __('Quell-Projekt') }}
                        </label>
                        <select name="source" id="manual-source" required x-model="source" :disabled="!customerKey"
                                class="select select-bordered w-full">
                            <option value="">{{ __('— wählen —') }}</option>
                            <template x-for="p in filtered" :key="'s-' + p.sqid">
                                <option :value="p.sqid" x-text="p.label"></option>
                            </template>
                        </select>
                    </div>
                    <button class="btn btn-sm btn-primary" :disabled="!target || !source || target === source">{{ __('Vergleichen →') }}</button>
                </div>
            </form>
        </details>
    </div>

    @if ($candidates->isEmpty())
        <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">difference</span>' :title="__('Keine Dubletten-Kandidaten im gewählten Filter.')" tone="success" framed />
    @else
        <div x-data="{ selected: [] }">
            <div x-cloak x-show="selected.length > 0"
                 class="sticky top-2 z-10 mb-3 flex items-center justify-between gap-2 rounded-box border border-primary/40 bg-base-100 px-4 py-2 shadow-md">
                <span class="text-sm text-base-content/70">
                    <span class="font-semibold text-base-content" x-text="selected.length"></span> {{ __('Paar(e) ausgewählt') }}
                </span>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn btn-sm btn-ghost" @click="selected = []">{{ __('Auswahl leeren') }}</button>
                    <form method="POST" action="{{ route('projects.duplicates.bulk-merge') }}"
                          data-confirm-dialog
                          data-confirm-message="{{ __('Alle ausgewählten Paare zusammenführen? Die jeweils markierten Quell-Projekte werden gelöscht — das kann nicht rückgängig gemacht werden.') }}"
                          data-confirm-icon="merge" data-confirm-tone="primary" data-confirm-label="{{ __('Zusammenführen') }}">
                        @csrf
                        <template x-for="pair in selected" :key="pair">
                            <input type="hidden" name="pairs[]" :value="pair">
                        </template>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Ausgewählte zusammenführen →') }}</button>
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
                <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <input type="checkbox" class="checkbox checkbox-sm" value="{{ $pairKey }}" x-model="selected"
                               aria-label="{{ __('Für Bulk-Zusammenführung auswählen') }}">
                        @php
                            $confBadge = match ($conf) {
                                ProjectDuplicateFinder::CONF_EXACT => 'badge-error',
                                ProjectDuplicateFinder::CONF_LIKELY => 'badge-warning',
                                default => 'badge-ghost',
                            };
                        @endphp
                        <span class="badge badge-sm {{ $confBadge }}">{{ $confidenceLabels[$conf] ?? $conf }}</span>
                        @foreach ($pair['reasons'] as $reason)
                            <span class="badge badge-sm badge-outline">{{ $reasonLabels[$reason] ?? $reason }}</span>
                        @endforeach
                        <span class="badge badge-sm badge-ghost">{{ $target->customer?->name ?: __('Intern') }}</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th class="w-40">{{ __('Feld') }}</th>
                                    <th>
                                        <span class="badge badge-sm badge-success">{{ __('Bleibt') }}</span>
                                        <a href="{{ route('projects.show', $target) }}" class="link ml-1">{{ $target->name }}</a>
                                    </th>
                                    <th>
                                        <span class="badge badge-sm badge-ghost">{{ __('Wird gelöscht') }}</span>
                                        <a href="{{ route('projects.show', $source) }}" class="link ml-1">{{ $source->name }}</a>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($compareFields as $field => $label)
                                    @php
                                        $tv = (string) ($target->getAttribute($field) ?? '');
                                        $sv = (string) ($source->getAttribute($field) ?? '');
                                    @endphp
                                    @if ($tv !== '' || $sv !== '')
                                        <tr>
                                            <td class="text-base-content/60">{{ $label }}</td>
                                            <td class="{{ $tv === '' ? 'text-base-content/30' : '' }}">{{ $tv !== '' ? $tv : '—' }}</td>
                                            <td class="{{ $tv !== $sv ? 'text-warning' : 'text-base-content/50' }}">{{ $sv !== '' ? $sv : '—' }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                                <tr>
                                    <td class="text-base-content/60">{{ __('Zeiten') }}</td>
                                    <td>{{ (int) ($target->time_entries_count ?? 0) }}</td>
                                    <td>{{ (int) ($source->time_entries_count ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-base-content/60">{{ __('Aufträge') }}</td>
                                    <td>{{ (int) ($target->diary_entries_count ?? 0) }}</td>
                                    <td>{{ (int) ($source->diary_entries_count ?? 0) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 flex flex-wrap justify-end gap-2">
                        <a href="{{ route('projects.duplicates.compare', ['target' => $target->sqid, 'source' => $source->sqid]) }}"
                           class="btn btn-sm btn-ghost">{{ __('Felder wählen…') }}</a>
                        <form method="POST" action="{{ route('projects.duplicates.merge') }}"
                              data-confirm-dialog
                              data-confirm-message="{{ __('„:source“ endgültig in „:target“ zusammenführen? Das Quell-Projekt wird gelöscht.', ['source' => $source->name, 'target' => $target->name]) }}"
                              data-confirm-icon="merge" data-confirm-tone="primary" data-confirm-label="{{ __('Zusammenführen') }}">
                            @csrf
                            <input type="hidden" name="source" value="{{ $source->sqid }}">
                            <input type="hidden" name="target" value="{{ $target->sqid }}">
                            <button class="btn btn-sm btn-primary">{{ __('Zusammenführen →') }}</button>
                        </form>
                        <form method="POST" action="{{ route('projects.duplicates.merge') }}"
                              data-confirm-dialog
                              data-confirm-message="{{ __('Richtung tauschen: „:source“ in „:target“ zusammenführen? Das Quell-Projekt wird gelöscht.', ['source' => $target->name, 'target' => $source->name]) }}"
                              data-confirm-icon="swap_horiz" data-confirm-tone="warning" data-confirm-label="{{ __('Zusammenführen') }}">
                            @csrf
                            <input type="hidden" name="source" value="{{ $target->sqid }}">
                            <input type="hidden" name="target" value="{{ $source->sqid }}">
                            <button class="btn btn-sm btn-outline">{{ __('Umgekehrt') }}</button>
                        </form>
                        <form method="POST" action="{{ route('projects.duplicates.dismiss') }}">
                            @csrf
                            <input type="hidden" name="source" value="{{ $source->sqid }}">
                            <input type="hidden" name="target" value="{{ $target->sqid }}">
                            <button class="btn btn-sm btn-ghost">{{ __('Kein Duplikat') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
            </div>
        </div>
    @endif
</x-index-page>
@endsection
