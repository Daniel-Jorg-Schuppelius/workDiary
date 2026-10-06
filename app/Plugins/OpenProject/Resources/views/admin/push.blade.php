{{--
  Created on   : Tue Jun 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : push.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('OpenProject – Zeiten zurückbuchen'))
@section('nav-title', __('OpenProject'))

@section('content')
<x-index-page :title="__('Zeiten nach OpenProject zurückbuchen')" :subtitle="__('Nicht-exportierte Projekt-Zeiten, deren Projekt einem OpenProject-Projekt zugeordnet ist, werden als Zeiteinträge zurückgebucht. Aufgaben werden — sofern zugeordnet — als Work Package gebucht. Bereits gebuchte Einträge werden übersprungen.')" back-route="admin.openproject.index" :back-label="__('Zurück')">
    <x-card>
        <x-validation-errors first class="mb-3" />

        <form method="POST" action="{{ route('admin.openproject.push.run') }}" class="space-y-3">
            @csrf
            <x-date-range fromName="date_from" toName="date_to"
                          :from="old('date_from')" :to="old('date_to')"
                          :label="__('Zeitraum (optional)')" />
            <p class="text-xs text-muted">{{ __('Leer lassen, um alle offenen Einträge zu buchen.') }}</p>
            <div class="flex justify-end">
                <x-button type="submit">{{ __('Jetzt zurückbuchen') }}</x-button>
            </div>
        </form>
    </x-card>

    @if ($summary)
        <x-card>
            <h2 class="mb-3 font-['Space_Grotesk'] text-base font-semibold">{{ __('Letzte Rückbuchung') }}</h2>
            <div class="flex flex-wrap gap-4 text-sm">
                <span><span class="font-semibold">{{ $summary['pushed'] }}</span> {{ __('zurückgebucht') }}</span>
                <span><span class="font-semibold">{{ $summary['skipped'] }}</span> {{ __('übersprungen') }}</span>
                <span><span class="font-semibold">{{ $summary['failed'] }}</span> {{ __('fehlgeschlagen') }}</span>
            </div>
            @if (! empty($summary['errors']))
                <ul class="mt-3 list-inside list-disc space-y-1 text-xs text-error">
                    @foreach ($summary['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    @endif
</x-index-page>
@endsection
