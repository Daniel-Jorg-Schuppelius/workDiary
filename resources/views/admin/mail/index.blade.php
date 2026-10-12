{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('mail.title'))
@section('nav-title', __('mail.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('mail.intro')">
    <x-slot:actions>
        @if ($transports === [])
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                        :href="route('admin.mail.connection.create', ['transport' => \App\Models\Mail\EmailConnection::TRANSPORT_IMAP])"
                        show-label>{{ __('mail.action.create') }}</x-icon-btn>
        @else
            <x-action-menu icon="add" tone="primary" :label="__('mail.action.create')">
                <x-icon-btn icon="mail" size="sm" data-entry-modal-trigger
                            :href="route('admin.mail.connection.create', ['transport' => \App\Models\Mail\EmailConnection::TRANSPORT_IMAP])"
                            show-label>{{ __('mail.transport.imap') }}</x-icon-btn>
                @foreach ($transports as $transport)
                    <x-icon-btn icon="mail" size="sm" data-entry-modal-trigger
                                :href="route('admin.mail.connection.create', ['transport' => $transport->key()])"
                                show-label>{{ $transport->label() }}</x-icon-btn>
                @endforeach
            </x-action-menu>
        @endif
        <x-action-form :action="route('admin.mail.poll')">
            <x-icon-btn icon="sync" size="sm" type="submit" show-label>{{ __('mail.action.poll') }}</x-icon-btn>
        </x-action-form>
        <x-icon-btn icon="inbox" size="sm" :href="route('admin.integration.inbox', ['plugin' => 'email'])" show-label>
            {{ $openCount > 0 ? __('mail.to_inbox_count', ['count' => $openCount]) : __('mail.to_inbox') }}
        </x-icon-btn>
    </x-slot:actions>

    <x-table scroll="flex" :pinRows="true">
        <x-slot:head>
            <tr>
                <th>{{ __('mail.field.name') }}</th>
                <th>{{ __('mail.col.host') }}</th>
                <th>{{ __('mail.field.folder') }}</th>
                <th>{{ __('mail.col.purpose') }}</th>
                <th>{{ __('mail.col.status') }}</th>
                <th>{{ __('mail.col.last_polled') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($connections as $connection)
            @php
                /** @var \App\Models\Mail\EmailConnection $connection */
                $cursor = \App\Services\Mail\MailboxCursor::of($connection);
            @endphp
            <tr>
                <td>{{ $connection->name }}</td>
                <td class="text-muted">
                    @if ($connection->usesImap())
                        {{ $connection->username }}&#64;{{ $connection->host }}
                    @else
                        {{ $connection->mailbox_address ?: __('mail.access.own_mailbox') }}
                        <span class="text-xs">· {{ collect($transports)->first(fn ($t) => $t->key() === $connection->transport)?->label() ?? $connection->transport }}</span>
                    @endif
                </td>
                <td>
                    <span class="font-mono text-sm">{{ $connection->folder }}</span>
                    @if ($connection->processed_folder)
                        <span class="text-xs text-muted">→ {{ $connection->processed_folder }}</span>
                    @endif
                    <div class="text-xs text-muted">{{ __('mail.col.fetch_since', ['date' => $connection->fetch_since?->fdate() ?? '—']) }}</div>
                </td>
                <td>
                    @if ($connection->einvoice_intake)
                        <x-status-badge size="xs" tone="info">{{ __('mail.purpose.invoices') }}</x-status-badge>
                    @endif
                    @if ($connection->callreport_intake)
                        <x-status-badge size="xs" tone="info">{{ __('mail.purpose.callreports') }}</x-status-badge>
                    @endif
                    @if (! $connection->einvoice_intake && ! $connection->callreport_intake)
                        <span class="text-xs text-muted">{{ __('mail.purpose.inbox') }}</span>
                    @endif
                </td>
                <td>
                    @if ($connection->isActive())
                        <x-status-badge size="xs" tone="success">{{ __('mail.status.active') }}</x-status-badge>
                    @else
                        <x-status-badge size="xs">{{ __('mail.status.inactive') }}</x-status-badge>
                    @endif
                    @if ($connection->hasConnectionError())
                        <x-status-badge size="xs" tone="error" :title="$connection->last_error">{{ __('mail.status.error') }}</x-status-badge>
                        <div class="text-xs text-error">{{ \Illuminate\Support\Str::limit((string) $connection->last_error, 120) }}</div>
                    @endif
                    @if ($cursor->backfill && $cursor->catchingUp)
                        <div class="text-xs text-muted">{{ __('mail.status.backfill_running', ['count' => $cursor->processed]) }}</div>
                    @endif
                </td>
                <td class="text-muted">{{ $connection->last_polled_at?->diffForHumans() ?? '—' }}</td>
                <td class="text-right whitespace-nowrap">
                    <x-icon-btn icon="edit" tone="ghost" size="xs" data-entry-modal-trigger
                                :href="route('admin.mail.connection.edit', $connection)"
                                :label="__('mail.action.edit')" />
                    <x-icon-btn icon="history" tone="ghost" size="xs" data-entry-modal-trigger
                                :href="route('admin.mail.connection.backfill.form', $connection)"
                                :label="__('mail.action.backfill')" />
                    @if ($connection->active)
                        <x-action-form :action="route('admin.mail.disconnect')" class="inline">
                            <input type="hidden" name="connection" value="{{ $connection->sqid }}">
                            <x-icon-btn icon="link_off" tone="ghost" size="xs" type="submit" class="text-error" :label="__('mail.action.disconnect')" />
                        </x-action-form>
                    @else
                        <x-action-form :action="route('admin.mail.connection.activate', $connection)" class="inline">
                            <x-icon-btn icon="power_settings_new" tone="ghost" size="xs" type="submit" :label="__('mail.action.activate')" />
                        </x-action-form>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="7" icon="mail" :label="__('mail.no_connections')" />
        @endforelse
    </x-table>
</x-index-page>
@endsection
