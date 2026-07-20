@extends('layouts.app')
@section('title', __('Projekte zusammenführen'))
@section('nav-title', __('Projekte zusammenführen'))

@php
    /** @var \App\Models\Project $source */
    /** @var \App\Models\Project $target */

    // Reine Anzeigefelder (Identität) — nicht übersteuerbar.
    $identityFields = [
        'name' => __('Name'),
    ];
    // Übersteuerbare Felder — müssen mit ProjectMergeService::FILLABLE_FROM_SOURCE
    // übereinstimmen, sonst ignoriert der Service die Auswahl.
    $overridableFields = [
        'number' => __('Projektnr.'),
        'description' => __('Beschreibung'),
        'invoice_text' => __('Rechnungstext'),
        'color' => __('Farbe'),
        'hourly_rate' => __('Stundensatz'),
        'internal_rate' => __('Interner Satz'),
        'time_budget' => __('Zeitbudget (Min.)'),
        'budget' => __('Budget'),
        'budget_type' => __('Budget-Typ'),
        'billing_increment_minutes' => __('Abr.-Taktung (Min.)'),
        'billing_grouping_gap_minutes' => __('Zusammenfassungs-Lücke (Min.)'),
        'starts_on' => __('Beginn'),
        'ends_on' => __('Ende'),
    ];
    $fmt = static function ($v): string {
        if ($v instanceof \Illuminate\Support\Carbon) {
            return $v->format('d.m.Y');
        }

        return (string) ($v ?? '');
    };
@endphp

@section('content')
<x-index-page :subtitle="__('Wähle pro Feld, ob der Wert des zu löschenden Projekts den Ziel-Wert ersetzen soll. Nicht angehakte, leere Ziel-Felder werden ohnehin aus der Quelle aufgefüllt; befüllte Ziel-Felder bleiben unangetastet.')">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('projects.duplicates.index')" show-label>{{ __('Zurück') }}</x-icon-btn>
    </x-slot:actions>

    <div class="mb-4 flex flex-wrap gap-x-6 gap-y-1 text-sm text-base-content/70">
        <span>{{ __('Kunde') }}: <span class="font-medium">{{ $target->customer?->name ?: __('Intern (ohne Kunde)') }}</span></span>
        @if ($target->foreignCustomer || $source->foreignCustomer)
            <span>{{ __('Endkunde') }}:
                <span class="font-medium">{{ $target->foreignCustomer?->name ?: '—' }}</span>
                @if (($target->foreign_customer_id ?? null) !== ($source->foreign_customer_id ?? null))
                    <span class="text-warning">({{ __('Quelle') }}: {{ $source->foreignCustomer?->name ?: '—' }})</span>
                @endif
            </span>
        @endif
    </div>

    <form method="POST" action="{{ route('projects.duplicates.merge') }}"
          data-confirm-dialog
          data-confirm-message="{{ __('„:source“ endgültig in „:target“ zusammenführen? Das Quell-Projekt wird gelöscht.', ['source' => $source->name, 'target' => $target->name]) }}"
          data-confirm-icon="merge" data-confirm-tone="primary" data-confirm-label="{{ __('Zusammenführen') }}">
        @csrf
        <input type="hidden" name="source" value="{{ $source->sqid }}">
        <input type="hidden" name="target" value="{{ $target->sqid }}">

        <x-table>
            <x-slot:head>
                    <tr>
                        <th class="w-44">{{ __('Feld') }}</th>
                        <th>
                            <span class="badge badge-sm badge-success">{{ __('Bleibt') }}</span>
                            <a href="{{ route('projects.show', $target) }}" class="link ml-1">{{ $target->name }}</a>
                        </th>
                        <th>
                            <span class="badge badge-sm badge-ghost">{{ __('Wird gelöscht') }}</span>
                            <a href="{{ route('projects.show', $source) }}" class="link ml-1">{{ $source->name }}</a>
                        </th>
                        <th class="w-40 text-center">{{ __('Wert aus Quelle übernehmen') }}</th>
                    </tr>
            </x-slot:head>
                    @foreach ($identityFields as $field => $label)
                        @php
                            $tv = $fmt($target->getAttribute($field));
                            $sv = $fmt($source->getAttribute($field));
                        @endphp
                        <tr>
                            <td class="text-base-content/60">{{ $label }}</td>
                            <td>{{ $tv !== '' ? $tv : '—' }}</td>
                            <td class="{{ $tv !== $sv ? 'text-warning' : 'text-base-content/50' }}">{{ $sv !== '' ? $sv : '—' }}</td>
                            <td class="text-center text-base-content/30">—</td>
                        </tr>
                    @endforeach

                    @foreach ($overridableFields as $field => $label)
                        @php
                            $tv = $fmt($target->getAttribute($field));
                            $sv = $fmt($source->getAttribute($field));
                        @endphp
                        @if ($tv !== '' || $sv !== '')
                            <tr>
                                <td class="text-base-content/60">{{ $label }}</td>
                                <td class="{{ $tv === '' ? 'text-base-content/30' : '' }}">{{ $tv !== '' ? $tv : '—' }}</td>
                                <td class="{{ $tv !== $sv ? 'text-warning' : 'text-base-content/50' }}">{{ $sv !== '' ? $sv : '—' }}</td>
                                <td class="text-center">
                                    @if ($sv !== '' && $tv !== $sv)
                                        <input type="checkbox" class="checkbox checkbox-sm"
                                               name="prefer_source[]" value="{{ $field }}">
                                    @else
                                        <span class="text-base-content/30">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
        </x-table>

        <div class="mt-4 flex flex-wrap justify-end gap-2">
            <a href="{{ route('projects.duplicates.compare', ['target' => $source->sqid, 'source' => $target->sqid]) }}"
               class="btn btn-sm btn-outline">{{ __('Richtung tauschen') }}</a>
            <button class="btn btn-sm btn-primary">{{ __('Zusammenführen →') }}</button>
        </div>
    </form>
</x-index-page>
@endsection
