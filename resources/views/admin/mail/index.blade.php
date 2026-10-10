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

@section('content')
<x-page-shell>
    <div class="space-y-4">
        <x-validation-errors first />

        {{-- Einführung + Aktionen --}}
        <x-card>
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('mail.title') }}</h1>
                <div class="flex items-center gap-2">
                    <x-button :href="route('admin.integration.inbox', ['plugin' => 'email'])" tone="ghost">
                        {{ __('mail.to_inbox') }}
                        @if ($openCount > 0)
                            <x-status-badge tone="warning" class="ml-1">{{ $openCount }}</x-status-badge>
                        @endif
                    </x-button>
                    <form method="POST" action="{{ route('admin.mail.poll') }}">
                        @csrf
                        <x-button type="submit">{{ __('mail.action.poll') }}</x-button>
                    </form>
                </div>
            </div>
            <p class="text-sm text-muted">{{ __('mail.intro') }}</p>
        </x-card>

        {{-- Vorhandene Postfächer --}}
        <x-card>
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('mail.mailboxes_heading') }}</h2>
            @if ($connections->isEmpty())
                <p class="text-sm text-muted">{{ __('mail.no_connections') }}</p>
            @else
                <x-table>
                    <x-slot:head>
                            <tr>
                                <th>{{ __('mail.field.name') }}</th>
                                <th>{{ __('mail.col.host') }}</th>
                                <th>{{ __('mail.col.status') }}</th>
                                <th>{{ __('mail.col.last_polled') }}</th>
                                <th></th>
                            </tr>
                    </x-slot:head>
                            @foreach ($connections as $connection)
                                <tr>
                                    <td>{{ $connection->name }}</td>
                                    <td class="text-muted">{{ $connection->username . '@' . $connection->host }}</td>
                                    <td>
                                        @if ($connection->isActive())
                                            <x-status-badge tone="success">{{ __('mail.status.active') }}</x-status-badge>
                                        @else
                                            <x-status-badge>{{ __('mail.status.inactive') }}</x-status-badge>
                                        @endif
                                    </td>
                                    <td class="text-muted">{{ $connection->last_polled_at?->diffForHumans() ?? '—' }}</td>
                                    <td class="text-right">
                                        @if ($connection->isActive())
                                            <form method="POST" action="{{ route('admin.mail.disconnect') }}">
                                                @csrf
                                                <input type="hidden" name="connection" value="{{ $connection->sqid }}">
                                                <x-button type="submit" tone="ghost" size="xs" class="text-error">{{ __('mail.action.disconnect') }}</x-button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                </x-table>
            @endif
        </x-card>

        {{-- Neues Postfach --}}
        <x-card as="form" class="space-y-3" method="POST" action="{{ route('admin.mail.connection.store') }}">
            @csrf
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('mail.add_heading') }}</h2>
            <div class="grid gap-3 md:grid-cols-2">
                <label class="form-control">
                    <span class="label-text">{{ __('mail.field.name') }}</span>
                    <input type="text" name="name" value="{{ old('name') }}" class="input input-bordered input-sm" required>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('mail.field.transport') }}</span>
                    <select name="transport" class="select select-bordered select-sm">
                        <option value="imap" @selected(old('transport', 'imap') === 'imap')>IMAP</option>
                        @foreach ($transports as $transport)
                            <option value="{{ $transport->key() }}" @selected(old('transport') === $transport->key())>{{ $transport->label() }}</option>
                        @endforeach
                    </select>
                    @foreach ($transports as $transport)
                        @if ($transport->hint())
                            <span class="label-text-alt text-muted">{{ $transport->hint() }}</span>
                        @endif
                    @endforeach
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('mail.field.host') }}</span>
                    <input type="text" name="host" value="{{ old('host') }}" placeholder="imap.example.com" class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('mail.field.port') }}</span>
                    <input type="number" name="port" value="{{ old('port', 993) }}" min="1" max="65535" class="input input-bordered input-sm" required>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('mail.field.encryption') }}</span>
                    <select name="encryption" class="select select-bordered select-sm">
                        <option value="ssl" @selected(old('encryption', 'ssl') === 'ssl')>SSL</option>
                        <option value="tls" @selected(old('encryption') === 'tls')>STARTTLS</option>
                        <option value="none" @selected(old('encryption') === 'none')>{{ __('mail.encryption.none') }}</option>
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('mail.field.username') }}</span>
                    <input type="text" name="username" value="{{ old('username') }}" autocomplete="off" class="input input-bordered input-sm" required>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('mail.field.password') }}</span>
                    <input type="password" name="password" autocomplete="new-password" class="input input-bordered input-sm" required>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('mail.field.folder') }}</span>
                    <input type="text" name="folder" value="{{ old('folder', 'INBOX') }}" class="input input-bordered input-sm" required>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('mail.field.processed_folder') }}</span>
                    <input type="text" name="processed_folder" value="{{ old('processed_folder') }}" placeholder="{{ __('mail.field.processed_folder_placeholder') }}" class="input input-bordered input-sm">
                </label>
                <label class="form-control justify-end">
                    <span class="label cursor-pointer justify-start gap-2">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" class="toggle toggle-sm toggle-primary" @checked(old('active', true))>
                        <span class="label-text">{{ __('mail.field.active') }}</span>
                    </span>
                </label>
                <label class="form-control justify-end">
                    <span class="label cursor-pointer justify-start gap-2">
                        <input type="hidden" name="einvoice_intake" value="0">
                        <input type="checkbox" name="einvoice_intake" value="1" class="toggle toggle-sm toggle-primary" @checked(old('einvoice_intake', false))>
                        <span class="label-text">{{ __('Rechnungspostfach: Rechnungen aus den Anhängen in den Rechnungseingang übernehmen') }}</span>
                    </span>
                </label>
                <label class="form-control justify-end">
                    <span class="label cursor-pointer justify-start gap-2">
                        <input type="hidden" name="callreport_intake" value="0">
                        <input type="checkbox" name="callreport_intake" value="1" class="toggle toggle-sm toggle-primary" @checked(old('callreport_intake', false))>
                        <span class="label-text">{{ __('Telefonbericht-Postfach: FRITZ!Box-Anruflisten (CSV) in den Anruflisten-Import übernehmen') }}</span>
                    </span>
                </label>
            </div>
            <div class="flex justify-end">
                <x-button type="submit">{{ __('mail.action.save') }}</x-button>
            </div>
        </x-card>
    </div>
</x-page-shell>
@endsection
