<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : well-known.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Support\Facades\Route;

/**
 * `/.well-known/*` — bewusst OHNE Gruppen-Stack (CRA-Tabletop 2026-09-01).
 *
 * Der CVD-Meldekanal nach RFC 9116 ist die einzige Adresse, über die ein
 * Sicherheitsforscher den Hersteller erreicht. Gebraucht wird er **genau
 * dann, wenn etwas kaputt ist** — und lag bis hierher im `web`-Stack:
 *
 *  - `RedirectIfNotInstalled` leitete ihn zum Installer um,
 *  - `HandleDatabaseUnavailable` beantwortete ihn bei DB-Ausfall mit 503,
 *  - `StartSession` (SESSION_DRIVER=database) hätte ihn ohne Datenbank
 *    ohnehin zum Absturz gebracht,
 *  - der Integritäts-Lockdown sperrte ihn im Integritätsvorfall.
 *
 * Wer die Datenbank lahmlegt, macht damit sonst nebenbei den Meldeweg
 * unerreichbar. Der Endpunkt liest ausschließlich `config()` — er braucht
 * weder Sitzung noch Mandant noch Datenbank, und bekommt deshalb keins davon.
 * Header setzt der Controller selbst.
 */
Route::get('/.well-known/security.txt', \App\Http\Controllers\SecurityTxtController::class)->name('security.txt');
Route::redirect('/security.txt', '/.well-known/security.txt');
