{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Zeit-Exporte'))
@section('nav-title', __('Zeit-Exporte'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    use App\Enums\TimeExport\TimeExportStatus;
    $tone = fn(TimeExportStatus $s) => match ($s) {
        TimeExportStatus::Preparing => 'info',
        TimeExportStatus::Ready => 'primary',
        TimeExportStatus::Delivered => 'success',
        TimeExportStatus::Rejected => 'warning',
        TimeExportStatus::Superseded => 'ghost',
    };
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('Genehmigte Monate als Lohnabrechnungs-Export bereitstellen.')">
    <x-slot:actions>
        @can('create', App\Models\TimeExport::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        :href="route('exports.create')"
                        show-label>{{ __('Export erstellen') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    <x-filter-bar :action="route('exports.index')" :reset="route('exports.index')">
        <select name="status" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('Status') }}">
            <option value="all" @selected($filters['status'] === 'all')>{{ __('Alle Status') }}</option>
            @foreach ($statuses as $st)
                <option value="{{ $st->value }}" @selected($filters['status'] === $st->value)>{{ $st->label() }}</option>
            @endforeach
        </select>
        <select name="profile" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('Profil') }}">
            <option value="all" @selected($filters['profile'] === 'all')>{{ __('Alle Profile') }}</option>
            @foreach ($profiles as $key => $label)
                <option value="{{ $key }}" @selected($filters['profile'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="number" name="year" min="2000" max="2999" value="{{ $filters['year'] }}"
               class="input input-sm input-bordered w-24 shrink-0" placeholder="{{ __('Jahr') }}"
               aria-label="{{ __('Jahr') }}" />
    </x-filter-bar>

    @if (session('status'))
        <div role="alert" class="alert alert-success"><span>{{ session('status') }}</span></div>
    @endif
    @if ($exports->isEmpty())
        <x-empty-state framed
            icon="receipt_long"
            :title="__('Noch keine Exporte vorhanden')"
            :message="__('Erstellen Sie aus genehmigten Monaten einen Export für die Lohnabrechnung.')" />
    @else
        <x-table table-sort="server"
                 :route="route('exports.index')"
                 :current-sort="$sort"
                 :current-dir="$dir"
                 :sort-params="array_filter(['status' => $filters['status'] === 'all' ? null : $filters['status'], 'profile' => $filters['profile'] === 'all' ? null : $filters['profile'], 'year' => $filters['year']])"
                 scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th sort="period_year" default>{{ __('Periode') }}</x-table.th>
                    <x-table.th sort="profile">{{ __('Profil') }}</x-table.th>
                    <th>{{ __('Scope') }}</th>
                    <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                    <x-table.th sort="rows_count" align="right">{{ __('Zeilen') }}</x-table.th>
                    <x-table.th sort="created_at">{{ __('Erstellt') }}</x-table.th>
                    <th>{{ __('Ersteller:in') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($exports as $e)
                <tr>
                    <td class="tabular-nums font-medium">{{ $e->periodLabel() }}</td>
                    <td>{{ $profiles[$e->profile] ?? $e->profile }}</td>
                    <td class="text-xs">
                        @if ($e->scope === 'user')
                            {{ __('Person') }}: {{ $e->scopeUser?->name }}
                        @elseif ($e->scope === 'team')
                            {{ __('Team') }}
                        @else
                            {{ __('Organisation') }}
                        @endif
                    </td>
                    <td><x-status-badge :tone="$tone($e->status)" size="sm">{{ $e->status->label() }}</x-status-badge></td>
                    <td class="text-right tabular-nums">{{ $e->rows_count }}</td>
                    <td class="text-xs tabular-nums">{{ $e->created_at?->fdatetime() }}</td>
                    <td class="text-xs">{{ $e->creator?->name }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="visibility" tone="ghost" size="sm"
                                        :href="route('exports.show', $e)"
                                        :aria-label="__('Details')" />
                            @can('download', $e)
                                <x-icon-btn icon="download" tone="ghost" size="sm"
                                            :href="route('exports.download', $e)"
                                            :aria-label="__('Herunterladen')" />
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$exports" standing />
    @endif
</x-index-page>
@endsection
