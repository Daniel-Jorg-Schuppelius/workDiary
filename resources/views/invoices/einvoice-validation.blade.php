{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : einvoice-validation.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Validierungsbericht (Feature 066, MVP-164): Preflight → XSD → KoSIT,
     verständlich VOR der Ausstellung — fehlende KoSIT-Umgebung wird
     transparent ausgewiesen, nie still übersprungen. --}}

@extends('layouts.app')
@section('title', __('E-Rechnungs-Validierung') . ' — ' . $invoice->number)
@section('nav-title', __('E-Rechnungs-Validierung'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:title>{{ __('E-Rechnungs-Validierung') }} — {{ $invoice->number }}</x-slot:title>
            <x-slot:subtitle>
                @if ($report['valid'] && $report['preflight_errors'] === [])
                    <x-status-badge tone="success" size="xs">{{ __('Bereit zur Ausstellung') }}</x-status-badge>
                @else
                    <x-status-badge tone="error" size="xs">{{ __('Nicht bestanden') }}</x-status-badge>
                @endif
            </x-slot:subtitle>
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('invoices.show', $invoice)" show-label>{{ __('Zur Rechnung') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card :title="__('1. Fachlicher Preflight (§ 14 UStG, EN-16931-Kernfelder)')">
        @if ($report['preflight_errors'] === [] && $report['preflight_warnings'] === [])
            <p class="text-sm text-success">{{ __('Keine Beanstandungen.') }}</p>
        @else
            <ul class="space-y-1 text-sm">
                @foreach ($report['preflight_errors'] as $error)
                    <li class="text-error">✖ {{ $error }}</li>
                @endforeach
                @foreach ($report['preflight_warnings'] as $warning)
                    <li class="text-warning">⚠ {{ $warning }}</li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card :title="__('2. UBL-2.1-Schema (XSD)')">
        @if (! $report['xml_generated'])
            <p class="text-sm text-base-content/60">{{ __('Übersprungen — der Preflight hat Fehler, es wurde kein XML erzeugt.') }}</p>
        @elseif ($report['schema_errors'] === [])
            <p class="text-sm text-success">{{ __('Schema-valide.') }}</p>
        @else
            <ul class="space-y-1 text-sm">
                @foreach ($report['schema_errors'] as $error)
                    <li class="text-error">✖ {{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card :title="__('3. EN-16931-Schematron + XRechnung-CIUS (KoSIT)')">
        @if (! $report['kosit_available'])
            <div class="alert alert-warning">
                <x-icon name="info" />
                <span>{{ __('KoSIT-Validator nicht verfügbar (Java-Laufzeit oder Validator-JAR fehlt) — die Regelprüfung wurde NICHT durchgeführt.') }}</span>
            </div>
        @elseif ($report['kosit_valid'])
            <p class="text-sm text-success">{{ __('KoSIT-Prüfung bestanden.') }}</p>
            @foreach ($report['kosit_warnings'] as $warning)
                <p class="text-sm text-warning">⚠ {{ $warning }}</p>
            @endforeach
        @else
            <ul class="space-y-1 text-sm">
                @foreach ($report['kosit_errors'] as $error)
                    <li class="text-error">✖ {{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card :title="__('4. Betragsabgleich XML/PDF (XRechnung vs. ZUGFeRD)')">
        @if (! $report['xml_generated'])
            <p class="text-sm text-base-content/60">{{ __('Übersprungen — der Preflight hat Fehler, es wurde kein XML erzeugt.') }}</p>
        @else
            @if ($report['consistency']['errors'] === [])
                <p class="text-sm text-success">
                    {{ __('Beleg, XRechnung-XML und visuelle Darstellung stimmen centgenau überein.') }}
                    @if ($report['consistency']['zugferd_checked'])
                        {{ __('Das im ZUGFeRD-PDF eingebettete CII wurde ebenfalls geprüft.') }}
                    @endif
                </p>
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($report['consistency']['errors'] as $error)
                        <li class="text-error">✖ {{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            @unless ($report['consistency']['zugferd_checked'])
                <p class="mt-2 text-sm text-base-content/60">{{ __('ZUGFeRD-Abgleich nicht durchgeführt (PDF-Toolkit/ZUGFeRD nicht verfügbar).') }}</p>
            @endunless
        @endif
    </x-card>
</x-page-shell>
@endsection
