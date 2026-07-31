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
        <p class="mb-3 text-sm text-base-content/60">
            {{ __('FRITZ!Box → Telefonie → Anrufe → Sichern (CSV). Nummern bekannter Kunden buchen automatisch; Anrufe, die eine gebuchte Fernwartungszeit desselben Kunden überlappen oder ihr bis zu :lead Minuten vorausgehen, verschmelzen mit dem bestehenden Eintrag. Gespräche unter :min Minuten und verpasste Anrufe werden ausgefiltert; unbekannte Nummern landen in der Zuordnungs-Inbox.', ['lead' => $leadMinutes, 'min' => $minCallMinutes]) }}
        </p>
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
                <p class="text-sm text-base-content/60">{{ __('Offene, noch nicht zugeordnete Import-Gruppen: :n', ['n' => $inboxOpenCount]) }}</p>
            </div>
            <x-icon-btn icon="inbox" tone="outline" size="sm" :href="route('admin.integration.inbox')" show-label>{{ __('Zur Inbox') }}</x-icon-btn>
        </div>
    </x-card>
</x-page-shell>
@endsection
