{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('CalDAV'))
@section('nav-title', __('CalDAV'))

@section('content')
<x-index-page :title="__('caldav::caldav.title')" :subtitle="__('caldav::caldav.intro')">
    <x-slot:badges>
        @if ($connection && $connection->isActive())
            <x-plugin-health :plugin-id="\App\Plugins\CalDav\CalDavPlugin::ID" :state="$healthState" />
        @elseif ($connection)
            <x-status-badge>{{ __('caldav::caldav.health.inactive') }}</x-status-badge>
        @endif
    </x-slot:badges>

    <x-validation-errors first />

    {{-- Status + Aktionen --}}
    <x-card>
        @if ($connection && $connection->isActive())
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.caldav.publish') }}">
                    @csrf
                    <x-button type="submit">{{ __('caldav::caldav.action.publish') }}</x-button>
                </form>
                <form method="POST" action="{{ route('admin.caldav.disconnect') }}">
                    @csrf
                    <x-button type="submit" tone="ghost">{{ __('caldav::caldav.action.disconnect') }}</x-button>
                </form>
            </div>
        @endif
    </x-card>

    {{-- Anbindung --}}
    <x-card as="form" class="space-y-3" method="POST" action="{{ route('admin.caldav.connection.store') }}">
        @csrf
        <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('caldav::caldav.connection.heading') }}</h2>

        <div class="grid gap-3 md:grid-cols-2">
            <label class="form-control">
                <span class="label-text">{{ __('caldav::caldav.field.name') }}</span>
                <input type="text" name="name" value="{{ old('name', $connection->name ?? '') }}"
                       class="input input-bordered input-sm" required>
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('caldav::caldav.field.base_url') }}</span>
                <input type="url" name="base_url" value="{{ old('base_url', $connection->base_url ?? '') }}"
                       placeholder="https://cloud.example.com/remote.php/dav" class="input input-bordered input-sm" required>
                <span class="label-text-alt text-muted">{{ __('caldav::caldav.field.base_url_help') }}</span>
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('caldav::caldav.field.username') }}</span>
                <input type="text" name="username" value="{{ old('username', $connection->username ?? '') }}"
                       autocomplete="off" class="input input-bordered input-sm" required>
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('caldav::caldav.field.app_password') }}</span>
                <input type="password" name="app_password" autocomplete="new-password"
                       placeholder="{{ $connection ? __('caldav::caldav.field.password_keep') : '' }}"
                       class="input input-bordered input-sm" @required(! $connection)>
                <span class="label-text-alt text-muted">{{ __('caldav::caldav.field.password_help') }}</span>
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('caldav::caldav.field.calendar_path') }}</span>
                <input type="text" name="calendar_path" value="{{ old('calendar_path', $connection->calendar_path ?? '') }}"
                       placeholder="calendars/team/dienstplan" class="input input-bordered input-sm" required>
                <span class="label-text-alt text-muted">{{ __('caldav::caldav.field.calendar_path_help') }}</span>
            </label>
            <label class="form-control justify-end">
                <span class="label cursor-pointer justify-start gap-2">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" class="toggle toggle-sm toggle-primary"
                           @checked(old('active', $connection->active ?? true))>
                    <span class="label-text">{{ __('caldav::caldav.field.active') }}</span>
                </span>
            </label>

            {{-- MVP-610b: Der Rückimport ändert Daten und läuft deshalb nur auf Zuruf. --}}
            <label class="form-control justify-end" title="{{ __('caldav::caldav.field.two_way_help') }}">
                <span class="label cursor-pointer justify-start gap-2">
                    <input type="hidden" name="two_way" value="0">
                    <input type="checkbox" name="two_way" value="1" class="toggle toggle-sm toggle-primary"
                           @checked(old('two_way', $connection->two_way ?? false))>
                    <span class="label-text">{{ __('caldav::caldav.field.two_way') }}</span>
                </span>
            </label>
        </div>

        {{-- Publish-Scopes (Rang 17): Termine und/oder Dienstpläne/Urlaube. --}}
        @php $currentScopes = (array) old('scopes', $connection->scopes ?? ['events']); @endphp
        <div class="form-control">
            <span class="label-text">{{ __('caldav::caldav.field.scopes') }}</span>
            <div class="flex flex-wrap gap-4 pt-1">
                <label class="label cursor-pointer justify-start gap-2">
                    <input type="checkbox" name="scopes[]" value="events" class="checkbox checkbox-sm"
                           @checked(in_array('events', $currentScopes, true))>
                    <span class="label-text">{{ __('caldav::caldav.field.scope_events') }}</span>
                </label>
                <label class="label cursor-pointer justify-start gap-2">
                    <input type="checkbox" name="scopes[]" value="schedule" class="checkbox checkbox-sm"
                           @checked(in_array('schedule', $currentScopes, true))>
                    <span class="label-text">{{ __('caldav::caldav.field.scope_schedule') }}</span>
                </label>
            </div>
            <span class="label-text-alt text-muted">{{ __('caldav::caldav.field.scopes_help') }}</span>
        </div>

        <div class="flex justify-end">
            <x-button type="submit">{{ __('caldav::caldav.action.save') }}</x-button>
        </div>
    </x-card>
</x-index-page>
@endsection
