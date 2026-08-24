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
@php
    $statusMeta = [
        'missing' => ['Fehlt', 'badge-error'],
        'expiring' => ['Läuft ab', 'badge-warning'],
        'required' => ['Erforderlich', 'badge-warning'],
        'in_review' => ['In Prüfung', 'badge-info'],
        'deviation_accepted' => ['Abweichung akzeptiert', 'badge-ghost'],
        'present' => ['Vorhanden', 'badge-success'],
        'not_applicable' => ['Nicht anwendbar', 'badge-ghost'],
    ];
    $statusTone = [
        'missing' => 'error',
        'expiring' => 'warning',
        'required' => 'warning',
        'in_review' => 'info',
        'deviation_accepted' => 'ghost',
        'present' => 'success',
        'not_applicable' => 'ghost',
    ];
    $statusOptions = ['present' => __('Vorhanden'), 'in_review' => __('In Prüfung'), 'not_applicable' => __('Nicht anwendbar'), 'deviation_accepted' => __('Abweichung akzeptiert'), 'missing' => __('Wieder offen')];
@endphp
@section('content')
    <x-index-page :subtitle="__('Lücken in Verträgen und Compliance-Anforderungen aufdecken und bewerten.')">
        <x-slot:actions>
            @can('manage', \App\Models\Privacy\ComplianceFinding::class)
                <form method="post" action="{{ route('dataprotection.compliance.run') }}">@csrf
                    <x-icon-btn icon="rule" tone="primary" size="sm" type="submit" show-label>{{ __('Analyse jetzt ausführen') }}</x-icon-btn>
                </form>
            @endcan
        </x-slot:actions>

        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        <x-validation-errors />

        {{-- Ampel --}}
        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($statusMeta as $s => [$label, $cls])
                    @if (($counts[$s] ?? 0) > 0)
                        <x-status-badge :tone="$statusTone[$s] ?? 'ghost'" size="lg" class="gap-2">{{ __($label) }} <span class="font-bold">{{ $counts[$s] }}</span></x-status-badge>
                    @endif
                @endforeach
                @if ($findings->isEmpty())
                    <span class="text-sm text-base-content/60">{{ __('Noch keine Analyse ausgeführt.') }}</span>
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
                    <td><x-status-badge :tone="$statusTone[$f->status] ?? 'ghost'" size="sm">{{ __($statusMeta[$f->status][0] ?? $f->status) }}</x-status-badge></td>
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
                                <select name="status" class="select select-xs select-bordered">
                                    @foreach ($statusOptions as $v => $l)<option value="{{ $v }}" @selected($f->status === $v)>{{ $l }}</option>@endforeach
                                </select>
                                <input name="justification" class="input input-xs input-bordered" placeholder="{{ __('Begründung') }}" value="{{ $f->justification }}">
                                <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('OK') }}</x-icon-btn>
                            </form>
                        </td>
                    @endcan
                </tr>
            @empty
                <x-table.empty :colspan="5" :title="__('Keine Befunde – Analyse ausführen.')" />
            @endforelse
        </x-table>

        {{-- Konfigurierbarer Anforderungskatalog (Nachtrag 043c). --}}
        @can('manage', \App\Models\Privacy\ComplianceFinding::class)
            <x-card>
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Anforderungskatalog') }}</h2>
                <p class="mb-2 text-xs text-base-content/60">{{ __('Welche Prüfungen die Lückenanalyse ausführt. Deaktivierte Anforderungen werden übersprungen; Branchenprofile können Vorlagen liefern.') }}</p>
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
                                <span class="font-mono text-xs text-base-content/50">{{ $requirement->requirement_key }}</span>
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
