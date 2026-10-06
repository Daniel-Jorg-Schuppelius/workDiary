{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Genehmigungs-Inbox (Feature 065, MVP-154): offene Schritte, für die der
     angemeldete Benutzer zuständig ist (Person, Rolle oder Stufenart → Rolle
     der Organisation). Variablen: $approvals, $entries/$kinds/$mappedRoles je Approval-Id. --}}

@extends('layouts.app')
@section('title', __('Genehmigungen'))
@section('nav-title', __('Genehmigungen'))
@include('partials.page-fill')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Offene Genehmigungsschritte, für die Sie zuständig sind — genehmigen, ablehnen, rückfragen oder delegieren.')">
        <x-table scroll="flex" :zebra="true">
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
                @forelse ($approvals as $approval)
                    @php
                        /** @var \App\Services\Approval\Dto\ApprovalInboxEntry $entry */
                        $entry = $entries[$approval->id];
                        $kind = $kinds[$approval->id] ?? null;
                        $mappedRole = $mappedRoles[$approval->id] ?? null;
                        $rule = (array) $approval->approver_rule;
                    @endphp
                    <tr class="hover">
                        <td>
                            <x-status-badge tone="ghost" size="sm">{{ $entry->type ?? \App\Support\EntityType::label($approval->approvable_type) }}</x-status-badge>
                        </td>
                        <td>
                            @if ($entry->url !== null)
                                <a class="link link-hover font-medium" href="{{ $entry->url }}">{{ $entry->title }}</a>
                            @else
                                <span class="font-medium">{{ $entry->title }}</span>
                            @endif
                            @if ($entry->reference !== null)
                                <div class="text-xs text-muted font-mono">{{ $entry->reference }}</div>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ $approval->step }}</td>
                        <td class="text-sm text-muted">
                            @if ($kind !== null && $mappedRole !== null)
                                {{ $kind->label() }} · {{ __('Rolle') }}: {{ $mappedRole->label() }}
                            @elseif ((string) ($rule['type'] ?? '') === 'role')
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
        </x-table>

        <x-pagination :paginator="$approvals" standing />
    </x-index-page>
@endsection
