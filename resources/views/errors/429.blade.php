{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : 429.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Zu viele Anfragen: Rate-Limit oder temporäre IP-Sperre (MVP-450). --}}
@php
    $retryAfter = null;
    if (isset($exception) && method_exists($exception, 'getHeaders')) {
        $retryAfter = (int) (($exception->getHeaders()['Retry-After'] ?? 0) ?: 0);
    }
@endphp
@include('errors._page', [
    'code' => 429,
    'icon' => 'hourglass_top',
    'tone' => 'warning',
    'title' => __('Zu viele Anfragen'),
    'message' => ($exception ?? null) && $exception->getMessage() ? $exception->getMessage() : __('Bitte warten Sie einen Moment und versuchen Sie es dann erneut.'),
    'extraNote' => $retryAfter > 0 ? __('Nächster Versuch empfohlen in :seconds Sekunden.', ['seconds' => $retryAfter]) : null,
    'reportable' => false,
])
