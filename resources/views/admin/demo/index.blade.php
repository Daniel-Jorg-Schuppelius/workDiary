@extends('layouts.app')

@section('title', __('Demo-Mandant') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Demo-Mandant'))

@php
    /** @var \App\Models\Organization $organization */
    /** @var bool $isEmpty */
    $alreadySeeded = (bool) $organization->is_demo;
@endphp

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar
            :subtitle="$organization->name"
            :badge="$alreadySeeded ? __('Aktiv') : __('Nicht aktiv')"
            :badge-tone="$alreadySeeded ? 'warning' : 'ghost'"
        >
            {{ __('Beispieldaten zum Vorführen, Testen und Onboarden. Erzeugt Kunden, Projekte, einen vollständigen Beispielauftrag und Hintergrund-Aufträge der letzten 60 Tage.') }}
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($errors->any())
        <div class="alert alert-error">
            <ul class="text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <article class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Aktion wählen') }}</h2>

            @if (! $alreadySeeded && ! $isEmpty)
                <div class="alert alert-warning">
                    <x-icon name="warning" />
                    <span class="text-sm">
                        {{ __('Diese Organisation enthält bereits Echtdaten. Demo-Seeding ist hier nicht zulässig — lege einen frischen Mandanten an.') }}
                    </span>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <form method="POST" action="{{ route('admin.demo.seed') }}" class="contents">
                    @csrf
                    <button type="submit"
                            class="btn btn-primary w-full md:w-auto"
                            @disabled(! $isEmpty)>
                        <x-icon name="play_arrow" />
                        {{ __('Demo-Daten erzeugen') }}
                    </button>
                </form>

                @can(\App\Enums\User\Permission::PlatformDemoReset->value)
                    <form method="POST" action="{{ route('admin.demo.reset') }}" class="contents"
                          onsubmit="return confirm('{{ __('Wirklich alle Demo-Daten löschen und neu erzeugen?') }}');">
                        @csrf
                        <button type="submit"
                                class="btn btn-warning w-full md:w-auto"
                                @disabled(! $alreadySeeded)>
                            <x-icon name="refresh" />
                            {{ __('Demo-Mandant zurücksetzen') }}
                        </button>
                    </form>
                @endcan
            </div>

            @if ($alreadySeeded && $organization->demo_seeded_at)
                <p class="text-xs text-base-content/60">
                    {{ __('Letzter Seed: :at', ['at' => $organization->demo_seeded_at->translatedFormat('d.m.Y H:i')]) }}
                </p>
            @endif
        </div>
    </article>

    <article class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Inhalt des Demo-Mandanten') }}</h2>
            <ul class="space-y-1 text-sm text-base-content/80">
                <li>{{ __('3 Demo-Kunden (ACME GmbH, Beispiel-Apotheke, Mustermann KG)') }}</li>
                <li>{{ __('5 Demo-Projekte') }}</li>
                <li>{{ __('6 Demo-Nutzer mit unterschiedlichen Rollen (Admin, Operator, Disponent, Buchhaltung, Read-Only)') }}</li>
                <li>{{ __('1 Hauptauftrag „Server-Migration ACME" mit 3 Zeiterfassungen und 1 offenem Punkt') }}</li>
                <li>{{ __('25 Hintergrund-Aufträge der letzten 60 Tage') }}</li>
            </ul>
        </div>
    </article>
</x-page-shell>
@endsection
