{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : duplicates.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Lieferanten-Abgleich'))
@section('nav-title', __('Lieferanten-Abgleich'))

@php
    use App\Services\SupplierDuplicateFinder;

    // Schlüssel entsprechen den Strategien des SupplierMatchProfile.
    $reasonLabels = [
        'vat_id' => __('USt-IdNr.'),
        'vendor_number' => __('Lieferantennr.'),
        'email' => __('E-Mail'),
        'company_zip' => __('Firma + PLZ'),
        'name' => __('Name/Firma ähnlich'),
    ];
    $confidenceLabels = [
        SupplierDuplicateFinder::CONF_EXACT  => __('Eindeutig'),
        SupplierDuplicateFinder::CONF_LIKELY => __('Wahrscheinlich'),
        SupplierDuplicateFinder::CONF_FUZZY  => __('Möglich'),
    ];
    // Felder, die im Vergleich gegenübergestellt werden.
    $compareFields = [
        'name' => __('Name'),
        'company' => __('Firma'),
        'number' => __('Nummer'),
        'vendor_number' => __('Lieferantennr.'),
        'vat_id' => __('USt-IdNr.'),
        'email' => __('E-Mail'),
        'address_zip' => __('PLZ'),
        'address_city' => __('Ort'),
    ];
@endphp

@section('content')
<x-index-page :subtitle="__('Doppelte Lieferanten (z. B. aus Import, Lexoffice-Sync und manueller Anlage) werden hier gegenübergestellt. Pro Paar entscheidest du, welcher Datensatz bestehen bleibt — Bestellungen, Kataloge, Eingangsrechnungen und Referenzen werden auf ihn umgehängt, der andere wird gelöscht.')">
    <x-slot:actions>
        <form method="GET" action="{{ route('suppliers.duplicates.index') }}" class="flex items-center gap-2">
            <select name="confidence" class="select select-sm select-bordered" data-autosubmit>
                <option value="all" @selected($confidence === 'all')>{{ __('Alle Stufen') }}</option>
                <option value="{{ SupplierDuplicateFinder::CONF_EXACT }}"  @selected($confidence === SupplierDuplicateFinder::CONF_EXACT)>{{ $confidenceLabels[SupplierDuplicateFinder::CONF_EXACT] }}</option>
                <option value="{{ SupplierDuplicateFinder::CONF_LIKELY }}" @selected($confidence === SupplierDuplicateFinder::CONF_LIKELY)>{{ $confidenceLabels[SupplierDuplicateFinder::CONF_LIKELY] }}</option>
                <option value="{{ SupplierDuplicateFinder::CONF_FUZZY }}"  @selected($confidence === SupplierDuplicateFinder::CONF_FUZZY)>{{ $confidenceLabels[SupplierDuplicateFinder::CONF_FUZZY] }}</option>
            </select>
        </form>
    </x-slot:actions>

    <x-card class="mb-4">
        <details @if (session('manual_open')) open @endif>
            <summary class="cursor-pointer text-sm font-medium">
                {{ __('Manuell zusammenführen') }}
                <span class="ml-1 text-muted">{{ __('— zwei Lieferanten frei wählen (für Dubletten, die der Abgleich nicht erkennt)') }}</span>
            </summary>
            <form method="GET" action="{{ route('suppliers.duplicates.compare') }}"
                  class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end">
                <div class="fieldset">
                    <label class="fieldset-label" for="manual-target">
                        <span class="badge badge-xs badge-success mr-1">{{ __('Bleibt') }}</span>{{ __('Ziel-Lieferant') }}
                    </label>
                    <select name="target" id="manual-target" required class="select select-bordered w-full">
                        <option value="">{{ __('— wählen —') }}</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->sqid }}">{{ $supplier->name }}@if ($supplier->number) ({{ $supplier->number }})@endif</option>
                        @endforeach
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="manual-source">
                        <span class="badge badge-xs badge-error mr-1">{{ __('Wird gelöscht') }}</span>{{ __('Quell-Lieferant') }}
                    </label>
                    <select name="source" id="manual-source" required class="select select-bordered w-full">
                        <option value="">{{ __('— wählen —') }}</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->sqid }}">{{ $supplier->name }}@if ($supplier->number) ({{ $supplier->number }})@endif</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-sm btn-primary">{{ __('Vergleichen →') }}</button>
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
                    <button type="button" class="btn btn-sm btn-ghost" @click="clear()">{{ __('Auswahl leeren') }}</button>
                    <form method="POST" action="{{ route('suppliers.duplicates.bulk-merge') }}"
                          data-confirm-dialog
                          data-confirm-message="{{ __('Alle ausgewählten Paare zusammenführen? Die jeweils markierten Quell-Lieferanten werden gelöscht — das kann nicht rückgängig gemacht werden.') }}"
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
                <x-card>
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <input type="checkbox" class="checkbox checkbox-sm" value="{{ $pairKey }}" x-model="selected"
                               aria-label="{{ __('Für Bulk-Zusammenführung auswählen') }}">
                        @php
                            $confBadge = match ($conf) {
                                SupplierDuplicateFinder::CONF_EXACT => 'badge-error',
                                SupplierDuplicateFinder::CONF_LIKELY => 'badge-warning',
                                default => 'badge-ghost',
                            };
                        @endphp
                        <span class="badge badge-sm {{ $confBadge }}">{{ $confidenceLabels[$conf] ?? $conf }}</span>
                        @foreach ($pair['reasons'] as $reason)
                            <span class="badge badge-sm badge-outline">{{ $reasonLabels[$reason] ?? $reason }}</span>
                        @endforeach
                    </div>

                    <x-table>
                        <x-slot:head>
                                <tr>
                                    <th class="w-40">{{ __('Feld') }}</th>
                                    <th>
                                        <span class="badge badge-sm badge-success">{{ __('Bleibt') }}</span>
                                        <a href="{{ route('suppliers.show', $target) }}" class="link ml-1">{{ $target->name }}</a>
                                    </th>
                                    <th>
                                        <span class="badge badge-sm badge-ghost">{{ __('Wird gelöscht') }}</span>
                                        <a href="{{ route('suppliers.show', $source) }}" class="link ml-1">{{ $source->name }}</a>
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
                                    <td class="text-muted">{{ __('Projekte') }}</td>
                                    <td>{{ (int) ($target->projects_count ?? 0) }}</td>
                                    <td>{{ (int) ($source->projects_count ?? 0) }}</td>
                                </tr>
                    </x-table>

                    <div class="mt-3 flex flex-wrap justify-end gap-2">
                        <a href="{{ route('suppliers.duplicates.compare', ['target' => $target->sqid, 'source' => $source->sqid]) }}"
                           class="btn btn-sm btn-ghost">{{ __('Felder wählen…') }}</a>
                        <form method="POST" action="{{ route('suppliers.duplicates.merge') }}"
                              data-confirm-dialog
                              data-confirm-message="{{ __('„:source“ endgültig in „:target“ zusammenführen? Der Quell-Lieferant wird gelöscht.', ['source' => $source->name, 'target' => $target->name]) }}"
                              data-confirm-icon="merge" data-confirm-tone="primary" data-confirm-label="{{ __('Zusammenführen') }}">
                            @csrf
                            <input type="hidden" name="source" value="{{ $source->sqid }}">
                            <input type="hidden" name="target" value="{{ $target->sqid }}">
                            <button class="btn btn-sm btn-primary">{{ __('Zusammenführen →') }}</button>
                        </form>
                        <form method="POST" action="{{ route('suppliers.duplicates.merge') }}"
                              data-confirm-dialog
                              data-confirm-message="{{ __('Richtung tauschen: „:source“ in „:target“ zusammenführen? Der Quell-Lieferant wird gelöscht.', ['source' => $target->name, 'target' => $source->name]) }}"
                              data-confirm-icon="swap_horiz" data-confirm-tone="warning" data-confirm-label="{{ __('Zusammenführen') }}">
                            @csrf
                            <input type="hidden" name="source" value="{{ $target->sqid }}">
                            <input type="hidden" name="target" value="{{ $source->sqid }}">
                            <button class="btn btn-sm btn-outline">{{ __('Umgekehrt') }}</button>
                        </form>
                        <form method="POST" action="{{ route('suppliers.duplicates.dismiss') }}">
                            @csrf
                            <input type="hidden" name="source" value="{{ $source->sqid }}">
                            <input type="hidden" name="target" value="{{ $target->sqid }}">
                            <button class="btn btn-sm btn-ghost">{{ __('Kein Duplikat') }}</button>
                        </form>
                    </div>
                </x-card>
            @endforeach
            </div>
        </div>
    @endif
</x-index-page>
@endsection
