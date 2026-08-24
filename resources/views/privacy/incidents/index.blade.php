{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Datenschutzvorfälle'))
@section('nav-title', __('Datenschutzvorfälle'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
    <x-index-page overflow="clip" :subtitle="__('Datenschutzvorfälle erfassen, bewerten und fristgerecht melden.')">
        <x-slot:actions>
            <x-icon-btn icon="gpp_maybe" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('dataprotection.incidents.create')"
                        show-label>{{ __('Vorfall melden') }}</x-icon-btn>
        </x-slot:actions>

        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('Nummer') }}</x-table.th>
                    <x-table.th>{{ __('Art') }}</x-table.th>
                    <x-table.th>{{ __('Status') }}</x-table.th>
                    <x-table.th>{{ __('72-h-Frist') }}</x-table.th>
                    <x-table.th>{{ __('Zuständig') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($incidents as $i)
                <tr class="hover">
                    <td><a class="link" href="{{ route('dataprotection.incidents.show', $i) }}">{{ $i->incident_number }}</a></td>
                    <td>{{ $i->type->label() }}</td>
                    <td><x-status-badge :tone="$i->isDeadlineBreached() ? 'error' : 'ghost'" size="sm">{{ $i->status->label() }}</x-status-badge></td>
                    <td class="{{ $i->isDeadlineBreached() ? 'text-error font-semibold' : '' }}">{{ $i->authority_deadline_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td>{{ $i->assignedUser?->name ?? '—' }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="5" :title="__('Keine Vorfälle erfasst.')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$incidents" standing />
    </x-index-page>
@endsection
