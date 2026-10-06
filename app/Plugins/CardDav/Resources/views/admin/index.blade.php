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
<x-index-page :title="__('carddav::carddav.title')" :subtitle="__('carddav::carddav.intro')">
    <x-slot:badges>
        @if ($connection && $connection->isActive())
            <x-plugin-health :plugin-id="\App\Plugins\CardDav\CardDavPlugin::ID" :state="$healthState" />
        @elseif ($connection)
            <x-status-badge>{{ __('carddav::carddav.health.inactive') }}</x-status-badge>
        @endif
    </x-slot:badges>

    <x-validation-errors first />

    {{-- Status + Aktionen --}}
    <x-card>
        @if ($connection && $connection->hasConnectionError())
            <div role="alert" class="alert alert-warning mb-4 text-sm">
                {{ __('carddav::carddav.health.last_error', ['error' => $connection->last_error ?? '—']) }}
            </div>
        @endif

        @if ($connection && $connection->isActive())
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.carddav.discover') }}">
                    @csrf
                    <x-button type="submit" tone="secondary">{{ __('carddav::carddav.action.discover') }}</x-button>
                </form>
                @if ($connection->isSyncable())
                    <form method="POST" action="{{ route('admin.carddav.sync') }}">
                        @csrf
                        <x-button type="submit">{{ __('carddav::carddav.action.sync') }}</x-button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.carddav.disconnect') }}">
                    @csrf
                    <x-button type="submit" tone="ghost">{{ __('carddav::carddav.action.disconnect') }}</x-button>
                </form>
            </div>
            @if ($connection->last_synced_at)
                <p class="mt-2 text-xs text-muted">
                    {{ __('carddav::carddav.status.last_synced', ['at' => $connection->last_synced_at->diffForHumans()]) }}
                </p>
            @endif
        @endif
    </x-card>

    {{-- Adressbuch-Wahl (Ergebnis der letzten Discovery) --}}
    @if ($connection && ($addressbooks !== [] || $connection->addressbook_url))
        <x-card class="space-y-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('carddav::carddav.addressbook.heading') }}</h2>

            @if ($connection->addressbook_url)
                <p class="text-sm text-base-content/70">
                    {{ __('carddav::carddav.addressbook.current', ['name' => $connection->addressbook_name ?: $connection->addressbook_url]) }}
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
                        <x-button type="submit">{{ __('carddav::carddav.action.choose_addressbook') }}</x-button>
                    </div>
                </form>
            @else
                <p class="text-xs text-muted">{{ __('carddav::carddav.addressbook.hint') }}</p>
            @endif
        </x-card>
    @endif

    {{-- Anbindung --}}
    <x-card as="form" class="space-y-3" method="POST" action="{{ route('admin.carddav.connection.store') }}">
        @csrf
        <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('carddav::carddav.connection.heading') }}</h2>

        <div class="grid gap-3 md:grid-cols-2">
            <label class="form-control">
                <span class="label-text">{{ __('carddav::carddav.field.name') }}</span>
                <input type="text" name="name" value="{{ old('name', $connection->name ?? '') }}"
                       class="input input-bordered input-sm" required>
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('carddav::carddav.field.base_url') }}</span>
                <input type="url" name="base_url" value="{{ old('base_url', $connection->base_url ?? '') }}"
                       placeholder="https://cloud.example.com/remote.php/dav" class="input input-bordered input-sm" required>
                <span class="label-text-alt text-muted">{{ __('carddav::carddav.field.base_url_help') }}</span>
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('carddav::carddav.field.username') }}</span>
                <input type="text" name="username" value="{{ old('username', $connection->username ?? '') }}"
                       autocomplete="off" class="input input-bordered input-sm" required>
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('carddav::carddav.field.app_password') }}</span>
                <input type="password" name="app_password" autocomplete="new-password"
                       placeholder="{{ $connection ? __('carddav::carddav.field.password_keep') : '' }}"
                       class="input input-bordered input-sm" @required(! $connection)>
                <span class="label-text-alt text-muted">{{ __('carddav::carddav.field.password_help') }}</span>
            </label>
            <label class="form-control justify-end">
                <span class="label cursor-pointer justify-start gap-2">
                    <input type="hidden" name="allow_private_network" value="0">
                    <input type="checkbox" name="allow_private_network" value="1" class="toggle toggle-sm toggle-warning"
                           @checked(old('allow_private_network', $connection->allow_private_network ?? false))>
                    <span class="label-text">{{ __('carddav::carddav.field.allow_private_network') }}</span>
                </span>
                <span class="label-text-alt text-muted">{{ __('carddav::carddav.field.allow_private_network_help') }}</span>
            </label>
            <label class="form-control justify-end">
                <span class="label cursor-pointer justify-start gap-2">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" class="toggle toggle-sm toggle-primary"
                           @checked(old('active', $connection->active ?? true))>
                    <span class="label-text">{{ __('carddav::carddav.field.active') }}</span>
                </span>
            </label>
        </div>

        <div class="flex justify-end">
            <x-button type="submit">{{ __('carddav::carddav.action.save') }}</x-button>
        </div>
    </x-card>
</x-index-page>
@endsection
