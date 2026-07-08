@extends('layouts.app')
@section('title', __('CalDAV'))
@section('nav-title', __('CalDAV'))

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
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('caldav.title') }}</h1>
                @if ($connection && $connection->isActive())
                    @if (($health['ok'] ?? false))
                        <span class="badge badge-success badge-sm">{{ __('caldav.health.ok') }}</span>
                    @else
                        <span class="badge badge-error badge-sm">{{ __('caldav.health.failing') }}</span>
                    @endif
                @elseif ($connection)
                    <span class="badge badge-ghost badge-sm">{{ __('caldav.health.inactive') }}</span>
                @endif
            </div>
            <p class="mb-4 text-sm text-base-content/60">{{ __('caldav.intro') }}</p>

            @if ($connection && $connection->isActive())
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.caldav.publish') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('caldav.action.publish') }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.caldav.disconnect') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-ghost">{{ __('caldav.action.disconnect') }}</button>
                    </form>
                </div>
            @endif
        </div>

        {{-- Anbindung --}}
        <form method="POST" action="{{ route('admin.caldav.connection.store') }}"
              class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
            @csrf
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('caldav.connection.heading') }}</h2>

            <div class="grid gap-3 md:grid-cols-2">
                <label class="form-control">
                    <span class="label-text">{{ __('caldav.field.name') }}</span>
                    <input type="text" name="name" value="{{ old('name', $connection->name ?? '') }}"
                           class="input input-bordered input-sm" required>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('caldav.field.base_url') }}</span>
                    <input type="url" name="base_url" value="{{ old('base_url', $connection->base_url ?? '') }}"
                           placeholder="https://cloud.example.com/remote.php/dav" class="input input-bordered input-sm" required>
                    <span class="label-text-alt text-base-content/50">{{ __('caldav.field.base_url_help') }}</span>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('caldav.field.username') }}</span>
                    <input type="text" name="username" value="{{ old('username', $connection->username ?? '') }}"
                           autocomplete="off" class="input input-bordered input-sm" required>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('caldav.field.app_password') }}</span>
                    <input type="password" name="app_password" autocomplete="new-password"
                           placeholder="{{ $connection ? __('caldav.field.password_keep') : '' }}"
                           class="input input-bordered input-sm" @required(! $connection)>
                    <span class="label-text-alt text-base-content/50">{{ __('caldav.field.password_help') }}</span>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('caldav.field.calendar_path') }}</span>
                    <input type="text" name="calendar_path" value="{{ old('calendar_path', $connection->calendar_path ?? '') }}"
                           placeholder="calendars/team/dienstplan" class="input input-bordered input-sm" required>
                    <span class="label-text-alt text-base-content/50">{{ __('caldav.field.calendar_path_help') }}</span>
                </label>
                <label class="form-control justify-end">
                    <span class="label cursor-pointer justify-start gap-2">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" class="toggle toggle-sm toggle-primary"
                               @checked(old('active', $connection->active ?? true))>
                        <span class="label-text">{{ __('caldav.field.active') }}</span>
                    </span>
                </label>
            </div>

            {{-- Publish-Scopes (Rang 17): Termine und/oder Dienstpläne/Urlaube. --}}
            @php $currentScopes = (array) old('scopes', $connection->scopes ?? ['events']); @endphp
            <div class="form-control">
                <span class="label-text">{{ __('caldav.field.scopes') }}</span>
                <div class="flex flex-wrap gap-4 pt-1">
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="checkbox" name="scopes[]" value="events" class="checkbox checkbox-sm"
                               @checked(in_array('events', $currentScopes, true))>
                        <span class="label-text">{{ __('caldav.field.scope_events') }}</span>
                    </label>
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="checkbox" name="scopes[]" value="schedule" class="checkbox checkbox-sm"
                               @checked(in_array('schedule', $currentScopes, true))>
                        <span class="label-text">{{ __('caldav.field.scope_schedule') }}</span>
                    </label>
                </div>
                <span class="label-text-alt text-base-content/50">{{ __('caldav.field.scopes_help') }}</span>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-sm btn-primary">{{ __('caldav.action.save') }}</button>
            </div>
        </form>
    </div>
</x-page-shell>
@endsection
