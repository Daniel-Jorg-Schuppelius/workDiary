{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Queue-Verwaltung (Feature 065, MVP-150): Arbeitsvorräte des Helpdesks —
     Modal-CRUD, genau eine Default-Queue, Löschen nur ohne Tickets. --}}

@extends('layouts.app')
@section('title', __('Ticket-Queues'))
@section('nav-title', __('Ticket-Queues'))

@section('content')
    <x-index-page :subtitle="__('Arbeitsvorräte des Helpdesks mit Team, Standard-SLA und Sichtbarkeit.')">
        <x-slot:actions>
            @if ($canManage)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('helpdesk.queues.create')"
                            show-label>{{ __('Neue Queue') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        <x-table :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Team') }}</th>
                    <th>{{ __('Sichtbarkeit') }}</th>
                    <th class="text-right">{{ __('Tickets') }}</th>
                    <th class="w-32 text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
            <tbody>
                @forelse ($queues as $queue)
                    <tr class="hover">
                        <td class="font-semibold">
                            {{ $queue->name }}
                            @if ($queue->is_default)
                                <x-status-badge tone="info" size="xs">{{ __('Standard') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-sm text-base-content/60">{{ $queue->team?->name ?? '—' }}</td>
                        <td class="text-sm">{{ $queue->visibility === 'portal' ? __('Kundenportal') : __('Intern') }}</td>
                        <td class="text-right tabular-nums">{{ $queue->tickets_count }}</td>
                        <td class="text-right whitespace-nowrap">
                            @if ($canManage)
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('helpdesk.queues.edit', $queue)"
                                            :label="__('Bearbeiten')" />
                                @if (! $queue->is_default && $queue->tickets_count === 0)
                                    <x-action-form :action="route('helpdesk.queues.destroy', $queue)"
                                          method="DELETE"
                                          data-confirm-title="{{ __('Queue löschen') }}"
                                          :confirm="__('Die Queue wird entfernt.')"
                                          :confirm-label="__('Löschen')">
                                        <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                    </x-action-form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="5" icon="inbox" :title="__('Noch keine Queues angelegt')" compact />
                @endforelse
            </tbody>
        </x-table>
    </x-index-page>
@endsection
