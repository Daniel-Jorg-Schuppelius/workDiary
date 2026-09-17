{{--
  Created on   : Sun Sep 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : feed.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Kalender-Abo der Organisation'))
@section('nav-title', __('Kalender-Abo der Organisation'))

@section('content')
<x-page-shell>
    <div role="alert" class="alert alert-info alert-soft">
        <x-icon name="calendar_month" />
        <div>
            <h3 class="font-semibold">{{ __('Gemeinsamer Kalender-Feed') }}</h3>
            <div class="text-sm">
                {{ __('Abonnieren Sie alle Veranstaltungen mit der Sichtbarkeit „Öffentlich" in einem externen Kalender (Google, Outlook, Apple). Wer den Link hat, sieht Titel, Zeit, Ort und Räume dieser Termine — auch ohne Anmeldung.') }}
            </div>
        </div>
    </div>

    <x-card class="flex flex-col gap-2 space-y-4">
        @if ($status['issued'])
            {{-- Gespeichert ist nur der Abdruck (S-44): der Link ist ausschließlich
                 direkt nach dem Erzeugen sichtbar. --}}
            @if ($token)
                @php($url = route('calendar.feed.organization', ['token' => $token]))
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

            <dl class="text-sm text-base-content/70">
                <div class="flex gap-2">
                    <dt>{{ __('Kennung des Links') }}:</dt>
                    <dd class="font-mono">{{ $status['hint'] ?? '—' }}…</dd>
                </div>
                <div class="flex gap-2">
                    <dt>{{ __('Erzeugt am') }}:</dt>
                    <dd>{{ $issuedAt?->format('d.m.Y H:i') ?? '—' }}</dd>
                </div>
            </dl>

            <div class="text-sm text-base-content/70">
                <strong>{{ __('Hinweis Google:') }}</strong> {{ __('„Andere Kalender → Per URL hinzufügen" und obigen Link einfügen.') }}<br>
                <strong>{{ __('Hinweis Outlook:') }}</strong> {{ __('„Kalender hinzufügen → Aus dem Internet" und obigen Link einfügen.') }}
            </div>

            <div class="flex flex-wrap gap-2">
                <x-action-form :action="route('events.feed.rotate')" :confirm="__('Token rotieren? Bestehende Abos brechen ab.')">
                    <x-button type="submit" tone="warning" size="sm">{{ __('Token rotieren') }}</x-button>
                </x-action-form>
                <x-action-form :action="route('events.feed.revoke')" method="DELETE" :confirm="__('Kalender-Link wirklich widerrufen?')">
                    <x-button type="submit" tone="error" size="sm">{{ __('Widerrufen') }}</x-button>
                </x-action-form>
            </div>
        @else
            <p>{{ __('Es ist noch kein Kalender-Link aktiv.') }}</p>
            <form method="POST" action="{{ route('events.feed.rotate') }}">
                @csrf
                <x-button type="submit" tone="primary">{{ __('Kalender-Link erzeugen') }}</x-button>
            </form>
        @endif
    </x-card>
</x-page-shell>
@endsection
