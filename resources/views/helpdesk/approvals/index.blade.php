{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Genehmigungs-Inbox (Feature 065, MVP-154): offene Schritte, für die der
     angemeldete Benutzer laut approver_rule zuständig ist — ServiceRequests
     UND Changes über EINE Mechanik. --}}

@extends('layouts.app')
@section('title', __('Genehmigungen'))
@section('nav-title', __('Genehmigungen'))

@section('content')
    <x-index-page :subtitle="__('Offene Genehmigungsschritte, für die du zuständig bist — genehmigen, ablehnen, rückfragen oder delegieren.')">
        <x-table :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Typ') }}</th>
                    <th>{{ __('Gegenstand') }}</th>
                    <th class="text-right">{{ __('Schritt') }}</th>
                    <th>{{ __('Zuständigkeit') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="w-24 text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
            <tbody>
                @forelse ($approvals as $approval)
                    @php
                        $approvable = $approval->approvable;
                        $ticket = $approvable instanceof \App\Models\ServiceRequest ? $approvable->ticket : null;
                        $rule = (array) $approval->approver_rule;
                    @endphp
                    <tr class="hover">
                        <td>
                            <x-status-badge tone="ghost" size="sm">{{ \App\Support\EntityType::label($approval->approvable_type) }}</x-status-badge>
                        </td>
                        <td>
                            @if ($ticket !== null)
                                <a class="link link-hover font-medium" href="{{ route('service-tickets.show', $ticket) }}">{{ $ticket->title }}</a>
                                <div class="text-xs text-muted font-mono">{{ $ticket->ticket_no }}</div>
                            @else
                                <span class="font-medium">{{ $approvable?->title ?? $approvable?->name ?? '—' }}</span>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ $approval->step }}</td>
                        <td class="text-sm text-muted">
                            @if ((string) ($rule['type'] ?? '') === 'role')
                                {{ __('Rolle') }}: {{ \App\Enums\User\UserRole::tryFrom((string) ($rule['value'] ?? ''))?->label() ?? (string) ($rule['value'] ?? '') }}
                            @else
                                {{ __('Persönlich') }}
                            @endif
                        </td>
                        <td>
                            @if ($approval->decision === 'question')
                                <x-status-badge tone="warning" size="sm">{{ __('Rückfrage offen') }}</x-status-badge>
                            @else
                                <x-status-badge tone="info" size="sm">{{ __('Offen') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <x-icon-btn icon="rule" tone="primary" size="sm"
                                        data-entry-modal-trigger
                                        :href="route('servicedesk.approvals.decide-form', $approval)"
                                        :label="__('Entscheiden')" />
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="6" icon="inbox" :title="__('Keine offenen Genehmigungen')" compact />
                @endforelse
            </tbody>
        </x-table>

        <x-pagination :paginator="$approvals" standing />
    </x-index-page>
@endsection
