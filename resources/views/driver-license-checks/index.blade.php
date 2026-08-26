{{--
  Created on   : Fri Jul 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@php
/**
 * @var array<int, array{user: \App\Models\User, latest: \App\Models\DriverLicenseCheck|null, overdue: bool}> $rows
 */
@endphp

@section('nav-title', __('Führerscheinkontrolle'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Dokumentierte Sichtprüfungen je Fahrer (Halterhaftung); überfällige Kontrollen sperren die Fahrzeugreservierung.')">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('vehicles.index')" show-label>{{ __('Fuhrpark') }}</x-icon-btn>
                @can(\App\Enums\User\Permission::VehicleManage->value)
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('driver-license-checks.create') . '?dialog=1'"
                                show-label>{{ __('Kontrolle dokumentieren') }}</x-icon-btn>
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-table :zebra="true" size="sm">
        <x-slot:head>
            <tr>
                <th>{{ __('Fahrer') }}</th>
                <th>{{ __('Letzte Kontrolle') }}</th>
                <th>{{ __('Klassen') }}</th>
                <th>{{ __('Führerschein gültig bis') }}</th>
                <th>{{ __('Nächste Kontrolle') }}</th>
                <th>{{ __('Status') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($rows as $row)
            <tr @class(['hover', 'bg-error/5' => $row['overdue']])>
                <td class="font-medium">
                    <a href="{{ route('driver-license-checks.show', $row['user']) }}" class="link link-hover">{{ $row['user']->name }}</a>
                </td>
                <td class="whitespace-nowrap">{{ $row['latest']?->checked_at?->fdate() ?? '—' }}</td>
                <td>{{ $row['latest']?->license_classes ?? '—' }}</td>
                <td class="whitespace-nowrap">{{ $row['latest']?->license_valid_until?->fdate() ?? '—' }}</td>
                <td class="whitespace-nowrap">{{ $row['latest']?->next_due_on?->fdate() ?? '—' }}</td>
                <td>
                    @if ($row['overdue'])
                        <span class="tooltip tooltip-left" data-tip="{{ __('Fahrzeugreservierung gesperrt, bis eine neue Sichtprüfung dokumentiert ist.') }}">
                            <x-status-badge size="sm" tone="error">{{ __('Überfällig — Reservierung gesperrt') }}</x-status-badge>
                        </span>
                    @elseif ($row['latest'] === null)
                        <x-status-badge size="sm" tone="warning">{{ __('Noch nie kontrolliert') }}</x-status-badge>
                    @else
                        <x-status-badge size="sm" tone="success">{{ __('OK') }}</x-status-badge>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty icon="badge" :colspan="6" :title="__('Keine Fahrer gefunden')" compact />
        @endforelse
    </x-table>
</x-page-shell>
@endsection
