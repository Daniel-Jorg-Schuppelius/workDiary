@extends('layouts.app')
@section('title', __('Kunden-Abgleich'))
@section('nav-title', __('Kunden-Abgleich'))

@php
    use App\Services\CustomerDuplicateFinder;

    $reasonLabels = [
        'vat_id' => __('USt-IdNr.'),
        'lexoffice_contact_number' => __('Lexoffice-Nr.'),
        'email' => __('E-Mail'),
        'company_zip' => __('Firma + PLZ'),
        'name' => __('Name/Firma ähnlich'),
    ];
    $confidenceLabels = [
        CustomerDuplicateFinder::CONF_EXACT  => __('Eindeutig'),
        CustomerDuplicateFinder::CONF_LIKELY => __('Wahrscheinlich'),
        CustomerDuplicateFinder::CONF_FUZZY  => __('Möglich'),
    ];
    // Felder, die im Vergleich gegenübergestellt werden.
    $compareFields = [
        'name' => __('Name'),
        'company' => __('Firma'),
        'number' => __('Kundennr.'),
        'lexoffice_contact_number' => __('Lexoffice-Nr.'),
        'vat_id' => __('USt-IdNr.'),
        'email' => __('E-Mail'),
        'address_zip' => __('PLZ'),
        'address_city' => __('Ort'),
    ];
@endphp

@section('content')
<x-index-page :subtitle="__('Doppelte Kunden (z. B. nach dem Toggl-Import) werden hier gegenübergestellt. Pro Paar entscheidest du, welcher Datensatz bestehen bleibt — alle Projekte, Zeiten, Rechnungen und Referenzen werden auf ihn umgehängt, der andere wird gelöscht.')">
    <x-slot:actions>
        <form method="GET" action="{{ route('customers.duplicates.index') }}" class="flex items-center gap-2">
            <select name="confidence" class="select select-sm select-bordered" data-autosubmit>
                <option value="all" @selected($confidence === 'all')>{{ __('Alle Stufen') }}</option>
                <option value="{{ CustomerDuplicateFinder::CONF_EXACT }}"  @selected($confidence === CustomerDuplicateFinder::CONF_EXACT)>{{ $confidenceLabels[CustomerDuplicateFinder::CONF_EXACT] }}</option>
                <option value="{{ CustomerDuplicateFinder::CONF_LIKELY }}" @selected($confidence === CustomerDuplicateFinder::CONF_LIKELY)>{{ $confidenceLabels[CustomerDuplicateFinder::CONF_LIKELY] }}</option>
                <option value="{{ CustomerDuplicateFinder::CONF_FUZZY }}"  @selected($confidence === CustomerDuplicateFinder::CONF_FUZZY)>{{ $confidenceLabels[CustomerDuplicateFinder::CONF_FUZZY] }}</option>
            </select>
        </form>
    </x-slot:actions>

    <div class="mb-4 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <details @if (session('manual_open')) open @endif>
            <summary class="cursor-pointer text-sm font-medium">
                {{ __('Manuell zusammenführen') }}
                <span class="ml-1 text-base-content/50">{{ __('— zwei Kunden frei wählen (für Dubletten, die der Abgleich nicht erkennt)') }}</span>
            </summary>
            <form method="GET" action="{{ route('customers.duplicates.compare') }}"
                  class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end">
                <div class="fieldset">
                    <label class="fieldset-label" for="manual-target">
                        <span class="badge badge-xs badge-success mr-1">{{ __('Bleibt') }}</span>{{ __('Ziel-Kunde') }}
                    </label>
                    <select name="target" id="manual-target" required class="select select-bordered w-full">
                        <option value="">{{ __('— wählen —') }}</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->sqid }}">{{ $customer->name }}@if ($customer->number) ({{ $customer->number }})@endif</option>
                        @endforeach
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="manual-source">
                        <span class="badge badge-xs badge-error mr-1">{{ __('Wird gelöscht') }}</span>{{ __('Quell-Kunde') }}
                    </label>
                    <select name="source" id="manual-source" required class="select select-bordered w-full">
                        <option value="">{{ __('— wählen —') }}</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->sqid }}">{{ $customer->name }}@if ($customer->number) ({{ $customer->number }})@endif</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-sm btn-primary">{{ __('Vergleichen →') }}</button>
            </form>
        </details>
    </div>

    @if ($candidates->isEmpty())
        <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">difference</span>' :title="__('Keine Dubletten-Kandidaten im gewählten Filter.')" tone="success" framed />
    @else
        {{-- Logik in Alpine.data("pairSelection") (components.js) — CSP-Build-konform. --}}
        <div x-data="pairSelection"
             data-pairs="{{ json_encode($candidates->map(fn ($pair) => $pair['source']->sqid . ':' . $pair['target']->sqid)->values()) }}">
            <label class="mb-3 inline-flex cursor-pointer items-center gap-2 text-sm">
                <input type="checkbox" class="checkbox checkbox-sm" :checked="allSelected()" @change="toggleAll()">
                {{ __('Alle auswählen') }}
                <span class="text-base-content/50">({{ $candidates->count() }})</span>
            </label>
            <div x-cloak x-show="hasSelection()"
                 class="sticky top-2 z-10 mb-3 flex items-center justify-between gap-2 rounded-box border border-primary/40 bg-base-100 px-4 py-2 shadow-md">
                <span class="text-sm text-base-content/70">
                    <span class="font-semibold text-base-content" x-text="selected.length"></span> {{ __('Paar(e) ausgewählt') }}
                </span>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn btn-sm btn-ghost" @click="clear()">{{ __('Auswahl leeren') }}</button>
                    <form method="POST" action="{{ route('customers.duplicates.bulk-merge') }}"
                          data-confirm-dialog
                          data-confirm-message="{{ __('Alle ausgewählten Paare zusammenführen? Die jeweils markierten Quell-Kunden werden gelöscht — das kann nicht rückgängig gemacht werden.') }}"
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
                                CustomerDuplicateFinder::CONF_EXACT => 'badge-error',
                                CustomerDuplicateFinder::CONF_LIKELY => 'badge-warning',
                                default => 'badge-ghost',
                            };
                        @endphp
                        <span class="badge badge-sm {{ $confBadge }}">{{ $confidenceLabels[$conf] ?? $conf }}</span>
                        @foreach ($pair['reasons'] as $reason)
                            <span class="badge badge-sm badge-outline">{{ $reasonLabels[$reason] ?? $reason }}</span>
                        @endforeach
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th class="w-40">{{ __('Feld') }}</th>
                                    <th>
                                        <span class="badge badge-sm badge-success">{{ __('Bleibt') }}</span>
                                        <a href="{{ route('customers.show', $target) }}" class="link ml-1">{{ $target->name }}</a>
                                    </th>
                                    <th>
                                        <span class="badge badge-sm badge-ghost">{{ __('Wird gelöscht') }}</span>
                                        <a href="{{ route('customers.show', $source) }}" class="link ml-1">{{ $source->name }}</a>
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
                                    <td class="text-base-content/60">{{ __('Projekte') }}</td>
                                    <td>{{ (int) ($target->projects_count ?? 0) }}</td>
                                    <td>{{ (int) ($source->projects_count ?? 0) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 flex flex-wrap justify-end gap-2">
                        <a href="{{ route('customers.duplicates.compare', ['target' => $target->sqid, 'source' => $source->sqid]) }}"
                           class="btn btn-sm btn-ghost">{{ __('Felder wählen…') }}</a>
                        <form method="POST" action="{{ route('customers.duplicates.merge') }}"
                              data-confirm-dialog
                              data-confirm-message="{{ __('„:source“ endgültig in „:target“ zusammenführen? Der Quell-Kunde wird gelöscht.', ['source' => $source->name, 'target' => $target->name]) }}"
                              data-confirm-icon="merge" data-confirm-tone="primary" data-confirm-label="{{ __('Zusammenführen') }}">
                            @csrf
                            <input type="hidden" name="source" value="{{ $source->sqid }}">
                            <input type="hidden" name="target" value="{{ $target->sqid }}">
                            <button class="btn btn-sm btn-primary">{{ __('Zusammenführen →') }}</button>
                        </form>
                        <form method="POST" action="{{ route('customers.duplicates.merge') }}"
                              data-confirm-dialog
                              data-confirm-message="{{ __('Richtung tauschen: „:source“ in „:target“ zusammenführen? Der Quell-Kunde wird gelöscht.', ['source' => $target->name, 'target' => $source->name]) }}"
                              data-confirm-icon="swap_horiz" data-confirm-tone="warning" data-confirm-label="{{ __('Zusammenführen') }}">
                            @csrf
                            <input type="hidden" name="source" value="{{ $target->sqid }}">
                            <input type="hidden" name="target" value="{{ $source->sqid }}">
                            <button class="btn btn-sm btn-outline">{{ __('Umgekehrt') }}</button>
                        </form>
                        <form method="POST" action="{{ route('customers.duplicates.dismiss') }}">
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
