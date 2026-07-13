@extends('layouts.app')
@section('title', __('msgraph.title'))
@section('nav-title', __('msgraph.title'))

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
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('msgraph.title') }}</h1>
                @if ($connection && $connection->isActive())
                    @if (($health['ok'] ?? false))
                        <span class="badge badge-success badge-sm">{{ __('msgraph.health.badge_ok') }}</span>
                    @else
                        <span class="badge badge-error badge-sm">{{ __('msgraph.health.badge_failing') }}</span>
                    @endif
                @elseif ($connection)
                    <span class="badge badge-ghost badge-sm">{{ __('msgraph.health.badge_inactive') }}</span>
                @endif
            </div>
            <p class="mb-4 text-sm text-base-content/60">{{ __('msgraph.intro') }}</p>

            @unless ($configured)
                <div class="alert alert-warning text-sm">{{ __('msgraph.not_configured_hint') }}</div>
            @endunless

            @if ($connection && $connection->isActive())
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.msgraph.publish') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('msgraph.action.publish') }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.msgraph.disconnect') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-ghost">{{ __('msgraph.action.disconnect') }}</button>
                    </form>
                </div>
            @elseif ($configured)
                <form method="POST" action="{{ route('admin.msgraph.oauth.start') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('msgraph.action.connect') }}</button>
                </form>
            @endif
        </div>

        {{-- Ziel-Kalender --}}
        @if ($connection && $connection->isActive())
            <form method="POST" action="{{ route('admin.msgraph.calendar.store') }}"
                  class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
                @csrf
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('msgraph.calendar.heading') }}</h2>
                <p class="text-sm text-base-content/60">{{ __('msgraph.calendar.help') }}</p>

                <label class="form-control max-w-md">
                    <span class="label-text">{{ __('msgraph.calendar.target') }}</span>
                    <select name="calendar_id" class="select select-bordered select-sm">
                        <option value="">{{ __('msgraph.calendar.default') }}</option>
                        @foreach ($calendars as $calendar)
                            <option value="{{ $calendar['id'] }}" @selected($connection->calendar_id === $calendar['id'])>{{ $calendar['name'] }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('msgraph.action.save') }}</button>
                </div>
            </form>
        @endif
    </div>
</x-page-shell>
@endsection
