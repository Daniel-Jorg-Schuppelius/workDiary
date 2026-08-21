{{--
  Created on   : Thu Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Vorschau vor dem Versand (Feature 119, MVP-608): Wer bekommt das, und wie
  viele sind es? Nach dem Versand steht hier der Nachweis je Empfänger.
--}}

@extends('layouts.app')

@section('title', __('circular.title'))
@section('nav-title', __('circular.title'))

@section('content')
    <x-index-page :subtitle="$circular->subject">
        <x-slot:actions>
            @if ($circular->isDraft() && $approvalRequired && ! $circular->isApproved())
                {{-- Feature 119: Der Versand bleibt gesperrt, bis eine zweite
                     Person freigegeben hat. --}}
                <x-action-form :action="route('circulars.approve', $circular)">
                    <x-icon-btn icon="how_to_reg" tone="primary" size="sm" type="submit"
                                show-label>{{ __('circular.action.approve') }}</x-icon-btn>
                </x-action-form>
            @endif
            @if ($circular->isDraft())
                <x-action-form :action="route('circulars.send', $circular)"
                               :confirm="__('circular.confirm_send', ['count' => $audience->count()])">
                    <x-icon-btn icon="send" tone="primary" size="sm" type="submit"
                                show-label>{{ __('circular.action.send') }}</x-icon-btn>
                </x-action-form>
            @endif
        </x-slot:actions>

        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                <x-status-badge :tone="$circular->isDraft() ? 'neutral' : 'success'" outline>
                    {{ __('circular.status.' . $circular->status) }}
                </x-status-badge>
                @if ($circular->is_mandatory)
                    <x-status-badge tone="warning" outline>{{ __('circular.mandatory_short') }}</x-status-badge>
                @endif
                @if ($circular->portal_notice)
                    <x-status-badge tone="info" outline>{{ __('circular.portal_short') }}</x-status-badge>
                @endif
                @if ($circular->isApproved())
                    <x-status-badge tone="success" outline>{{ __('circular.approved_by', ['name' => $circular->approvedBy?->name ?? '—']) }}</x-status-badge>
                @elseif ($approvalRequired)
                    <x-status-badge tone="warning" outline>{{ __('circular.approval_pending') }}</x-status-badge>
                @endif
            </div>
            <p class="mt-3 whitespace-pre-line text-sm">{{ $circular->body }}</p>
        </x-card>

        @if ($circular->isDraft())
            {{-- Die Zahl steht VOR dem Knopf: eine Mail an alle Kunden ist
                 nichts, das man versehentlich auslösen können soll. --}}
            <x-card :title="__('circular.audience.heading', ['count' => $audience->count()])">
                @if ($circular->is_mandatory)
                    <p class="text-xs text-base-content/70">{{ __('circular.mandatory_hint') }}</p>
                @endif
                <ul class="mt-2 grid gap-1 text-sm sm:grid-cols-2">
                    @foreach ($audience as $customer)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ $customer->name }}</span>
                            <span class="text-base-content/60">{{ $customer->email ?: __('circular.no_email') }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @else
            <x-table scroll="flex" :pin-rows="true" :zebra="true">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('circular.column.customer') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('circular.column.email') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('circular.column.status') }}</x-table.th>
                        <x-table.th sort type="date">{{ __('circular.column.sent_at') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($circular->recipients as $recipient)
                    <tr class="hover">
                        <td class="font-medium">{{ $recipient->customer?->name ?? '—' }}</td>
                        <td>{{ $recipient->email ?: '—' }}</td>
                        <td>
                            <x-status-badge :tone="$recipient->status === 'sent' ? 'success' : 'warning'" outline>
                                {{ __('circular.recipient_status.' . $recipient->status) }}
                            </x-status-badge>
                            @if ($recipient->reason)
                                <span class="text-xs text-base-content/60">{{ \App\Support\Trans::or('circular.reason.' . $recipient->reason, $recipient->reason) }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">{{ optional($recipient->sent_at)->fdatetime() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state icon="campaign" :title="__('circular.empty_recipients')" /></td></tr>
                @endforelse
            </x-table>
        @endif
    </x-index-page>
@endsection
