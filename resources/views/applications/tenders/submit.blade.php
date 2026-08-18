{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : submit.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Abgabe: :title', ['title' => $opportunity->title]))
@section('nav-title', __('Angebotsabgabe'))

@php
    /** @var \App\Models\Applications\ApplicationOpportunity $opportunity */
    /** @var list<array{severity: string, code: string, message: string}> $findings */
    $blockers = array_values(array_filter($findings, static fn (array $f): bool => $f['severity'] === 'blocker'));
    $warnings = array_values(array_filter($findings, static fn (array $f): bool => $f['severity'] === 'warning'));
@endphp

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :badge="__('values.' . $opportunity->status)" badge-tone="outline">
            <div class="text-sm text-base-content/70">
                {{ $opportunity->title }}
                @if ($opportunity->submission_deadline) · {{ __('Abgabefrist: :date', ['date' => $opportunity->submission_deadline->fdate()]) }} @endif
            </div>
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('tenders.show', $opportunity)" show-label>{{ __('Zur Akte') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- Schritt 1: prüfen. Nachbessern ist im Vergaberecht die Ausnahme —
         was hier auffällt, ist nach der Abgabe nicht mehr zu heilen. --}}
    <x-card :title="__('1. Prüfung')">
        @if (empty($findings))
            <div class="alert alert-success">
                <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
                <span>{{ __('Keine Beanstandungen. Die Akte ist abgabebereit.') }}</span>
            </div>
        @else
            @foreach ($blockers as $finding)
                <div class="alert alert-error mb-2">
                    <span class="material-symbols-outlined" aria-hidden="true">block</span>
                    <span>{{ $finding['message'] }}</span>
                </div>
            @endforeach
            @foreach ($warnings as $finding)
                <div class="alert alert-warning mb-2">
                    <span class="material-symbols-outlined" aria-hidden="true">warning</span>
                    <span>{{ $finding['message'] }}</span>
                </div>
            @endforeach
            @if (! empty($warnings) && empty($blockers))
                <p class="mt-2 text-sm text-base-content/70">
                    {{ __('Hinweise halten die Abgabe nicht auf — die Einreichung wird hier dokumentiert, oft am Tag danach.') }}
                </p>
            @endif
        @endif
    </x-card>

    {{-- Schritt 2: ausgeben. Der Export ist die Datei, die die Vergabestelle
         bekommt — nicht die Akte. --}}
    <x-card :title="__('2. Ausgabe')">
        @if ($opportunity->billOfQuantity)
            <p class="mb-3 text-sm text-base-content/70">
                {{ __('Leistungsverzeichnis: :name', ['name' => $opportunity->billOfQuantity->name]) }}
                @if ($opportunity->billOfQuantity->phase)
                    · {{ $opportunity->billOfQuantity->phase->value }}
                @endif
            </p>
            <div class="flex flex-wrap gap-2">
                <x-icon-btn icon="download" size="sm" show-label
                            :href="route('bill-of-quantities.export', [$opportunity->billOfQuantity, 'phase' => '84'])">
                    {{ __('GAEB X84 (Angebotsabgabe)') }}
                </x-icon-btn>
                <x-icon-btn icon="table_view" size="sm" show-label
                            :href="route('bill-of-quantities.show', $opportunity->billOfQuantity)">
                    {{ __('Leistungsverzeichnis öffnen') }}
                </x-icon-btn>
            </div>
        @else
            <x-empty-state icon="request_quote" compact
                           :title="__('Kein Leistungsverzeichnis an der Akte.')"
                           :message="__('Ohne LV gibt es keinen GAEB-Ausgang — die Abgabe erfolgt dann über die Unterlagen der Vergabestelle.')" />
        @endif
    </x-card>

    {{-- Schritt 3: dokumentieren. Der Snapshot ist der Nachweis, was
         eingereicht wurde. --}}
    <x-card :title="__('3. Einreichung dokumentieren')">
        <form method="POST" action="{{ route('tenders.submit', $opportunity) }}" class="flex flex-wrap items-end gap-2">
            @csrf
            <label class="fieldset">
                <span class="label-text text-xs">{{ __('Kanal') }}</span>
                <select name="channel" class="select select-sm select-bordered w-44">
                    <option value="portal">{{ __('values.portal') }}</option>
                    <option value="email">{{ __('values.email') }}</option>
                    <option value="paper">{{ __('values.paper') }}</option>
                    <option value="other">{{ __('values.other') }}</option>
                </select>
            </label>
            <label class="fieldset flex-1">
                <span class="label-text text-xs">{{ __('Anmerkung') }}</span>
                <input name="note" maxlength="500" class="input input-sm input-bordered w-full"
                       placeholder="{{ __('z. B. Eingangsbestätigung Nr. …') }}">
            </label>
            <x-icon-btn icon="outbox" tone="primary" size="sm" type="submit" show-label :disabled="$blocked"
                        :title="$blocked ? __('Erst die Sperren beheben.') : __('Einreichung als versionierten Snapshot mit Hash dokumentieren')">
                {{ __('Einreichung dokumentieren') }}
            </x-icon-btn>
        </form>

        @if ($opportunity->submissions->isNotEmpty())
            <x-table bare class="mt-4">
                <x-slot:head>
                    <tr>
                        <th>{{ __('Version') }}</th>
                        <th>{{ __('Zeitpunkt') }}</th>
                        <th>{{ __('Kanal') }}</th>
                        <th>{{ __('SHA-256') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($opportunity->submissions as $submission)
                    <tr>
                        <td>V{{ $submission->version }}</td>
                        <td>{{ $submission->created_at->fdatetime() }}</td>
                        <td>{{ __("values.{$submission->channel}") }}</td>
                        <td class="font-mono text-xs">{{ substr($submission->sha256, 0, 16) }}…</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection
