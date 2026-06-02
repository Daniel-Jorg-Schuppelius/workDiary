{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : legacy-migration.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Legacy-Migration'))
@section('nav-title', __('Legacy-Migration'))

@section('content')
<div class="flex h-[calc(100dvh-11rem)] flex-col gap-6 overflow-auto">
    <div class="flex justify-end">
        @if ($writeEnabled)
            <x-status-badge tone="warning">{{ __('Legacy-Schreibzugriff aktiv') }}</x-status-badge>
        @else
            <x-status-badge tone="success">{{ __('Legacy read-only') }}</x-status-badge>
        @endif
    </div>

    @if (! $stats['configured'])
        <div class="alert alert-warning">
            <span>{{ __('Legacy-Datenbank nicht erreichbar oder nicht konfiguriert.') }}</span>
        </div>
    @else
        <x-table table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string" default="asc">{{ __('Datentyp') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Legacy gesamt') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Bereits importiert') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Verbleibend') }}</x-table.th>
                        <th class="text-right">{{ __('Aktion') }}</th>
                    </tr>
                </x-slot:head>
                    @foreach (['users' => __('Benutzer'), 'diary' => __('Auftragsbuch'), 'shifts' => __('Bereitschaften'), 'assignments' => __('Notdienste')] as $key => $label)
                        @php $row = $stats[$key]; $remaining = max(0, $row['legacy'] - $row['imported']); @endphp
                        <tr>
                            <td class="font-medium">{{ $label }}</td>
                            <td class="text-right">{{ number_format($row['legacy'], 0, ',', '.') }}</td>
                            <td class="text-right">{{ number_format($row['imported'], 0, ',', '.') }}</td>
                            <td class="text-right" data-sort-value="{{ $remaining }}">
                                @if ($remaining === 0)
                                    <x-status-badge tone="success" size="sm">{{ __('Vollständig') }}</x-status-badge>
                                @else
                                    <span class="text-warning">{{ number_format($remaining, 0, ',', '.') }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('admin.legacy-migration.run') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="type" value="{{ $key }}">
                                    <x-icon-btn icon="download" tone="primary" size="sm" type="submit" show-label>{{ __('Importieren') }}</x-icon-btn>
                                </form>
                            </td>
                        </tr>
                    @endforeach
        </x-table>

        <form method="POST" action="{{ route('admin.legacy-migration.run') }}" class="inline">
            @csrf
            <input type="hidden" name="type" value="all">
            <x-icon-btn icon="download" tone="primary" size="sm" type="submit" show-label>{{ __('Alles importieren') }}</x-icon-btn>
        </form>
    @endif
</div>
@endsection
