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
            @if ($circular->is_template)
                {{-- Vorlage (MVP-1285): wird nie versendet, nur als Entwurf übernommen. --}}
                <x-icon-btn icon="content_copy" tone="primary" size="sm" data-entry-modal-trigger
                            :href="route('circulars.create', ['template' => $circular->sqid])" show-label>{{ __('circular.template.use') }}</x-icon-btn>
            @else
                <x-action-form :action="route('circulars.save-template', $circular)">
                    <x-icon-btn icon="bookmark_add" size="sm" placement="menu" type="submit" show-label>{{ __('circular.template.save') }}</x-icon-btn>
                </x-action-form>
            @endif
            @if (! $circular->is_template && $circular->isDraft() && $approvalRequired && ! $circular->isApproved())
                {{-- Feature 119: Der Versand bleibt gesperrt, bis eine zweite
                     Person freigegeben hat. --}}
                <x-action-form :action="route('circulars.approve', $circular)">
                    <x-icon-btn icon="how_to_reg" tone="primary" size="sm" type="submit"
                                show-label>{{ __('circular.action.approve') }}</x-icon-btn>
                </x-action-form>
            @endif
            @if (! $circular->is_template && $circular->isDraft())
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
                    {{ $circular->status->label() }}
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
            @if ($circular->attachments->isNotEmpty())
                <ul class="mt-3 flex flex-wrap gap-2 text-sm">
                    @foreach ($circular->attachments as $file)
                        <li><a class="link link-hover inline-flex items-center gap-1" href="{{ \Illuminate\Support\Facades\URL::signedRoute('attachments.download', $file) }}"><x-icon name="attach_file" />{{ $file->original_name }}</a></li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        @if (! $circular->is_template && $circular->isDraft())
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
                            <span class="text-muted">{{ $customer->email ?: __('circular.no_email') }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @else
            <x-table :pin-rows="true" :zebra="true" table-sort="client">
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
                            <x-status-badge :tone="$recipient->status === \App\Enums\Communication\CustomerCircularRecipientStatus::Sent ? 'success' : 'warning'" outline>
                                {{ $recipient->status->label() }}
                            </x-status-badge>
                            @if ($recipient->reason)
                                <span class="text-xs text-muted">{{ \App\Support\Trans::or('circular.reason.' . $recipient->reason, $recipient->reason) }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">{{ optional($recipient->sent_at)->fdatetime() ?? '—' }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="4" icon="campaign" :title="__('circular.empty_recipients')" compact />
                @endforelse
            </x-table>
        @endif
    </x-index-page>
@endsection
