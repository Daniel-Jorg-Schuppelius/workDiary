@extends('layouts.app')

@section('title', __('Demo-Mandant') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Demo-Mandant'))

@php
    /** @var \App\Models\Organization $organization */
    /** @var bool $isEmpty */
    /** @var array<int, \App\Enums\Demo\DemoIndustry> $industries */
    /** @var \App\Enums\Demo\DemoIndustry $currentIndustry */
    $alreadySeeded = (bool) $organization->is_demo;
@endphp

@section('content')
<x-index-page
    :subtitle="$organization->name"
    :badge="$alreadySeeded ? __('Aktiv') : __('Nicht aktiv')"
    :badge-tone="$alreadySeeded ? 'warning' : 'ghost'"
>
    <x-slot:note>{{ __('Beispieldaten zum Vorführen, Testen und Onboarden. Erzeugt Kunden, Projekte, einen vollständigen Beispielauftrag und Hintergrund-Aufträge der letzten 60 Tage.') }}</x-slot:note>

    <x-validation-errors />

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
                <form method="POST" action="{{ route('admin.demo.seed') }}" class="flex flex-col gap-2 md:flex-row md:items-end">
                    @csrf
                    <x-select-field name="industry" :label="__('Musterbranche')" class="select-sm" :disabled="! $isEmpty">
                        @foreach ($industries as $industry)
                            <option value="{{ $industry->value }}" @selected($currentIndustry->value === $industry->value)>
                                {{ $industry->label() }}
                            </option>
                        @endforeach
                    </x-select-field>
                    <button type="submit"
                            class="btn btn-primary w-full md:w-auto"
                            :disabled="! $isEmpty">
                        <x-icon name="play_arrow" />
                        {{ __('Demo-Daten erzeugen') }}
                    </button>
                </form>

                @can(\App\Enums\User\Permission::PlatformDemoReset->value)
                    <x-action-form :action="route('admin.demo.reset')" class="contents"
                          :confirm="__('Wirklich alle Demo-Daten löschen und neu erzeugen?')"
                          confirm-icon="refresh"
                          confirm-tone="warning"
                          :confirm-label="__('Zurücksetzen')">
                        <button type="submit"
                                class="btn btn-warning w-full md:w-auto"
                                @disabled(! $alreadySeeded)>
                            <x-icon name="refresh" />
                            {{ __('Demo-Mandant zurücksetzen') }}
                        </button>
                    </x-action-form>
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
            <p class="text-xs text-base-content/60">{{ __('Inhalt richtet sich nach der gewählten Musterbranche und installiert das passende Branchenprofil.') }}</p>
            <ul class="space-y-1 text-sm text-base-content/80">
                <li>{{ __('Branchenprofil je Musterbranche (Klassifikationen, Tags, SLAs, Prozeduren)') }}</li>
                <li>{{ __('3 Demo-Kunden und 5 Demo-Projekte') }}</li>
                <li>{{ __('6 Demo-Nutzer mit unterschiedlichen Rollen (Admin, Operator, Disponent, Buchhaltung, Read-Only)') }}</li>
                <li>{{ __('1 branchenspezifischer Hauptauftrag mit 3 Zeiterfassungen, Material, Asset und 1 offenem Punkt') }}</li>
                <li>{{ __('1 signiertes Abnahmeprotokoll mit Prüfpunkten und 1 Kommunikationseintrag') }}</li>
                <li>{{ __('25 Hintergrund-Aufträge der letzten 60 Tage in verschiedenen Stati') }}</li>
            </ul>
        </div>
    </article>
</x-index-page>
@endsection
