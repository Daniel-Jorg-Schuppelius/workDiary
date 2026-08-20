{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : phone-value.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props(['number' => null])

{{--
    <x-phone-value :number="..."> — Rufnummer als tel:-Link, daneben der
    Click-to-Dial-Knopf, sobald die Organisation eine wählfähige
    Telefonanbindung eingerichtet hat (Feature 056/MVP-118, Audit W4.5).

    Der Anbindungs-Check fragt den CtiDialService direkt — bewusst ohne
    Request-Cache: `once()`/`scoped` froren in Tests und unter Octane einen
    veralteten Stand ein, und die Query ist indiziert und billig.
--}}
@php
    $_number = trim((string) $number);
    $_organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
    $_canDial = $_number !== ''
        && $_organization instanceof \App\Models\Organization
        && app(\App\Services\Cti\Dial\CtiDialService::class)->connectionFor($_organization) !== null;
@endphp

@if ($_number !== '')
    <span class="inline-flex items-center gap-1">
        <a class="link" href="tel:{{ preg_replace('/[^\d+]/', '', $_number) }}">{{ $_number }}</a>
        @if ($_canDial)
            <x-action-form :action="route('cti.dial')"
                           :confirm="__('cti.dial.confirm', ['number' => $_number])"
                           confirm-icon="call"
                           confirm-tone="info"
                           :confirm-label="__('cti.dial.action')"
                           class="inline">
                <input type="hidden" name="number" value="{{ $_number }}">
                <x-icon-btn icon="call" tone="ghost" size="xs" type="submit" :label="__('cti.dial.action')" />
            </x-action-form>
        @endif
    </span>
@endif
