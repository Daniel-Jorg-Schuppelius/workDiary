{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Legal Hold (MVP-801). Variablen: $holds (Paginator), $canManage --}}
@extends('layouts.app')
@section('title', __('Legal Hold'))
@section('nav-title', __('Legal Hold'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
    <x-index-page overflow="clip" :subtitle="__('Sperrvermerke für laufende Betroffenen- und Rechtsverfahren: Solange ein Vermerk aktiv ist, wird zu Person oder Kunde nichts gelöscht oder anonymisiert.')">
        @if ($canManage)
            <x-slot:actions>
                <x-icon-btn icon="gavel" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('dataprotection.legal-holds.create')"
                            show-label>{{ __('Legal Hold setzen') }}</x-icon-btn>
            </x-slot:actions>
        @endif
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('Betroffen') }}</x-table.th>
                    <x-table.th>{{ __('Aktenzeichen') }}</x-table.th>
                    <x-table.th>{{ __('Grund') }}</x-table.th>
                    <x-table.th>{{ __('Gesetzt') }}</x-table.th>
                    <x-table.th>{{ __('Status') }}</x-table.th>
                    <x-table.th class="text-right"></x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($holds as $hold)
                @php
                    $subject = $hold->holdable;
                    $subjectLabel = $subject instanceof \App\Models\Customer ? __('Kunde') : __('Person');
                @endphp
                <tr class="hover">
                    <td>
                        <span class="block">{{ $subject?->getAttribute('name') ?? __('gelöscht') }}</span>
                        <span class="text-xs text-muted">{{ $subjectLabel }}</span>
                    </td>
                    <td>{{ $hold->reference ?? '—' }}</td>
                    <td class="max-w-md whitespace-normal">{{ $canManage ? $hold->reason : '…' }}</td>
                    <td class="whitespace-nowrap">
                        {{ $hold->placed_at->format('d.m.Y') }}
                        <span class="block text-xs text-muted">{{ $hold->placedBy?->name ?? '—' }}</span>
                    </td>
                    <td>
                        @if ($hold->isActive())
                            <x-status-badge tone="error" size="sm">{{ __('aktiv') }}</x-status-badge>
                        @else
                            <x-status-badge tone="ghost" size="sm">{{ __('aufgehoben am :date', ['date' => $hold->released_at?->format('d.m.Y')]) }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-right">
                        @if ($canManage && $hold->isActive())
                            <x-icon-btn icon="lock_open" tone="warning" data-entry-modal-trigger
                                        :href="route('dataprotection.legal-holds.release-dialog', $hold)" :label="__('Legal Hold aufheben')" />
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="6" :title="__('Keine Legal Holds.')" compact />
            @endforelse
        </x-table>
        <x-pagination :paginator="$holds" standing />
    </x-index-page>
@endsection
