{{--
  Created on   : Tue Jul 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Quelltext-Integrität'))
@section('nav-title', __('Quelltext-Integrität'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Datei-Hash-Baseline des Quelltexts: Prüfläufe, Abweichungen und Baseline-Stand.')">
    <x-slot:actions>
        <x-action-form :action="route('admin.integrity.verify')">
            <x-icon-btn icon="play_arrow" tone="ghost" size="sm" type="submit"
                        show-label>{{ __('Jetzt prüfen') }}</x-icon-btn>
        </x-action-form>
        <x-action-form :action="route('admin.integrity.freeze')"
              data-confirm-title="{{ __('Baseline einfrieren') }}"
              :confirm="__('Neue lokale Baseline erzeugen? Der aktuelle Dateistand gilt danach als Soll — vorhandene Abweichungen werden Teil der Baseline.')"
              confirm-icon="ac_unit"
              confirm-tone="warning"
              :confirm-label="__('Baseline einfrieren')">
            <x-icon-btn icon="ac_unit" tone="ghost" size="sm" type="submit"
                        show-label>{{ __('Baseline einfrieren') }}</x-icon-btn>
        </x-action-form>
    </x-slot:actions>

    <div class="grid gap-3 sm:grid-cols-3 mb-3 shrink-0">
        <div class="rounded-box border border-base-300 bg-base-100 p-3">
            <div class="text-xs uppercase tracking-wide text-base-content/60">{{ __('Letzter Prüflauf') }}</div>
            @if ($latest !== null)
                <div class="mt-1 flex items-center gap-2">
                    <x-status-badge :tone="$latest->status->tone()" size="sm">{{ $latest->status->label() }}</x-status-badge>
                    <span class="text-xs text-base-content/70">{{ $latest->ran_at->format('d.m.Y H:i') }}</span>
                </div>
                @if ($latest->deviationCount() > 0)
                    <div class="mt-1 text-xs text-base-content/70">
                        {{ __(':added neu, :modified geändert, :deleted gelöscht, :packages Paket(e)', ['added' => $latest->added_count, 'modified' => $latest->modified_count, 'deleted' => $latest->deleted_count, 'packages' => $latest->packages_changed_count]) }}
                    </div>
                @endif
            @else
                <div class="mt-1 text-sm text-base-content/70">{{ __('Noch kein Prüflauf') }}</div>
            @endif
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-3">
            <div class="text-xs uppercase tracking-wide text-base-content/60">{{ __('Baseline') }}</div>
            @if ($baseline !== null)
                <div class="mt-1 flex items-center gap-2">
                    <x-status-badge :tone="$baseline['source'] === 'release' ? 'info' : 'warning'" size="sm" outline>
                        {{ $baseline['source'] === 'release' ? __('Release (signierbar)') : __('Lokal (unsigniert)') }}
                    </x-status-badge>
                </div>
                <div class="mt-1 text-xs text-base-content/70">
                    {{ __(':files Dateien, :packages Pakete', ['files' => $baseline['files'], 'packages' => $baseline['packages']]) }}
                </div>
            @else
                <div class="mt-1 text-sm text-warning">{{ __('Keine Baseline vorhanden') }}</div>
            @endif
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-3">
            <div class="text-xs uppercase tracking-wide text-base-content/60">{{ __('Root-Hash') }}</div>
            @if ($baseline !== null)
                <code class="mt-1 block text-xs break-all">{{ $baseline['root'] }}</code>
            @else
                <div class="mt-1 text-sm text-base-content/70">—</div>
            @endif
        </div>
    </div>

    @if ($latest !== null && $latest->findings !== null && $latest->findings !== [])
        <div class="rounded-box border border-error/40 bg-error/5 p-3 mb-3 shrink-0 overflow-y-auto max-h-48">
            <div class="text-sm font-medium mb-1">{{ __('Befunde des letzten Laufs') }}</div>
            @foreach ($latest->findings as $category => $paths)
                <div class="text-xs">
                    <span class="font-mono text-base-content/60">[{{ $category }}]</span>
                    @foreach ($paths as $path)
                        <div class="pl-4 font-mono break-all">{{ $path }}</div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    @if ($checks->count() === 0)
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">verified_user</span>'
            :title="__('Keine Prüfläufe')"
            :message="__('Noch keine Integritätsprüfung gelaufen — „Jetzt prüfen“ startet den ersten Lauf.')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('Zeitpunkt') }}</x-table.th>
                    <x-table.th>{{ __('Status') }}</x-table.th>
                    <x-table.th>{{ __('Baseline') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('Dateien') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('Neu') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('Geändert') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('Gelöscht') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('Pakete') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('Dauer') }}</x-table.th>
                    <x-table.th>{{ __('Auslöser') }}</x-table.th>
                </tr>
            </x-slot:head>
            @foreach ($checks as $check)
                <tr>
                    <td class="text-xs text-base-content/70 whitespace-nowrap">{{ $check->ran_at->format('d.m.Y H:i:s') }}</td>
                    <td><x-status-badge :tone="$check->status->tone()" size="sm">{{ $check->status->label() }}</x-status-badge></td>
                    <td class="text-xs">{{ $check->baseline_source ?: '—' }}</td>
                    <td class="text-right text-xs">{{ number_format($check->files_checked, 0, ',', '.') }}</td>
                    <td class="text-right text-xs {{ $check->added_count > 0 ? 'text-error font-medium' : 'text-base-content/50' }}">{{ $check->added_count }}</td>
                    <td class="text-right text-xs {{ $check->modified_count > 0 ? 'text-error font-medium' : 'text-base-content/50' }}">{{ $check->modified_count }}</td>
                    <td class="text-right text-xs {{ $check->deleted_count > 0 ? 'text-error font-medium' : 'text-base-content/50' }}">{{ $check->deleted_count }}</td>
                    <td class="text-right text-xs {{ $check->packages_changed_count > 0 ? 'text-error font-medium' : 'text-base-content/50' }}">{{ $check->packages_changed_count }}</td>
                    <td class="text-right text-xs text-base-content/70">{{ $check->duration_ms }} ms</td>
                    <td class="text-xs text-base-content/70">{{ $check->triggered_by }}</td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$checks" standing />
    @endif
</x-index-page>
@endsection
