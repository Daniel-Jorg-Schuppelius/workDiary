{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : presence-status.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    <x-presence-status :user-id="$user->id" /> — Selbstauskunft einer Person
    (Feature 103, MVP-1669): „Nicht stören“, „Kurz weg bis 14:30“, „Außer Haus
    bis 16:00“. Nur bei eingeschaltetem Anwesenheits-Board der Organisation;
    ohne gültigen Stand rendert die Komponente nichts. Die Stände der
    Organisation liest der Dienst je Anfrage einmal.
--}}
@props([
    'userId',
    'size' => 'xs',
])
@php
    $__presence = app(\App\Services\Attendance\PresenceStatusService::class);
    $__status = $__presence->visibleFor(\App\Support\OrganizationContext::current())[(int) $userId] ?? null;
@endphp
@if ($__status !== null)
    <x-status-badge :size="$size" :tone="$__status->kind->tone()" data-presence-status="{{ $__status->kind->value }}">{{ $__presence->describe($__status) }}</x-status-badge>
@endif
