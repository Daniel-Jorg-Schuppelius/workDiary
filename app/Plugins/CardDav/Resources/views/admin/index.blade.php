{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('CardDAV'))
@section('nav-title', __('CardDAV'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        @if (session('success'))
            <div class="alert alert-success text-sm">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error text-sm">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error text-sm">{{ $errors->first() }}</div>
        @endif

        {{-- Status + Aktionen --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('carddav.title') }}</h1>
                @if ($connection && $connection->isActive())
                    @if (($health['ok'] ?? false))
                        <span class="badge badge-success badge-sm">{{ __('carddav.health.ok') }}</span>
                    @else
                        <span class="badge badge-error badge-sm">{{ __('carddav.health.failing') }}</span>
                    @endif
                @elseif ($connection)
                    <span class="badge badge-ghost badge-sm">{{ __('carddav.health.inactive') }}</span>
                @endif
            </div>
            <p class="mb-4 text-sm text-muted">{{ __('carddav.intro') }}</p>

            @if ($connection && $connection->hasConnectionError())
                <div class="alert alert-warning mb-4 text-sm">
                    {{ __('carddav.health.last_error', ['error' => $connection->last_error ?? '—']) }}
                </div>
            @endif

            @if ($connection && $connection->isActive())
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.carddav.discover') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-secondary">{{ __('carddav.action.discover') }}</button>
                    </form>
                    @if ($connection->isSyncable())
                        <form method="POST" action="{{ route('admin.carddav.sync') }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('carddav.action.sync') }}</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.carddav.disconnect') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-ghost">{{ __('carddav.action.disconnect') }}</button>
                    </form>
                </div>
                @if ($connection->last_synced_at)
                    <p class="mt-2 text-xs text-muted">
                        {{ __('carddav.status.last_synced', ['at' => $connection->last_synced_at->diffForHumans()]) }}
                    </p>
                @endif
            @endif
        </div>

        {{-- Adressbuch-Wahl (Ergebnis der letzten Discovery) --}}
        @if ($connection && ($addressbooks !== [] || $connection->addressbook_url))
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('carddav.addressbook.heading') }}</h2>

                @if ($connection->addressbook_url)
                    <p class="text-sm text-base-content/70">
                        {{ __('carddav.addressbook.current', ['name' => $connection->addressbook_name ?: $connection->addressbook_url]) }}
                    </p>
                @endif

                @if ($addressbooks !== [])
                    <form method="POST" action="{{ route('admin.carddav.addressbook') }}" class="space-y-2">
                        @csrf
                        @foreach ($addressbooks as $book)
                            <label class="label cursor-pointer justify-start gap-2">
                                <input type="radio" name="addressbook_url" value="{{ $book['url'] }}" class="radio radio-sm"
                                       @checked(($connection->addressbook_url ?? null) === $book['url'])>
                                <span class="label-text">{{ $book['name'] ?: $book['url'] }}</span>
                                <span class="text-xs text-muted">{{ $book['url'] }}</span>
                            </label>
                        @endforeach
                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('carddav.action.choose_addressbook') }}</button>
                        </div>
                    </form>
                @else
                    <p class="text-xs text-muted">{{ __('carddav.addressbook.hint') }}</p>
                @endif
            </div>
        @endif

        {{-- Anbindung --}}
        <form method="POST" action="{{ route('admin.carddav.connection.store') }}"
              class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
            @csrf
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('carddav.connection.heading') }}</h2>

            <div class="grid gap-3 md:grid-cols-2">
                <label class="form-control">
                    <span class="label-text">{{ __('carddav.field.name') }}</span>
                    <input type="text" name="name" value="{{ old('name', $connection->name ?? '') }}"
                           class="input input-bordered input-sm" required>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('carddav.field.base_url') }}</span>
                    <input type="url" name="base_url" value="{{ old('base_url', $connection->base_url ?? '') }}"
                           placeholder="https://cloud.example.com/remote.php/dav" class="input input-bordered input-sm" required>
                    <span class="label-text-alt text-muted">{{ __('carddav.field.base_url_help') }}</span>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('carddav.field.username') }}</span>
                    <input type="text" name="username" value="{{ old('username', $connection->username ?? '') }}"
                           autocomplete="off" class="input input-bordered input-sm" required>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('carddav.field.app_password') }}</span>
                    <input type="password" name="app_password" autocomplete="new-password"
                           placeholder="{{ $connection ? __('carddav.field.password_keep') : '' }}"
                           class="input input-bordered input-sm" @required(! $connection)>
                    <span class="label-text-alt text-muted">{{ __('carddav.field.password_help') }}</span>
                </label>
                <label class="form-control justify-end">
                    <span class="label cursor-pointer justify-start gap-2">
                        <input type="hidden" name="allow_private_network" value="0">
                        <input type="checkbox" name="allow_private_network" value="1" class="toggle toggle-sm toggle-warning"
                               @checked(old('allow_private_network', $connection->allow_private_network ?? false))>
                        <span class="label-text">{{ __('carddav.field.allow_private_network') }}</span>
                    </span>
                    <span class="label-text-alt text-muted">{{ __('carddav.field.allow_private_network_help') }}</span>
                </label>
                <label class="form-control justify-end">
                    <span class="label cursor-pointer justify-start gap-2">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" class="toggle toggle-sm toggle-primary"
                               @checked(old('active', $connection->active ?? true))>
                        <span class="label-text">{{ __('carddav.field.active') }}</span>
                    </span>
                </label>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-sm btn-primary">{{ __('carddav.action.save') }}</button>
            </div>
        </form>
    </div>
</x-page-shell>
@endsection
