{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Betroffenenanfragen'))
@section('nav-title', __('Betroffenenanfragen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Anfragen betroffener Personen erfassen, zuweisen und fristgerecht bearbeiten.')">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('dataprotection.requests.create')"
                        show-label>{{ __('Neue Anfrage') }}</x-icon-btn>
        </x-slot:actions>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('Nummer') }}</x-table.th>
                    <x-table.th>{{ __('Art') }}</x-table.th>
                    <x-table.th>{{ __('Status') }}</x-table.th>
                    <x-table.th>{{ __('Frist') }}</x-table.th>
                    <x-table.th>{{ __('Zuständig') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($requests as $r)
                <tr class="hover">
                    <td><a class="link" href="{{ route('dataprotection.requests.show', $r) }}">{{ $r->request_number }}</a></td>
                    <td>{{ $r->type->label() }}</td>
                    <td>
                        <x-status-badge :tone="$r->isOverdue() ? 'error' : 'ghost'" size="sm">{{ $r->status->label() }}</x-status-badge>
                    </td>
                    <td class="{{ $r->isOverdue() ? 'text-error font-semibold' : '' }}">{{ $r->deadline_at?->format('d.m.Y') }}</td>
                    <td>{{ $r->assignedUser?->name ?? '—' }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="5" :title="__('Keine Anfragen.')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$requests" standing />
    </x-index-page>
@endsection
