{{--
  Created on   : Thu Oct 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('invoicing.inquiries.title'))
@section('nav-title', __('invoicing.inquiries.title'))
@include('partials.page-fill')

@section('content')
@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator<int, \App\Models\Invoicing\InvoiceInquiry> $inquiries */
    /** @var string $status */
    /** @var array{open: int, answered: int} $counts */
    /** @var array<string, string> $sourceLabels */
@endphp
{{-- Rechnungsnachfragen aus E-Mails (Feature 166, MVP-1202): Arbeitsliste über alle Rechnungen und Belege. --}}
<x-index-page overflow="clip" :subtitle="__('invoicing.inquiries.subtitle')" back-route="billing.feed" :back-label="__('Zurück')">
    <x-tab-nav class="w-fit" :items="[
        ['route' => 'invoices.inquiries.index', 'params' => ['status' => 'open'], 'active' => $status === 'open', 'icon' => 'mark_email_unread', 'label' => __('invoicing.inquiries.filter.open'), 'count' => $counts['open']],
        ['route' => 'invoices.inquiries.index', 'params' => ['status' => 'answered'], 'active' => $status === 'answered', 'icon' => 'mark_email_read', 'label' => __('invoicing.inquiries.filter.answered'), 'count' => $counts['answered']],
        ['route' => 'invoices.inquiries.index', 'params' => ['status' => 'all'], 'active' => $status === 'all', 'icon' => 'all_inbox', 'label' => __('invoicing.inquiries.filter.all')],
    ]" />

    @if ($inquiries->isEmpty())
        <x-empty-state framed icon="mark_email_read" :title="__('invoicing.inquiries.empty')" :message="__('invoicing.inquiries.empty_hint')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('invoicing.inquiries.column.received') }}</th>
                    <th>{{ __('invoicing.inquiries.column.document') }}</th>
                    <th>{{ __('Kunde') }}</th>
                    <th>{{ __('invoicing.inquiries.column.sender') }}</th>
                    <th>{{ __('Betreff') }}</th>
                    <th>{{ __('invoicing.inquiries.column.match') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($inquiries as $inquiry)
                <tr>
                    <td class="whitespace-nowrap">{{ $inquiry->received_at->fdatetime() }}</td>
                    <td class="whitespace-nowrap">
                        @if ($inquiry->invoice !== null)
                            <a href="{{ route('invoices.show', $inquiry->invoice) }}" class="link link-hover font-medium">{{ $inquiry->invoice_number }}</a>
                        @else
                            <a href="{{ route('billing.feed', ['q' => $inquiry->invoice_number]) }}" class="link link-hover font-medium">{{ $inquiry->invoice_number }}</a>
                            @if ($inquiry->external_source !== null)
                                <x-status-badge tone="plain" outline size="xs">{{ $sourceLabels[$inquiry->external_source] ?? $inquiry->external_source }}</x-status-badge>
                            @endif
                        @endif
                    </td>
                    <td>{{ $inquiry->customer?->name ?? '—' }}</td>
                    <td class="max-w-xs truncate" title="{{ $inquiry->from_email }}">{{ $inquiry->from_name ?: $inquiry->from_email }}</td>
                    <td class="max-w-sm truncate">{{ $inquiry->subject ?? __('mail.inbox.no_subject') }}</td>
                    <td>{{ $inquiry->match_kind->label() }}</td>
                    <td><x-status-badge :tone="$inquiry->status->tone()" size="xs">{{ $inquiry->status->label() }}</x-status-badge></td>
                    <td class="text-right whitespace-nowrap">
                        @if ($inquiry->communicationNote !== null)
                            <x-icon-btn icon="chat" tone="ghost" size="sm" :href="route('communication-notes.show', $inquiry->communicationNote)" :label="__('invoicing.inquiries.action.note')" />
                        @endif
                        @if ($inquiry->isOpen())
                            <x-action-form :action="route('invoices.inquiries.answer', $inquiry)">
                                <x-icon-btn icon="task_alt" tone="success" size="sm" type="submit" :label="__('invoicing.inquiries.action.answer')" />
                            </x-action-form>
                        @else
                            <x-action-form :action="route('invoices.inquiries.reopen', $inquiry)">
                                <x-icon-btn icon="undo" tone="ghost" size="sm" type="submit" :label="__('invoicing.inquiries.action.reopen')" />
                            </x-action-form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$inquiries" standing />
    @endif
</x-index-page>
@endsection
