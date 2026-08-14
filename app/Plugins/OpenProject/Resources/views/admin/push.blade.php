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
<x-page-shell>
    <div class="space-y-4">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('Zeiten nach OpenProject zurückbuchen') }}</h1>
                <a href="{{ route('admin.openproject.index') }}" class="btn btn-ghost btn-sm">{{ __('Zurück') }}</a>
            </div>
            <p class="mb-4 text-sm text-base-content/60">
                {{ __('Nicht-exportierte Projekt-Zeiten, deren Projekt einem OpenProject-Projekt zugeordnet ist, werden als Zeiteinträge zurückgebucht. Aufgaben werden — sofern zugeordnet — als Work Package gebucht. Bereits gebuchte Einträge werden übersprungen.') }}
            </p>

            @if (session('status'))
                <div class="alert alert-success mb-3 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error mb-3 text-sm">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.openproject.push.run') }}" class="space-y-3">
                @csrf
                <x-date-range fromName="date_from" toName="date_to"
                              :from="old('date_from')" :to="old('date_to')"
                              :label="__('Zeitraum (optional)')" />
                <p class="text-xs text-base-content/60">{{ __('Leer lassen, um alle offenen Einträge zu buchen.') }}</p>
                <div class="flex justify-end">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Jetzt zurückbuchen') }}</button>
                </div>
            </form>
        </div>

        @if ($summary)
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
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
            </div>
        @endif
    </div>
</x-page-shell>
@endsection
