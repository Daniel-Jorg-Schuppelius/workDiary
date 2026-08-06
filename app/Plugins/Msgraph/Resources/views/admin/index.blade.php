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

        {{-- Graph-Mail-Versand (Feature 102) --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('msgraph_mail.heading') }}</h2>
                @if ($mailConnection && $mailConnection->isActive())
                    <span class="badge badge-success badge-sm">{{ __('msgraph_mail.badge_connected') }}</span>
                @elseif ($mailConnection)
                    <span class="badge badge-ghost badge-sm">{{ __('msgraph_mail.badge_inactive') }}</span>
                @endif
            </div>
            <p class="text-sm text-base-content/60">{{ __('msgraph_mail.intro') }}</p>

            @unless ($mailerActive)
                <div class="alert alert-info text-sm">{{ __('msgraph_mail.mailer_hint') }}</div>
            @endunless

            @if ($mailConnection && $mailConnection->isActive())
                @if ($mailConnection->account_label)
                    <p class="text-sm">{{ __('msgraph_mail.account') }}: <span class="font-mono">{{ $mailConnection->account_label }}</span></p>
                @endif
                @if ($mailConnection->last_error)
                    <div role="alert" class="alert alert-warning text-sm">
                        <span>{{ $mailConnection->last_error }} <span class="text-base-content/60">({{ $mailConnection->last_error_at?->ftime() }})</span></span>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.msgraph.mail.settings') }}" class="space-y-3">
                    @csrf
                    <label class="form-control max-w-md">
                        <span class="label-text">{{ __('msgraph_mail.from_address') }}</span>
                        <input type="email" name="from_address" maxlength="190"
                               value="{{ old('from_address', $mailConnection->from_address) }}"
                               class="input input-sm input-bordered" placeholder="{{ __('msgraph_mail.from_placeholder') }}">
                        <span class="label-text-alt text-base-content/60">{{ __('msgraph_mail.from_hint') }}</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="save_to_sent_items" value="1" class="checkbox checkbox-sm"
                               @checked(old('save_to_sent_items', $mailConnection->save_to_sent_items))>
                        {{ __('msgraph_mail.save_to_sent') }}
                    </label>
                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('msgraph.action.save') }}</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.msgraph.mail.disconnect') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-ghost">{{ __('msgraph_mail.disconnect') }}</button>
                </form>
            @elseif ($configured)
                <form method="POST" action="{{ route('admin.msgraph.mail.oauth.start') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('msgraph_mail.connect') }}</button>
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

                <label class="flex items-center gap-2 text-sm" title="{{ __('msgraph.calendar.teams_meetings_hint') }}">
                    <input type="hidden" name="teams_meetings" value="0">
                    <input type="checkbox" name="teams_meetings" value="1" class="checkbox checkbox-sm"
                           @checked(old('teams_meetings', $connection->teams_meetings))>
                    {{ __('msgraph.calendar.teams_meetings') }}
                </label>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('msgraph.action.save') }}</button>
                </div>
            </form>
        @endif
    </div>
</x-page-shell>
@endsection
