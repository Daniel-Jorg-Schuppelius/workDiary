{{--
  Created on   : Fri May 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : calendar.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
            @if ($user->calendar_feed_token_hash)
                {{-- Der Klartext-Token wird nur als Hash gespeichert (S-44) und
                     ist deshalb ausschließlich direkt nach dem Rotieren
                     sichtbar. Danach zeigt die Seite nur noch, DASS ein Link
                     besteht. --}}
                @if (session('calendar_feed_token'))
                    @php($url = route('calendar.feed.personal', ['token' => session('calendar_feed_token')]))
                    <x-form-group :label="__('Abo-URL')" name="feed_url">
                        <div class="join w-full">
                            <input type="text" readonly value="{{ $url }}" class="input input-bordered join-item w-full font-mono text-xs">
                            <button type="button" class="btn join-item" data-copy-text="{{ $url }}">
                                {{ __('Kopieren') }}
                            </button>
                        </div>
                        <x-slot:hint>{{ __('Jetzt kopieren — der Link wird nicht gespeichert und ist später nicht mehr abrufbar.') }}</x-slot:hint>
                    </x-form-group>
                @else
                    <div class="alert alert-info text-sm">
                        {{ __('Ein Kalender-Link ist aktiv. Aus Sicherheitsgründen wird er nicht gespeichert und kann nicht erneut angezeigt werden — bei Verlust einen neuen erzeugen („Token rotieren").') }}
                    </div>
                @endif

                <div class="text-sm text-base-content/70">
                    <strong>{{ __('Hinweis Google:') }}</strong> {{ __('„Andere Kalender → Per URL hinzufügen" und obigen Link einfügen.') }}<br>
                    <strong>{{ __('Hinweis Outlook:') }}</strong> {{ __('„Kalender hinzufügen → Aus dem Internet" und obigen Link einfügen.') }}
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-action-form :action="route('account.calendar.rotate')" :confirm="__('Token rotieren? Bestehende Abos brechen ab.')">
                        <x-button type="submit" tone="warning" size="sm">{{ __('Token rotieren') }}</x-button>
                    </x-action-form>
                    <x-action-form :action="route('account.calendar.revoke')" method="DELETE" :confirm="__('Kalender-Link wirklich widerrufen?')">
                        <x-button type="submit" tone="error" size="sm">{{ __('Widerrufen') }}</x-button>
                    </x-action-form>
                </div>
            @else
                <p>{{ __('Es ist noch kein Kalender-Link aktiv.') }}</p>
                <form method="POST" action="{{ route('account.calendar.rotate') }}">
                    @csrf
                    <x-button type="submit" tone="primary">{{ __('Kalender-Link erzeugen') }}</x-button>
                </form>
            @endif
        </div>
    </div>
</x-page-shell>
@endsection
