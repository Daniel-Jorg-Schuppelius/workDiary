{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Change-/CAB-Übersicht (Feature 065, MVP-157): kommende Fenster zuerst
     (CAB-Sicht), Filter nach Typ/Status/Outcome. Freigaben laufen über die
     gemeinsame Genehmigungs-Inbox (servicedesk.approvals.*). --}}

@extends('layouts.app')
@section('title', __('Changes'))
@section('nav-title', __('Changes'))

@section('content')
    <x-index-page :subtitle="__('Geplante Änderungen mit Fenster, Plänen und Freigaben — kommende Fenster zuerst (CAB-Sicht).')">
        <x-slot:actions>
            @if ($canManage)
                <x-icon-btn icon="library_books" size="sm"
                            :href="route('servicedesk.change-templates.index')"
                            show-label>{{ __('Vorlagen') }}</x-icon-btn>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('servicedesk.changes.create')"
                            show-label>{{ __('Neuer Change') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        <x-filter-bar :action="route('servicedesk.changes.index')" :reset="route('servicedesk.changes.index')">
            <select name="change_type" class="select select-sm select-bordered w-36 shrink-0" aria-label="{{ __('Typ') }}">
                <option value="">{{ __('Alle Typen') }}</option>
                @foreach ($typeLabels as $val => $label)
                    <option value="{{ $val }}" @selected($filters['change_type'] === $val)>{{ $label }}</option>
                @endforeach
            </select>

            <select name="status" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Status') }}">
                <option value="">{{ __('Alle Status') }}</option>
                @foreach ($statusLabels as $val => $label)
                    <option value="{{ $val }}" @selected($filters['status'] === $val)>{{ $label }}</option>
                @endforeach
            </select>

            <select name="outcome" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Ergebnis') }}">
                <option value="">{{ __('Alle Ergebnisse') }}</option>
                @foreach ($outcomeLabels as $val => $label)
                    <option value="{{ $val }}" @selected($filters['outcome'] === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </x-filter-bar>

        <x-table :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Titel') }}</th>
                    <th>{{ __('Typ') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Fenster') }}</th>
                    <th class="text-right">{{ __('Tickets') }}</th>
                    <th class="text-right">{{ __('Assets') }}</th>
                    <th>{{ __('Ergebnis') }}</th>
                    <th class="w-24 text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
            <tbody>
                @forelse ($changes as $change)
                    <tr class="hover">
                        <td>
                            <a class="link link-hover font-medium" href="{{ route('servicedesk.changes.show', $change) }}">{{ $change->title }}</a>
                            @if ($change->problem !== null)
                                <div class="text-xs text-muted">{{ __('Problem') }}: {{ $change->problem->title }}</div>
                            @endif
                        </td>
                        <td><x-status-badge tone="ghost" size="sm">{{ $typeLabels[$change->change_type] ?? $change->change_type }}</x-status-badge></td>
                        <td><x-status-badge size="sm" outline>{{ $statusLabels[$change->status] ?? $change->status }}</x-status-badge></td>
                        <td class="text-sm text-muted whitespace-nowrap">
                            @if ($change->window_from !== null)
                                {{ $change->window_from->translatedFormat('d.m.Y H:i') }}
                                – {{ $change->window_to?->translatedFormat('d.m.Y H:i') ?? '…' }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ $change->tickets_count }}</td>
                        <td class="text-right tabular-nums">{{ $change->assets_count }}</td>
                        <td class="text-sm text-muted">{{ $change->outcome !== null ? ($outcomeLabels[$change->outcome] ?? $change->outcome) : '—' }}</td>
                        <td class="text-right">
                            <x-icon-btn icon="open_in_new" :href="route('servicedesk.changes.show', $change)" :label="__('Details')" />
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="8" icon="published_with_changes" :title="__('Noch keine Changes erfasst')" compact />
                @endforelse
            </tbody>
        </x-table>

        <x-pagination :paginator="$changes" standing />
    </x-index-page>
@endsection
