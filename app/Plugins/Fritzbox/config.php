<?php
/*
 * Created on   : Thu Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 | FRITZ!Box-Anruflisten-Import (CSV-Upload + Telefonbericht per Mail).
 | Telefonate bekannter Kundennummern buchen als Zeiteinträge; Anrufe, die eine
 | gebuchte Fernwartungszeit desselben Kunden überlappen oder ihr vorausgehen,
 | verschmelzen mit dem bestehenden Eintrag. Unbekannte Nummern landen in der
 | universellen Zuordnungs-Inbox. Eingehängt vom FritzboxServiceProvider unter
 | `plugins.fritzbox`. ENV nur als Fallback (Tests/Konsole).
 */
return [
    'enabled' => env('FRITZBOX_ENABLED', false),
    // Wenn false, werden importierte Telefonate nie als abrechenbar markiert.
    'default_billable' => (bool) env('FRITZBOX_DEFAULT_BILLABLE', true),
    // Benutzer, dem importierte Telefonate zugeordnet werden (sonst Org-Owner / erster Benutzer).
    'default_user_id' => env('FRITZBOX_DEFAULT_USER_ID'),
    // Gespräche unterhalb dieser Dauer werden übersprungen.
    'min_call_minutes' => (int) env('FRITZBOX_MIN_CALL_MINUTES', 2),
    // Endet ein Anruf höchstens so viele Minuten vor einer gebuchten Fernwartungszeit, wird verschmolzen.
    'call_lead_minutes' => (int) env('FRITZBOX_CALL_LEAD_MINUTES', 15),
    // Kommagetrennt: nur Anrufe über diese eigenen Rufnummern importieren (leer = alle).
    'own_number_allowlist' => env('FRITZBOX_OWN_NUMBER_ALLOWLIST', ''),
    // Ältere FRITZ!OS-Firmware exportiert ausgehende Anrufe als Typ 3 (neuere: Typ 4).
    'type3_outgoing' => (bool) env('FRITZBOX_TYPE3_OUTGOING', false),
];
