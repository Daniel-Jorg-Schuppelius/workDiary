{{--
  Created on   : Tue Sep 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : 503.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Wartungsmodus des Frameworks (`php artisan down`, deploy.sh): ohne diese
     Datei zeigte Laravel seine nackte englische Standard-503-Seite. `safe`,
     weil die Datenbank während der Migration gerade umgebaut wird — kein
     Request-ID-Lookup, keine Session. deploy.sh rendert die Seite vor
     (`--render=errors::503`), damit sie auch steht, solange Composer und Vite
     die Anwendung noch nicht wieder booten lassen. --}}
@php
    $retryAfter = null;
    if (isset($exception) && method_exists($exception, 'getHeaders')) {
        $retryAfter = (int) (($exception->getHeaders()['Retry-After'] ?? 0) ?: 0);
    }
@endphp
@include('errors._page', [
    'code' => 503,
    'icon' => 'engineering',
    'tone' => 'warning',
    'title' => __('Wartungsarbeiten'),
    'message' => __('Die Anwendung wird gerade aktualisiert. Bitte versuchen Sie es in wenigen Minuten erneut.'),
    'extraNote' => $retryAfter > 0
        ? __('Nächster Versuch empfohlen in :seconds Sekunden.', ['seconds' => $retryAfter])
        : null,
    'safe' => true,
    'reportable' => false,
    'actions' => [
        ['label' => __('Erneut versuchen'), 'reload' => true, 'icon' => 'refresh'],
    ],
])
