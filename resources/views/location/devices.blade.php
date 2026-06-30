{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : devices.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Meine Standorterfassung'))
@section('nav-title', __('Meine Standorterfassung'))

@section('content')
<x-index-page :subtitle="__('Einwilligung verwalten und Geräte (OwnTracks/Traccar) verbinden.')">

    {{-- Einwilligung (Opt-in) --}}
    <x-card class="mb-4">
        <x-slot:title>{{ __('Einwilligung') }}</x-slot:title>
        <p class="text-sm text-base-content/70 mb-3">
            {{ __('Ohne deine Einwilligung werden keine Standortdaten angenommen oder gespeichert.') }}
        </p>
        <x-action-form :action="route('location.devices.consent')">
            <input type="hidden" name="enabled" value="{{ $optedIn ? 0 : 1 }}">
            @if ($optedIn)
                <x-status-badge tone="success" size="sm">{{ __('aktiviert') }}</x-status-badge>
                <x-icon-btn icon="toggle_off" tone="ghost" size="sm" type="submit" show-label>{{ __('Deaktivieren') }}</x-icon-btn>
            @else
                <x-status-badge tone="ghost" size="sm">{{ __('deaktiviert') }}</x-status-badge>
                <x-icon-btn icon="toggle_on" tone="primary" size="sm" type="submit" show-label>{{ __('Aktivieren') }}</x-icon-btn>
            @endif
        </x-action-form>
    </x-card>

    {{-- Einmalig angezeigte Push-URL nach dem Anlegen --}}
    @if (session('location_device_url'))
        <x-card tone="warning" class="mb-4">
            <x-slot:title>{{ __('Push-URL (nur jetzt sichtbar)') }}</x-slot:title>
            <p class="text-sm mb-2">{{ __('Trage diese URL in OwnTracks/Traccar ein. Sie wird nicht erneut angezeigt.') }}</p>
            <code class="block break-all bg-base-200 rounded p-2 text-sm">{{ session('location_device_url') }}</code>
        </x-card>
    @endif

    {{-- Neues Gerät --}}
    <x-card class="mb-4">
        <x-slot:title>{{ __('Gerät hinzufügen') }}</x-slot:title>
        <form method="POST" action="{{ route('location.devices.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <x-input-field name="label" :label="__('Bezeichnung')" :value="old('label')" maxlength="120" required placeholder="{{ __('z. B. Mein Diensthandy') }}" />
            <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Token erzeugen') }}</x-icon-btn>
        </form>
    </x-card>

    {{-- Punktueller Browser-Stempel --}}
    <x-card class="mb-4">
        <x-slot:title>{{ __('Hier einstempeln') }}</x-slot:title>
        <p class="text-sm text-base-content/70 mb-3">
            {{ __('Aktuellen Standort einmalig aus dem Browser senden.') }}
        </p>
        <x-icon-btn icon="my_location" tone="primary" size="sm" show-label
                    data-location-stamp
                    data-stamp-url="{{ url('/api/location/stamp') }}">{{ __('Standort senden') }}</x-icon-btn>
        <p class="text-error text-sm mt-2 hidden" data-stamp-error>{{ __('Standort konnte nicht gesendet werden.') }}</p>
    </x-card>

    {{-- Rückwirkender Google-Timeline-Import --}}
    <x-card class="mb-4">
        <x-slot:title>{{ __('Google-Timeline importieren') }}</x-slot:title>
        <p class="text-sm text-base-content/70 mb-3">
            {{ __('Lade einen Google-Standortverlauf (JSON-Export vom Handy) hoch.') }}
        </p>
        <form method="POST" action="{{ route('location.devices.import-google') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
            @csrf
            <input type="file" name="file" accept=".json,application/json" required class="file-input file-input-bordered file-input-sm">
            <x-icon-btn icon="upload" tone="primary" size="sm" type="submit" show-label>{{ __('Importieren') }}</x-icon-btn>
        </form>
    </x-card>

    {{-- Geräteliste --}}
    @if ($tokens->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">smartphone</span>' />
    @else
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('Gerät') }}</th>
                    <th>{{ __('Zuletzt benutzt') }}</th>
                    <th class="text-end">{{ __('Status') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($tokens as $device)
                <tr>
                    <td>{{ $device->label }}</td>
                    <td>{{ $device->last_used_at?->translatedFormat('d.m.Y H:i') ?? '—' }}</td>
                    <td class="text-end">
                        @if ($device->isActive())
                            <x-status-badge tone="success" size="sm">{{ __('aktiv') }}</x-status-badge>
                        @else
                            <x-status-badge tone="ghost" size="sm">{{ __('widerrufen') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-right">
                        @if ($device->isActive())
                            <x-action-form :action="route('location.devices.destroy', $device)" method="DELETE"
                                           :confirm="__('Gerät widerrufen?')" :confirm-label="__('Widerrufen')">
                                <x-icon-btn icon="link_off" tone="error" size="sm" type="submit" :label="__('Widerrufen')" />
                            </x-action-form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-index-page>

<script @cspNonce>
    (function () {
        var btn = document.querySelector('[data-location-stamp]');
        if (!btn || !('geolocation' in navigator)) { return; }

        function xsrf() {
            var m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
            return m ? decodeURIComponent(m[1]) : '';
        }

        btn.addEventListener('click', function () {
            var err = document.querySelector('[data-stamp-error]');
            if (err) { err.classList.add('hidden'); }
            btn.setAttribute('disabled', 'disabled');

            navigator.geolocation.getCurrentPosition(function (pos) {
                fetch(btn.dataset.stampUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-XSRF-TOKEN': xsrf(),
                    },
                    body: JSON.stringify({
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                        accuracy_m: pos.coords.accuracy,
                    }),
                }).then(function (r) {
                    if (r.ok) { window.location.reload(); return; }
                    throw new Error('stamp failed');
                }).catch(function () {
                    btn.removeAttribute('disabled');
                    if (err) { err.classList.remove('hidden'); }
                });
            }, function () {
                btn.removeAttribute('disabled');
                if (err) { err.classList.remove('hidden'); }
            });
        });
    })();
</script>
@endsection
