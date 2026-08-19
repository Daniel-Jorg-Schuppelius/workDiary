<?php
/*
 * Created on   : Thu Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FritzboxPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Fritzbox;

use App\Plugins\AbstractPlugin;
use App\Plugins\Contracts\Plugin;

/**
 * FritzBox-Anruflisten-Plugin: importiert die FRITZ!Box-Anrufliste (CSV-Export
 * oder monatlicher Telefonbericht per Push-Mail) und bucht Telefonate als
 * Zeiteinträge. Rufnummern bekannter Kunden/Endkunden buchen automatisch;
 * Anrufe, die eine bereits gebuchte Fernwartungssitzung desselben Kunden
 * überlappen oder ihr unmittelbar vorausgehen, verschmelzen mit dem
 * bestehenden Eintrag statt doppelt abzurechnen ({@see FritzboxImportService}).
 * Unbekannte Nummern landen gruppiert in der universellen Zuordnungs-Inbox
 * ({@see FritzboxGroupBooker}: zuordnen/merken, geteilte Nummer, ignorieren).
 *
 * Keine TimeImport-Capability: es gibt keinen API-Pull, nur CSV/Mail-Intake.
 * Plugin-Id ist "fritzbox". Pro Organisation konfigurierbar über plugin_settings.
 */
class FritzboxPlugin extends AbstractPlugin {
    public const ID = 'fritzbox';

    /** Vom {@see \App\Providers\PluginServiceProvider} zur Provider-Registrierung ausgewertet. */
    public const SERVICE_PROVIDER = FritzboxServiceProvider::class;

    public function name(): string {
        return 'FRITZ!Box-Anrufliste';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Importiert die FRITZ!Box-Anrufliste (CSV oder Telefonbericht per E-Mail) und bucht Telefonate als Zeiteinträge — mit Verschmelzung in überlappende Fernwartungszeiten.');
    }

    public function capabilities(): array {
        return [];
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.fritzbox.index',
            'label' => __('FRITZ!Box-Import'),
            'icon' => 'call',
        ];
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'default_billable', 'label' => __('Telefonate abrechenbar buchen'), 'type' => 'boolean', 'default' => true, 'help' => __('Wenn aus, werden importierte Telefonate nie als abrechenbar markiert.')],
            ['key' => 'default_user_id', 'label' => __('Zeiten buchen für Benutzer-ID'), 'type' => 'text', 'help' => __('Optional. Leer = Organisations-Owner bzw. erster Benutzer.')],
            ['key' => 'min_call_minutes', 'label' => __('Mindestdauer (Minuten)'), 'type' => 'text', 'default' => '2', 'help' => __('Kürzere Gespräche werden übersprungen (Rückrufbitten, Fehlwahl). Verpasste Anrufe werden nie importiert.')],
            ['key' => 'call_lead_minutes', 'label' => __('Vorlauf-Fenster (Minuten)'), 'type' => 'text', 'default' => '15', 'help' => __('Endet ein Anruf höchstens so viele Minuten vor einer gebuchten Fernwartungszeit desselben Kunden, wird er mit ihr verschmolzen (Start wird vorgezogen) statt doppelt gebucht.')],
            ['key' => 'own_number_allowlist', 'label' => __('Nur eigene Rufnummern'), 'type' => 'text', 'help' => __('Optional, kommagetrennt. Nur Anrufe über diese eigenen Rufnummern werden importiert (z. B. Firmenleitung); leer = alle.')],
            ['key' => 'type3_outgoing', 'label' => __('Typ 3 als ausgehend werten'), 'type' => 'boolean', 'default' => false, 'help' => __('Ältere FRITZ!OS-Versionen exportieren ausgehende Anrufe als Typ 3, neuere als Typ 4 (Typ 3 = abgewiesen). Nur aktivieren, wenn die Liste von einer alten Firmware stammt.')],
            ['key' => 'external_contact_matching', 'label' => __('Externe Kontakte abgleichen'), 'type' => 'boolean', 'default' => true, 'help' => __('Unbekannte Rufnummern mit aktiv verbundenen Kontaktverzeichnissen wie Lexoffice und Microsoft 365 abgleichen.')],
            // MVP-534: Telefonstempeln — Anrufe auf diese eigenen Rufnummern
            // (wie in der Anrufliste ausgewiesen) werden zu Zeitstempeln.
            ['key' => 'stamp_in_line', 'label' => __('Stempel-Rufnummer: Kommen'), 'type' => 'text', 'help' => __('Eigene Rufnummer, deren Anrufe als Kommen-Stempel gelten (genau wie in der Anrufliste ausgewiesen). Leer = aus.')],
            ['key' => 'stamp_out_line', 'label' => __('Stempel-Rufnummer: Gehen'), 'type' => 'text', 'help' => __('Eigene Rufnummer, deren Anrufe als Gehen-Stempel gelten. Leer = aus.')],
            ['key' => 'stamp_toggle_line', 'label' => __('Stempel-Rufnummer: Kommen/Gehen'), 'type' => 'text', 'help' => __('Eigene Rufnummer mit Wechsel-Logik (offener Stempel → Gehen, sonst Kommen). Der Anruf wird nicht angenommen — die Rufnummer des Anrufenden wirkt als Ausweis; Zuordnung auf der FRITZ!Box-Seite pflegen.')],
        ];
    }

}
