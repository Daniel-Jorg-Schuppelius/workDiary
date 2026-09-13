{{--
  Created on   : Thu Jul 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : 403.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    // Eigene Begründung nur, wenn eine gesetzt wurde: Gate/Policy hängt sie als
    // Response an die AuthorizationException (previous), abort(403, …) trägt sie
    // direkt. Ein schlichtes „false" liefert keine Meldung — so bleibt der
    // Framework-Wortlaut außen vor, ohne ihn zu vergleichen.
    $previous = ($exception ?? null)?->getPrevious();
    $reason = $previous instanceof \Illuminate\Auth\Access\AuthorizationException
        ? $previous->response()?->message()
        : ($exception ?? null)?->getMessage();
@endphp
@include('errors._page', [
    'code' => 403,
    'icon' => 'lock',
    'tone' => 'warning',
    'title' => __('errors.403.title'),
    'message' => is_string($reason) && $reason !== '' ? $reason : __('errors.403.message'),
])
