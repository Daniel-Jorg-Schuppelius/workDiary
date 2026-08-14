{{--
  Created on   : Sat May 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : 423.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- errors/_page-Gerüst statt eigener Kopie (Vollaudit 2026-07, N42);
     reportable=false wie zuvor — ein Lizenz-Block ist kein meldbarer Fehler. --}}
@include('errors._page', [
    'code' => 423,
    'icon' => 'workspace_premium',
    'tone' => 'primary',
    'title' => __('Modul nicht im Plan enthalten'),
    'message' => ($exception ?? null) && $exception->getMessage() ? $exception->getMessage() : __('Diese Funktion ist in Ihrem aktuellen Plan nicht verfügbar.'),
    'extraNote' => __('Für den Zugang ist ein höherer Plan erforderlich. Bitte wenden Sie sich an Ihre Administration.'),
    'reportable' => false,
])
