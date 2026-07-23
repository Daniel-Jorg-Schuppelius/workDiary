{{--
  Created on   : Fri Jul 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@php
/**
 * @var \App\Models\User $driver
 * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\DriverLicenseCheck> $checks
 * @var bool $overdue
 */
@endphp

@section('nav-title', __('Führerscheinkontrolle') . ' · ' . $driver->name)

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Kontrollhistorie (Nachweis)')">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('driver-license-checks.index')" show-label>{{ __('Übersicht') }}</x-icon-btn>
                @can(\App\Enums\User\Permission::VehicleManage->value)
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('driver-license-checks.create', ['user' => \App\Support\Sqid::encode(\App\Models\User::class, (int) $driver->id), 'dialog' => 1])"
                                show-label>{{ __('Kontrolle dokumentieren') }}</x-icon-btn>
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($overdue)
        <div class="alert alert-error text-sm">
            <span>{{ __('Die Führerscheinkontrolle dieses Fahrers ist überfällig — Fahrzeugreservierungen sind gesperrt, bis eine neue Sichtprüfung dokumentiert ist.') }}</span>
        </div>
    @endif

    <x-table :zebra="true" size="sm">
        <x-slot:head>
            <tr>
                <th>{{ __('Geprüft am') }}</th>
                <th>{{ __('Geprüft von') }}</th>
                <th>{{ __('Klassen') }}</th>
                <th>{{ __('Gültig bis') }}</th>
                <th>{{ __('Nächste Kontrolle') }}</th>
                <th>{{ __('Notiz') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($checks as $check)
            <tr>
                <td class="whitespace-nowrap">{{ $check->checked_at->fdate() }}</td>
                <td>{{ $check->checker?->name ?? '—' }}</td>
                <td>{{ $check->license_classes ?? '—' }}</td>
                <td class="whitespace-nowrap">{{ $check->license_valid_until?->fdate() ?? '—' }}</td>
                <td class="whitespace-nowrap">{{ $check->next_due_on->fdate() }}</td>
                <td class="max-w-xs truncate text-base-content/60 text-xs">{{ $check->note }}</td>
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">badge</span>' :colspan="6" :title="__('Noch keine Kontrolle dokumentiert')" compact />
        @endforelse
    </x-table>
</x-page-shell>
@endsection
