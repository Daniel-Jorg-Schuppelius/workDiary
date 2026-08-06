@extends('layouts.app')
@section('title', __('mail.title'))
@section('nav-title', __('mail.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        @if (session('success'))
            <div class="alert alert-success text-sm">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error text-sm">{{ session('error') }}</div>
        @endif
        <x-validation-errors first />

        {{-- Einführung + Aktionen --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('mail.title') }}</h1>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.integration.inbox', ['plugin' => 'email']) }}" class="btn btn-sm btn-ghost">
                        {{ __('mail.to_inbox') }}
                        @if ($openCount > 0)
                            <span class="badge badge-sm badge-warning ml-1">{{ $openCount }}</span>
                        @endif
                    </a>
                    <form method="POST" action="{{ route('admin.mail.poll') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('mail.action.poll') }}</button>
                    </form>
                </div>
            </div>
            <p class="text-sm text-base-content/60">{{ __('mail.intro') }}</p>
        </div>

        {{-- Vorhandene Postfächer --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('mail.mailboxes_heading') }}</h2>
            @if ($connections->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('mail.no_connections') }}</p>
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
                                    <td class="text-base-content/60">{{ $connection->username . '@' . $connection->host }}</td>
                                    <td>
                                        @if ($connection->isActive())
                                            <span class="badge badge-success badge-sm">{{ __('mail.status.active') }}</span>
                                        @else
                                            <span class="badge badge-ghost badge-sm">{{ __('mail.status.inactive') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-base-content/60">{{ $connection->last_polled_at?->diffForHumans() ?? '—' }}</td>
                                    <td class="text-right">
                                        @if ($connection->isActive())
                                            <form method="POST" action="{{ route('admin.mail.disconnect') }}">
                                                @csrf
                                                <input type="hidden" name="connection" value="{{ $connection->sqid }}">
                                                <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('mail.action.disconnect') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                </x-table>
            @endif
        </div>

        {{-- Neues Postfach --}}
        <form method="POST" action="{{ route('admin.mail.connection.store') }}"
              class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
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
                        <option value="msgraph" @selected(old('transport') === 'msgraph')>{{ __('mail.transport.msgraph') }}</option>
                    </select>
                    <span class="label-text-alt text-base-content/60">{{ __('mail.transport.msgraph_hint') }}</span>
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
                        <span class="label-text">{{ __('Rechnungs-Postfach: Anhänge als E-Rechnung in den Prüfbereich übernehmen') }}</span>
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
                <button type="submit" class="btn btn-sm btn-primary">{{ __('mail.action.save') }}</button>
            </div>
        </form>
    </div>
</x-page-shell>
@endsection
