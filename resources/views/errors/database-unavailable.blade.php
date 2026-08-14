{{--
  Created on   : Sat May 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : database-unavailable.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- errors/_page-Gerüst statt eigener Kopie (Vollaudit 2026-07, N42);
     safe=true: DB ist weg — Request-ID-Lookup und auth()-Checks würden
     erneut auf die Datenbank zugreifen. --}}
@include('errors._page', [
    'safe' => true,
    'icon' => 'database_off',
    'tone' => 'warning',
    'title' => __('Datenbank vorübergehend nicht erreichbar'),
    'message' => __('Wir können die Datenbank gerade nicht erreichen. Bitte versuche es in wenigen Augenblicken erneut. Falls das Problem bestehen bleibt, wende dich an deine Administration.'),
    'actions' => [
        ['label' => __('Erneut versuchen'), 'reload' => true, 'icon' => 'refresh'],
    ],
    'details' => config('app.debug') && ! empty($exceptionMessage) ? $exceptionMessage : null,
])
