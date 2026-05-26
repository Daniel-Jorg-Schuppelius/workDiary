@extends('layouts.app')

@section('title', __('Kalender-Abo'))
@section('nav-title', __('Kalender-Abo'))

@section('content')
<x-page-shell>
    <div role="alert" class="alert alert-info alert-soft">
        <x-icon name="calendar_month" />
        <div>
            <h3 class="font-semibold">{{ __('Persönlicher Kalender-Feed') }}</h3>
            <div class="text-sm">
                {{ __('Abonnieren Sie Ihre genehmigten Urlaube und geplanten Schichten in einem externen Kalender (Google, Outlook, Apple). Der Link enthält einen zufälligen Token; geben Sie ihn nicht weiter.') }}
            </div>
        </div>
    </div>

    <div class="card bg-base-100 border border-base-300">
        <div class="card-body space-y-4">
            @if ($user->calendar_feed_token)
                @php($url = route('calendar.feed.personal', ['token' => $user->calendar_feed_token]))
                <x-form-group :label="__('Abo-URL')" name="feed_url">
                    <div class="join w-full">
                        <input type="text" readonly value="{{ $url }}" class="input input-bordered join-item w-full font-mono text-xs">
                        <button type="button" class="btn join-item" onclick="navigator.clipboard.writeText('{{ $url }}')">
                            {{ __('Kopieren') }}
                        </button>
                    </div>
                </x-form-group>

                <div class="text-sm text-base-content/70">
                    <strong>{{ __('Hinweis Google:') }}</strong> {{ __('„Andere Kalender → Per URL hinzufügen" und obigen Link einfügen.') }}<br>
                    <strong>{{ __('Hinweis Outlook:') }}</strong> {{ __('„Kalender hinzufügen → Aus dem Internet" und obigen Link einfügen.') }}
                </div>

                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('account.calendar.rotate') }}"
                          data-confirm-dialog data-confirm-message="{{ __('Token rotieren? Bestehende Abos brechen ab.') }}">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-sm">{{ __('Token rotieren') }}</button>
                    </form>
                    <form method="POST" action="{{ route('account.calendar.revoke') }}"
                          data-confirm-dialog data-confirm-message="{{ __('Kalender-Link wirklich widerrufen?') }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-error btn-sm">{{ __('Widerrufen') }}</button>
                    </form>
                </div>
            @else
                <p>{{ __('Es ist noch kein Kalender-Link aktiv.') }}</p>
                <form method="POST" action="{{ route('account.calendar.rotate') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">{{ __('Kalender-Link erzeugen') }}</button>
                </form>
            @endif
        </div>
    </div>
</x-page-shell>
@endsection
