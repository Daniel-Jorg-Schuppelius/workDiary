{{--
  Created on   : Tue Jul 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Temporäre Supportfreigaben (Rang 64): erteilen, einsehen, widerrufen. --}}

@extends('layouts.app')

@section('title', __('Supportfreigaben'))
@section('nav-title', __('Supportfreigaben'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Zeitlich begrenzte Freigaben für den Plattform-Support. Impersonation ist nur bei aktiver Freigabe möglich; jeder Zugriff wird auditiert.')">
    <x-slot:actions>
        <x-icon-btn icon="policy" tone="ghost" size="sm"
                    :href="route('admin.support.access-audit.index')"
                    show-label>{{ __('Supportzugriffe (Audit)') }}</x-icon-btn>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('admin.support.grants.create')"
                    show-label>{{ __('Freigabe erteilen') }}</x-icon-btn>
    </x-slot:actions>

    <x-table scroll="flex" :pinRows="true">
        <x-slot:head>
            <tr>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Umfang') }}</th>
                <th>{{ __('Zweck / Ticket') }}</th>
                <th>{{ __('Erteilt von') }}</th>
                <th>{{ __('Für Konto') }}</th>
                <th>{{ __('Gültig bis') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($grants as $grant)
            @php /** @var \App\Models\SupportAccessGrant $grant */ @endphp
            <tr>
                <td>
                    @if ($grant->isActive())
                        <x-status-badge size="xs" tone="success">{{ __('aktiv') }}</x-status-badge>
                    @elseif ($grant->revoked_at !== null)
                        <x-status-badge size="xs" tone="error">{{ __('widerrufen') }}</x-status-badge>
                    @else
                        <x-status-badge size="xs" tone="neutral">{{ __('abgelaufen') }}</x-status-badge>
                    @endif
                </td>
                <td>
                    @if ($grant->scope === \App\Models\SupportAccessGrant::SCOPE_FULL)
                        <x-status-badge size="xs" tone="warning">{{ __('Vollzugriff') }}</x-status-badge>
                    @else
                        <x-status-badge size="xs" tone="info">{{ __('Nur lesend') }}</x-status-badge>
                    @endif
                </td>
                <td class="max-w-md truncate text-sm">{{ $grant->purpose }}</td>
                <td class="text-sm">{{ $grant->grantedBy?->name ?? '—' }}</td>
                <td class="text-sm">{{ $grant->grantedTo?->name ?? __('Alle Support-Konten') }}</td>
                <td class="tabular-nums text-sm">{{ $grant->expires_at->translatedFormat('d.m.Y H:i') }}</td>
                <td class="text-right">
                    @if ($grant->isActive())
                        <form method="POST" action="{{ route('admin.support.grants.revoke', $grant) }}" class="inline">
                            @csrf
                            <x-icon-btn icon="block" tone="error" size="xs" type="submit"
                                        :label="__('Widerrufen')" />
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="7" icon="support_agent" :label="__('Noch keine Supportfreigaben erteilt.')" />
        @endforelse
    </x-table>

    <x-slot:footer>
        <x-pagination :paginator="$grants" standing />
    </x-slot:footer>
</x-index-page>
@endsection
