{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : module-unavailable.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Modulschlüssel fehlt ({@see \App\Exceptions\ModuleKeyMissingException}); safe, weil öffentliche Portale ohne Session aufrufen. --}}
@include('errors._page', [
    'code' => 503,
    'icon' => 'lock_clock',
    'tone' => 'warning',
    'title' => __('Noch nicht eingerichtet'),
    'message' => $userMessage,
    'safe' => true,
    'reportable' => false,
])
