{{--
  Created on   : Sat Oct 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Firefly-III-Seite (Feature 176). Variablen aus FireflyAdminController::index():
  $configured, $connection, $connected, $transactions, $search, $status, $windowDays.
--}}
@extends('layouts.app')
@section('title', 'Firefly III — ' . config('app.name', 'WorkDiary'))
@section('nav-title', 'Firefly III')
@include('partials.page-fill')

@section('content')
@php $ready = $connected && $connection->isReady(); @endphp
<x-index-page overflow="clip"
    :subtitle="__('firefly::firefly.page.subtitle')"
    :badge="$connected ? __('firefly::firefly.connection.connected') : __('firefly::firefly.connection.not_connected')"
    :badge-tone="$ready ? 'success' : ($connected ? 'warning' : 'ghost')">

    <x-slot:actions>
        @if ($ready)
            <x-action-form :action="route('admin.firefly.sync')">
                <x-button type="submit" icon="sync" placement="bar">{{ __('firefly::firefly.connection.sync_now') }}</x-button>
            </x-action-form>
        @endif
        @if ($connected)
            <x-action-form :action="route('admin.firefly.verify')">
                <x-button type="submit" tone="ghost" icon="link" placement="menu">{{ __('firefly::firefly.connection.verify') }}</x-button>
            </x-action-form>
            <x-action-form :action="route('admin.firefly.disconnect')" :confirm="__('firefly::firefly.connection.confirm_disconnect')" confirm-tone="error">
                <x-button type="submit" tone="ghost" icon="link_off" placement="danger">{{ __('firefly::firefly.connection.disconnect') }}</x-button>
            </x-action-form>
        @elseif ($configured)
            <x-action-form :action="route('admin.firefly.verify')">
                <x-button type="submit" icon="link" placement="bar">{{ __('firefly::firefly.connection.verify') }}</x-button>
            </x-action-form>
        @endif
        <x-button :href="route('admin.plugins.edit', \App\Plugins\Firefly\FireflyPlugin::ID)" data-entry-modal-trigger tone="ghost" icon="key" placement="menu">{{ __('firefly::firefly.connection.settings') }}</x-button>
    </x-slot:actions>

    @unless ($configured)
        <div role="alert" class="alert alert-warning text-sm">
            <x-icon name="key_off" />
            <span>{{ __('firefly::firefly.connection.not_configured') }}</span>
        </div>
    @endunless

    <x-card :title="__('firefly::firefly.connection.title')">
        @if (! $connected)
            <div role="note" class="alert text-sm">
                <x-icon name="privacy_tip" />
                <span>{{ __('firefly::firefly.connection.privacy_note') }}</span>
            </div>
        @else
            <x-detail-grid class="sm:grid-cols-2">
                <x-detail-grid.row :label="__('firefly::firefly.connection.instance')">{{ $connection->source_name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('firefly::firefly.connection.account')">{{ $connection->account_name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('firefly::firefly.connection.window')">{{ __('firefly::firefly.connection.window_value', ['days' => $windowDays]) }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('firefly::firefly.connection.last_sync')">
                    {{ $connection->last_synced_at?->fdatetime() ?? '—' }}
                    @if ($connection->is_last_run_complete === false)
                        <x-status-badge tone="warning" size="xs">{{ __('firefly::firefly.connection.incomplete') }}</x-status-badge>
                    @endif
                </x-detail-grid.row>
                @if ($connection->last_error)
                    <x-detail-grid.row :label="__('firefly::firefly.connection.last_error')" full class="text-error">{{ $connection->last_error }}</x-detail-grid.row>
                @endif
            </x-detail-grid>
        @endif
    </x-card>

    <x-filter-bar :action="route('admin.firefly.index')" :reset="$search !== null || $status !== null ? route('admin.firefly.index') : null">
        <x-filter-field :label="__('firefly::firefly.list.search')" for="ff-q">
            <input id="ff-q" type="search" name="q" value="{{ $search }}" maxlength="100"
                   class="input input-sm input-bordered w-56 shrink-0" placeholder="{{ __('firefly::firefly.list.search_placeholder') }}">
        </x-filter-field>
        <x-filter-field :label="__('firefly::firefly.list.col.match')" for="ff-match">
            <select id="ff-match" name="match" class="select select-sm select-bordered shrink-0" data-autosubmit>
                <option value="">{{ __('firefly::firefly.list.all_matches') }}</option>
                @foreach (\App\Plugins\Firefly\Enums\FireflyMatchStatus::cases() as $option)
                    <option value="{{ $option->value }}" @selected($status === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('firefly::firefly.list.col.date') }}</th>
                <th>{{ __('firefly::firefly.list.col.kind') }}</th>
                <th>{{ __('firefly::firefly.list.col.counterparty') }}</th>
                <th>{{ __('firefly::firefly.list.col.description') }}</th>
                <th>{{ __('firefly::firefly.list.col.match') }}</th>
                <th class="text-right">{{ __('firefly::firefly.list.col.amount') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($transactions as $transaction)
            <tr>
                <td class="text-sm whitespace-nowrap">{{ $transaction->booked_on->fdate() }}</td>
                <td class="text-sm">{{ $transaction->kind->label() }}</td>
                <td class="text-sm">{{ $transaction->counterparty_name ?? '—' }}</td>
                <td class="text-sm">{{ $transaction->description !== null ? \CommonToolkit\Helper\Data\StringHelper::truncate($transaction->description, 80) : '—' }}</td>
                <td class="text-sm">
                    <x-status-badge :tone="$transaction->match_status->tone()" size="xs">{{ $transaction->match_status->label() }}</x-status-badge>
                    @if ($transaction->invoice)
                        <div><a href="{{ route('invoices.show', $transaction->invoice) }}" class="link link-hover text-xs">{{ $transaction->invoice->number }}</a></div>
                    @elseif ($transaction->incomingEinvoice?->document_id)
                        <div><a href="{{ route('finance.incoming-invoices.show', \App\Support\Sqid::encode(\App\Models\Document\Document::class, (int) $transaction->incomingEinvoice->document_id)) }}" class="link link-hover text-xs">{{ $transaction->incomingEinvoice->invoice_number }}</a></div>
                    @endif
                </td>
                <td class="text-right font-mono text-sm whitespace-nowrap">
                    {{ $transaction->booked_amount->format() }}
                    @if ($transaction->foreign_amount !== null)
                        <div class="text-xs text-muted" title="{{ __('firefly::firefly.list.foreign_amount') }}">{{ $transaction->foreign_amount->format() }}</div>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="6" icon="account_balance" :title="__('firefly::firefly.list.empty')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$transactions" standing />
</x-index-page>
@endsection
