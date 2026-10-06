{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Lückenanalyse'))
@section('nav-title', __('Compliance- & Vertragslücken'))
@section('content')
    <x-index-page :subtitle="__('Lücken in Verträgen und Compliance-Anforderungen aufdecken und bewerten.')">
        <x-slot:actions>
            @can('manage', \App\Models\Privacy\ComplianceFinding::class)
                <form method="post" action="{{ route('dataprotection.compliance.run') }}">@csrf
                    <x-icon-btn icon="rule" tone="primary" size="sm" type="submit" show-label>{{ __('Analyse jetzt ausführen') }}</x-icon-btn>
                </form>
            @endcan
        </x-slot:actions>

        <x-validation-errors />

        {{-- Ampel --}}
        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                @foreach (\App\Enums\Privacy\ComplianceFindingStatus::cases() as $status)
                    @if (($counts[$status->value] ?? 0) > 0)
                        <x-status-badge :tone="$status->tone()" size="lg" class="gap-2">{{ $status->label() }} <span class="font-bold">{{ $counts[$status->value] }}</span></x-status-badge>
                    @endif
                @endforeach
                @if ($findings->total() === 0)
                    <span class="text-sm text-muted">{{ __('Noch keine Analyse ausgeführt.') }}</span>
                @endif
            </div>
        </x-card>

        {{-- Kein scroll=flex: unter der Tabelle folgt der Anforderungskatalog —
             die Seite scrollt normal (Vollscan 2026-08 I11). --}}
        <x-table>
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('Anforderung') }}</x-table.th>
                    <x-table.th>{{ __('Status') }}</x-table.th>
                    <x-table.th>{{ __('Auslöser') }}</x-table.th>
                    <x-table.th>{{ __('Bezug') }}</x-table.th>
                    @can('manage', \App\Models\Privacy\ComplianceFinding::class)<x-table.th>{{ __('Entscheidung') }}</x-table.th>@endcan
                </tr>
            </x-slot:head>
            @forelse ($findings as $f)
                <tr class="hover">
                    <td>{{ $f->label }}</td>
                    <td><x-status-badge :tone="$f->status->tone()" size="sm">{{ $f->status->label() }}</x-status-badge></td>
                    <td class="text-sm">{{ $f->trigger ?? '—' }}</td>
                    <td class="text-sm">
                        @if ($f->activity)<a class="link" href="{{ route('dataprotection.activities.show', $f->activity) }}">{{ $f->activity->name }}</a>
                        @elseif ($f->agreement)<a class="link" href="{{ route('dataprotection.agreements.show', $f->agreement) }}">{{ $f->agreement->title }}</a>
                        @elseif ($f->processor)<a class="link" href="{{ route('dataprotection.processors.show', $f->processor) }}">{{ $f->processor->name }}</a>
                        @else — @endif
                    </td>
                    @can('manage', \App\Models\Privacy\ComplianceFinding::class)
                        <td>
                            <form method="post" action="{{ route('dataprotection.compliance.update', $f) }}" class="flex flex-wrap items-center gap-1">
                                @csrf @method('PUT')
                                <select name="status" class="select select-xs select-bordered" @required(! $f->status->isManual())>
                                    {{-- „Läuft ab“ und „Erforderlich“ sind keine Entscheidung: ohne leere Vorauswahl stünde „Vorhanden“ da. --}}
                                    @unless ($f->status->isManual())
                                        <option value="" selected disabled>{{ __('Bitte wählen') }}</option>
                                    @endunless
                                    {{-- „Fehlt“ heißt als Entscheidung „Wieder offen“. --}}
                                    @foreach (\App\Enums\Privacy\ComplianceFindingStatus::manual() as $option)
                                        <option value="{{ $option->value }}" @selected($f->status === $option)>{{ $option === \App\Enums\Privacy\ComplianceFindingStatus::Missing ? __('Wieder offen') : $option->label() }}</option>
                                    @endforeach
                                </select>
                                <input aria-label="{{ __('Begründung') }}" name="justification" class="input input-xs input-bordered" placeholder="{{ __('Begründung') }}" value="{{ $f->justification }}">
                                <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('OK') }}</x-icon-btn>
                            </form>
                        </td>
                    @endcan
                </tr>
            @empty
                <x-table.empty :colspan="5" :title="__('Keine Befunde – Analyse ausführen.')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$findings" standing />

        {{-- Konfigurierbarer Anforderungskatalog (Nachtrag 043c). --}}
        @can('manage', \App\Models\Privacy\ComplianceFinding::class)
            <x-card>
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Anforderungskatalog') }}</h2>
                <p class="mb-2 text-xs text-muted">{{ __('Welche Prüfungen die Lückenanalyse ausführt. Deaktivierte Anforderungen werden übersprungen; Branchenprofile können Vorlagen liefern.') }}</p>
                <ul class="space-y-1">
                    @foreach ($requirements as $requirement)
                        <li class="rounded-box border border-base-300 px-3 py-2">
                            <form method="post" action="{{ route('dataprotection.compliance.requirement.update', $requirement) }}" class="flex flex-wrap items-center gap-2">
                                @csrf @method('PUT')
                                <label class="label cursor-pointer gap-2">
                                    <input type="hidden" name="active" value="0">
                                    <input type="checkbox" name="active" value="1" class="toggle toggle-sm toggle-primary" @checked($requirement->active)>
                                </label>
                                <input name="label" class="input input-sm input-bordered flex-1" value="{{ $requirement->label }}" maxlength="255" required>
                                <span class="font-mono text-xs text-muted">{{ $requirement->requirement_key }}</span>
                                @if ($requirement->source === 'profile')
                                    <x-status-badge tone="info" size="xs">{{ __('Branchenprofil') }}</x-status-badge>
                                @endif
                                <x-icon-btn icon="check" tone="ghost" size="sm" type="submit" :label="__('Speichern')" />
                            </form>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endcan
    </x-index-page>
@endsection
