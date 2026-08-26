{{--
  Created on   : Thu Jul 31 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : import.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  FRITZ!Box-Anruflisten-Import: CSV-Export hochladen; Telefonate werden als
  Zeiteinträge gebucht bzw. mit Fernwartungszeiten verschmolzen.
--}}

@extends('layouts.app')
@section('title', __('FRITZ!Box-Import'))
@section('nav-title', __('FRITZ!Box-Import'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ __('FRITZ!Box-Import') }}</x-slot:title>
        <x-slot:subtitle>{{ __('Telefonate aus der FRITZ!Box-Anrufliste als Zeiteinträge übernehmen.') }}</x-slot:subtitle>
    </x-page-toolbar>

    @if (session('status'))
        <div class="alert alert-success text-sm">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-error text-sm">{{ $errors->first() }}</div>
    @endif

    <x-card>
        <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('Anrufliste hochladen') }}</h2>
        <p class="mb-3 text-sm text-muted">
            {{ __('FRITZ!Box → Telefonie → Anrufe → Sichern (CSV). Nummern bekannter Kunden buchen automatisch; Anrufe, die eine gebuchte Fernwartungszeit desselben Kunden überlappen oder ihr bis zu :lead Minuten vorausgehen, verschmelzen mit dem bestehenden Eintrag. Gespräche unter :min Minuten und verpasste Anrufe werden ausgefiltert; unbekannte Nummern landen in der Zuordnungs-Inbox.', ['lead' => $leadMinutes, 'min' => $minCallMinutes]) }}
        </p>
        <div class="mb-3 flex items-start gap-2 rounded-box bg-base-200 p-3 text-sm">
            <x-icon name="contact_phone" class="mt-0.5 shrink-0 text-info" />
            <div>
                <span class="font-medium">{{ __('Kontaktabgleich') }}</span>
                @if ($contactDirectorySources !== [])
                    <span class="text-base-content/70">{{ __('Aktive Kontaktquellen: :sources.', ['sources' => implode(', ', $contactDirectorySources)]) }}</span>
                @else
                    <span class="text-base-content/70">{{ __('Keine externe Kontaktquelle verbunden. Lokale Kunden und Endkunden werden weiterhin abgeglichen.') }}</span>
                @endif
            </div>
        </div>
        <form method="POST" action="{{ route('admin.fritzbox.import-csv') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2">
            @csrf
            <input type="file" name="csv" accept=".csv,.txt" class="file-input file-input-bordered file-input-sm" required>
            <x-icon-btn icon="upload" tone="primary" size="sm" type="submit" show-label>{{ __('Importieren') }}</x-icon-btn>
        </form>
    </x-card>

    <x-card>
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Zuordnungs-Inbox') }}</h2>
                <p class="text-sm text-muted">{{ __('Offene, noch nicht zugeordnete Import-Gruppen: :n', ['n' => $inboxOpenCount]) }}</p>
            </div>
            <x-icon-btn icon="inbox" tone="outline" size="sm" :href="route('admin.integration.inbox')" show-label>{{ __('Zur Inbox') }}</x-icon-btn>
        </div>
    </x-card>

    {{-- Telefonstempeln (Feature 103, MVP-534) --}}
    <x-card>
        <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('Telefonstempeln') }}</h2>
        <p class="mb-3 text-sm text-muted">
            @if ($stampLinesActive === [])
                {{ __('Keine Stempel-Rufnummer konfiguriert — in den Plugin-Einstellungen eine eigene Rufnummer für Kommen/Gehen hinterlegen. Der Anruf wird nicht angenommen; die Rufnummer des Anrufenden wirkt als Ausweis.') }}
            @else
                {{ __('Aktive Stempel-Rufnummern: :lines. Anrufe darauf werden beim Import zu Kommen-/Gehen-Stempeln; die Rufnummer des Anrufenden wirkt als Ausweis.', ['lines' => implode(', ', $stampLinesActive)]) }}
            @endif
        </p>

        @if ($stampNumbers->isNotEmpty())
            <x-table class="mb-3">
                <x-slot:head>
                    <tr>
                        <th>{{ __('Rufnummer') }}</th>
                        <th>{{ __('Mitarbeiter') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($stampNumbers as $reference)
                    <tr>
                        <td class="font-mono text-xs">{{ $reference->external_id }}</td>
                        <td>{{ $reference->referenceable?->name ?? '—' }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('admin.fritzbox.stamp-numbers.destroy') }}" class="inline">
                                @csrf @method('DELETE')
                                <input type="hidden" name="number" value="{{ $reference->external_id }}">
                                <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('Entfernen') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif

        <form method="POST" action="{{ route('admin.fritzbox.stamp-numbers.store') }}" class="flex flex-wrap items-end gap-2">
            @csrf
            <label class="form-control">
                <span class="label-text">{{ __('Mitarbeiter') }}</span>
                <select name="user" class="select select-bordered select-sm" required>
                    @foreach ($stampUserOptions as $option)
                        <option value="{{ $option['sqid'] }}">{{ $option['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-control grow">
                <span class="label-text">{{ __('Rufnummer') }}</span>
                <input type="text" name="number" class="input input-bordered input-sm" placeholder="+49 151 2345678" required>
            </label>
            <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Zuordnen') }}</x-icon-btn>
        </form>
    </x-card>
</x-page-shell>
@endsection
