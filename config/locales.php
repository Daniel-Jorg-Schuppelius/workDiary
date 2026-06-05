<?php
/*
 * Created on   : Thu Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : locales.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Zentrale Locale-Registry — die EINZIGE Quelle für unterstützte Sprachen.
 * Middleware, LocaleController, der Sprachumschalter und das Tooling leiten
 * sich hieraus ab. Eine neue Sprache = ein Eintrag hier + `php artisan lang:sync`.
 *
 *   native:  Autonym (Eigenbezeichnung), wird unabhängig von der UI-Sprache gezeigt
 *   flag:    Flaggen-Emoji für den Umschalter
 *   carbon:  Locale-Code für Carbon (Datums-/Zeitformatierung)
 *
 * Welche dieser Sprachen tatsächlich auswählbar sind, filtert zusätzlich
 * `config('app.available_locales')` (ENV-Whitelist) — siehe App\Support\Locales.
 */

return [
    'de' => ['native' => 'Deutsch',  'flag' => '🇩🇪', 'carbon' => 'de'],
    'en' => ['native' => 'English',  'flag' => '🇬🇧', 'carbon' => 'en'],
    'fr' => ['native' => 'Français', 'flag' => '🇫🇷', 'carbon' => 'fr'],
    'it' => ['native' => 'Italiano', 'flag' => '🇮🇹', 'carbon' => 'it'],
    'es' => ['native' => 'Español',  'flag' => '🇪🇸', 'carbon' => 'es'],
];
