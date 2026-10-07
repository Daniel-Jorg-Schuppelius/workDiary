<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IndexListLayoutRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate (Konsolidierungs-Audit 2026-10, k4-14): eine Indexliste
 * steht in voller Höhe und paginiert stehend. `ViewConventionRuleTest` V3/V5
 * greifen erst, wenn `scroll="flex"` bzw. `<x-pagination>` schon da sind —
 * eine Liste, deren Controller `->get()` liefert, sah kein Gate (115 von 314).
 *
 *  L1  `index.blade.php` mit `<x-table>` trägt `<x-pagination standing>`.
 *  L2  Dieselbe Liste trägt `scroll="flex"` (V3 verlangt dann den Marker
 *      `partials.page-fill`).
 *
 * Bewusste Ausnahmen stehen mit Grund in den Listen unten (strukturell kurze
 * Stammdaten, Seiten aus mehreren Tabellen). Eine Baseline gibt es nicht mehr:
 * der Bestand ist abgearbeitet, jeder Verstoß ist ein Fehler.
 */
class IndexListLayoutRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Bereiche außerhalb der Konvention */
    private const SKIP_PREFIXES = [
        'resources/views/legacy/' => 'Legacy-Modul.',
        'resources/views/vendor/' => 'Fremd-Views.',
        'resources/views/components/' => 'Komponenten definieren die Konventionen.',
        'resources/views/layouts/' => 'Layouts.',
    ];

    /** Ein Grund für alle Portal-Listen: das Layout trägt die Voll-Höhe-Mechanik nicht. */
    private const PORTAL_LAYOUT = 'Kundenportal: customer.layout ist ein schlichtes Scroll-Layout ohne Voll-Höhe-Rahmen (kein wd-page-fill, kein main-class) — scroll="flex" griffe dort nicht; geblättert wird stehend.';

    /** @var array<string, string> L1: Listen, die bewusst nicht paginieren — Pfad => Grund */
    private const UNPAGINATED_ALLOW = [
        'app/Plugins/JtlWawi/Resources/views/admin/index.blade.php' => 'Einrichtungsseite: die Zähler des letzten Abgleichs und die Lager der angebundenen JTL-Wawi (eine Handvoll), je Zeile mit Zuordnungsformular — keine wachsende Liste.',
        'app/Plugins/Lexoffice/Resources/views/handover/index.blade.php' => 'Übergabeliste des gewählten Zeitraums: die Belege werden über alle Zeilen für ein Paket angekreuzt (ein Formular, bis 200 je Paket); der Übergabestand filtert in PHP über die Zustandstabelle.',
        'app/Plugins/Lexoffice/Resources/views/plan/index.blade.php' => 'Tarifseite: die Tabelle ist die Funktionsmatrix — eine Zeile je Lexware-Funktion (Enum LexwareFeature), keine Datensätze.',
        'app/Plugins/Msgraph/Resources/views/admin/index.blade.php' => 'Verbindungsseite mit sechs Abschnitten; die Tabelle ist die Nebenliste des Aufgabenabschnitts — von Hand angelegte Zuordnungen der To-Do-Listen des einen verbundenen Kontos.',
        'resources/views/admin/access/roles/index.blade.php' => 'Rollen der Organisation und der Plattform — Systemrollen plus wenige eigene; clientseitig nach dem übersetzten Rollennamen sortiert.',
        'resources/views/admin/ai/index.blade.php' => 'KI-Verbindungen je Anbieter (eine Handvoll) und die im Code registrierten KI-Funktionen — keine wachsende Liste.',
        'resources/views/admin/branch-profiles/index.blade.php' => 'Profilkarten aus dem Katalog im Code, in PHP gefiltert; die Tabelle zeigt die eigenen Profilvarianten der Organisation — eine Handvoll.',
        'resources/views/admin/chat/index.blade.php' => 'Chat-Kanäle der Organisation (Webhooks zu Slack, Teams und Co.) — eine Handvoll.',
        'resources/views/admin/classifications/index.blade.php' => 'Je Domäne zwei Tabellen (Organisationswerte, Plattform-Defaults); die Reihenfolge der Organisationswerte wird über alle Zeilen einer Domäne gespeichert.',
        'resources/views/admin/components/index.blade.php' => 'Systemübersicht; die Tabelle zeigt die offenen Update-Hinweise der Update-Prüfung — je Komponente höchstens einen.',
        'resources/views/admin/cti/index.blade.php' => 'Telefonie-Anbindungen der Organisation (je angeschlossener Telefonanlage ein Webhook) — eine Handvoll; darunter je Anbindung die Wähleinstellungen.',
        'resources/views/admin/custom-fields/index.blade.php' => 'Eine Zeile je Träger eigener Felder (Kunde, Auftrag, Asset, Artikel, Projekt) — durch CustomFieldService::SUBJECTS festgelegt.',
        'resources/views/admin/data-ownership/index.blade.php' => 'Eine Zeile je Datenbereich (Enum DataDomain) mit Auswahl des führenden Systems — durch den Code festgelegt.',
        'resources/views/admin/document-design/index.blade.php' => 'Belegprofile und Briefpapier-Vorlagen der Organisation — Vorlagen, eine Handvoll; die Checkliste darüber prüft gegen die ganze Menge.',
        'resources/views/admin/license/index.blade.php' => 'Lizenzübersicht; die Tabelle listet die Feature-Flags der installierten Lizenz — aus der Lizenzdatei, keine Datensätze.',
        'resources/views/admin/mail/index.blade.php' => 'Postfächer der Organisation für den Maileingang — eine Handvoll.',
        'resources/views/admin/metrics/index.blade.php' => 'Betriebskennzahlen: Aggregat-Tabellen (Nutzung je Funktion, Erklärung der Telemetrie-Zähler) — keine Datensatzliste.',
        'resources/views/admin/notification-rules/index.blade.php' => 'Eine Zeile je Benachrichtigungsereignis (Enum NotificationEvent) — durch den Code festgelegt, nicht durch Daten.',
        'resources/views/admin/number-formats/index.blade.php' => 'Eine Zeile je Nummernkreis (Enum NumberScope) mit Formular in der Zeile — durch den Code festgelegt.',
        'resources/views/admin/plugins/index.blade.php' => 'Eine Zeile je installiertem Plugin aus dem PluginManager, in der View gefiltert und clientseitig sortiert — keine Datenbankliste.',
        'resources/views/admin/privacy/index.blade.php' => 'Datenschutz-Übersicht: Datenkategorien aus der Konfiguration und Auszüge (Sitzungen, Tokens, Integrationen, letzte Exporte und Supportzugriffe) — die vollständige Verwaltung liegt unter Sitzungen.',
        'resources/views/admin/scheduler/index.blade.php' => 'Eine Zeile je geplantem Job aus der Job-Registry im Code; Filter und Sortierung laufen in PHP über den berechneten Laufzustand.',
        'resources/views/admin/security/index.blade.php' => 'Sicherheitsübersicht: je Karte ein Auszug der jüngsten Einträge sowie die offenen Advisories, nach Schweregrad in PHP sortiert.',
        'resources/views/admin/shift-rotations/index.blade.php' => 'Karten je Dienstrhythmus mit Wochenraster; die Tabelle ist die Zuweisungsliste des jeweiligen Rhythmus.',
        'resources/views/admin/shipments/index.blade.php' => 'Versanddienstleister-Anbindungen der Organisation — je Dienstleister eine Zeile.',
        'resources/views/admin/sso/index.blade.php' => 'Einrichtungsseite: SCIM-Tokens und die vom Identitätsanbieter zugewiesenen Gruppen, je Zeile mit Team-Zuordnung — beides eine Handvoll.',
        'resources/views/admin/time-accounts/index.blade.php' => 'Karten je Zusatzkonto; die Tabelle ist die Regelliste des jeweiligen Kontos.',
        'resources/views/asset-compliance/index.blade.php' => 'Übersicht: Kennzahlen (bald fällig, überfällig) und die Asset-Auswahl der Sperre rechnen über alle aktiven Prüfpflichten; daneben die Tabelle der Sperren.',
        'resources/views/assets/components/index.blade.php' => 'Stückliste eines einzelnen Geräts, clientseitig sortierbar — auch nach dem aus Einbaudatum und Intervall berechneten Wechseltermin; der Hinweis auf fällige Verschleißteile rechnet über alle verbauten Teile.',
        'resources/views/club/fees/collections/index.blade.php' => 'Einzugsvorschlag: alle offenen Forderungen sind vorausgewählt und gehen in einem Formular in den Sammellauf.',
        'resources/views/club/fees/tariffs/index.blade.php' => 'Beitragstarife je Verein — eine Handvoll Zeilen, jede mit ihrer Satzhistorie.',
        'resources/views/club/my/index.blade.php' => 'Persönliche Übersicht: Termine der nächsten 90 Tage und Spieltage des Mitglieds, je Zeile mit berechnetem Anmeldestand.',
        'resources/views/club/resources/index.blade.php' => 'Baum aus Hallen, Teilflächen und Plätzen — die Einrückung braucht alle Knoten.',
        'resources/views/collections/index.blade.php' => 'Inhalte der gewählten Sammlung: gemischte Inhaltsarten in gespeicherter Reihenfolge, je Eintrag in PHP auf Sichtbarkeit geprüft — Zähler und Seiteneinteilung stünden erst nach dieser Prüfung fest.',
        'resources/views/domain/reports/index.blade.php' => 'Bericht: die Tabelle ist die Verlängerungsprognose je Monat (Aggregat), daneben Kennzahlen und Auszüge — keine Datensatzliste.',
        'resources/views/driver-license-checks/index.blade.php' => 'Fälligkeitsübersicht mit einer Zeile je Mitarbeiter, überfällige zuerst — Rang und Fälligkeit stammen aus der jeweils letzten Kontrolle je Person und werden in PHP sortiert.',
        'resources/views/finance/dunning/index.blade.php' => 'Mahnlauf: die Mahnstufe wird je Rechnung berechnet (fällig, Karenz, gesperrt), die Sammelmahnung wählt über alle Zeilen aus.',
        'resources/views/flex/index.blade.php' => 'Monatsblatt des Arbeitszeitkontos: eine Zeile je Kalendertag des gewählten Monats (28 bis 31), aus dem Zeitkonto berechnet — keine Datensätze.',
        'resources/views/helpdesk/catalog/index.blade.php' => 'Servicekatalog als Baum Fachdienst → Angebot → Katalogeintrag, je Angebot eine eigene Tabelle.',
        'resources/views/helpdesk/reports/index.blade.php' => 'Bericht: Kennzahlkarten, Diagramme und Aggregat-Tabellen je Queue, Status und Ausgang — keine Datensatzliste.',
        'resources/views/helpdesk/routing/index.blade.php' => 'Regelwerk: die erste zutreffende Regel gewinnt — die Reihenfolge muss auf einer Seite stehen.',
        'resources/views/holidays/index.blade.php' => 'Jahreskalender: gesetzliche Feiertage aus dem Feiertagsdienst, zusammengeführt mit den eigenen des Mandanten — rund ein Dutzend Zeilen je Jahr; die Zuordnung zum Jahr braucht alle eigenen Feiertage.',
        'resources/views/isms/conformity/index.blade.php' => 'Eine Zeile je Norm und Ausgabe des Geltungsbereichs (einstellig); die ganze Menge wird gegen die Normen der Anforderungen abgeglichen.',
        'resources/views/isms/requirements/index.blade.php' => 'Natürliche Ref-Sortierung (A.5.2 vor A.5.10) und SoA-Filter laufen in PHP über den ganzen Katalog; der Abgleich fehlender Aussagen braucht alle Zeilen.',
        'resources/views/learning/dossier/index.blade.php' => 'Namentliche Nachweismappe: jede Anzeige wird als eine Offenlegung mit Personenzahl protokolliert — Blättern schriebe je Seite einen Eintrag.',
        'resources/views/learning/my/index.blade.php' => 'Eigene Kursteilnahmen einer Person, clientseitig sortierbar — auch nach dem berechneten Fortschritt.',
        'resources/views/passenger/masterdata/index.blade.php' => 'Stammdatenseite mit drei Fachtabellen (versionierte Tarife, Konzessionen je Betriebsart, ein Profil je Fahrzeug) — jede kurz, und eine stehende Paginierung könnte nur eine bedienen.',
        'resources/views/payroll/index.blade.php' => 'Einstellungsseite: Mindestlohn-Sätze (ein Satz je Stichtag) und die Prüfliste der Mitarbeiter unter Mindestlohn, die „Alle anheben“ als Ganzes bedient.',
        'resources/views/suppliers/credentials/index.blade.php' => 'Ampel je Lieferant: der Status wird aus den Nachweisen berechnet und sortiert die Liste (gesperrt zuerst) — in SQL nicht abbildbar.',
        'resources/views/sustainability/index.blade.php' => 'Dashboard: die Tabellen sind das Aggregat je Aktivitätsart, die Ziele mit ihrem Zielpfad und die VSME-Referenzmatrix (Katalog) — keine wachsende Datensatzliste.',
        'resources/views/vacation-entitlements/index.blade.php' => 'Jahresübersicht mit einer Zeile je Mitarbeiter, clientseitig sortierbar — auch nach den berechneten Salden (genommen, beantragt, Rest) aus dem Urlaubskonto-Dienst; der Hinweis auf fehlende Ansprüche gleicht gegen alle Zeilen ab.',
    ];

    /** @var array<string, string> L2: Listen, die bewusst mit der Seite fließen — Pfad => Grund */
    private const PAGE_FLOW_ALLOW = [
        'app/Plugins/DatevOnline/Resources/views/admin/index.blade.php' => 'Verbindungsseite aus vier Karten: Mandantenwahl, Belegbilder, Buchungsstapel (geblättert) und zuletzt übertragene Belege.',
        'app/Plugins/Etsy/Resources/views/admin/index.blade.php' => 'Verbindungskarte über dem Bestellspiegel, darunter die Ledger-Summen je Buchungsart — zwei Tabellen.',
        'app/Plugins/JtlWawi/Resources/views/admin/index.blade.php' => 'Einrichtungsseite aus Verbindungsstatus, Registrierung, Verbindungsformular, Lagerzuordnung und Bestandsführung.',
        'app/Plugins/Lexoffice/Resources/views/handover/index.blade.php' => 'Auswahlformular: die Tabelle steht in der Karte mit Zähler und Paket-Knopf, darunter der Hinweis zur Übergabe.',
        'app/Plugins/Lexoffice/Resources/views/plan/index.blade.php' => 'Einstellungsseite: Tarifprofil-Formular neben der Funktionsmatrix, darunter die Wechselvorschau.',
        'app/Plugins/Msgraph/Resources/views/admin/index.blade.php' => 'Verbindungsseite aus sechs Abschnitten (Kalender, Mail, Kontakte, Aufgaben, OneNote, Entra) mit je eigenem Verbindungsformular.',
        'app/Plugins/OrgaMax/Resources/views/admin/index.blade.php' => 'Verbindungsseite aus fünf Abschnitten: Verbindung, Datenführerschaft, übergebene Aufträge (in der Karte geblättert), Rechnungs-Projektion (stehend geblättert) und Abgleichsprotokoll.',
        'app/Plugins/RemoteSupport/Resources/views/pending/index.blade.php' => 'Inbox aus Karten je Verbindungs-ID mit Zuweisungsformularen (zwei Reiter); die Tabelle ist die Sitzungsliste je Mehrkundengerät.',
        'app/Plugins/Todoist/Resources/views/admin/index.blade.php' => 'Verbindungskarte über den Projektzuordnungen; das Formular für neue Zuordnungen steht unter der Tabelle in derselben Karte.',
        'resources/views/admin/access/roles/index.blade.php' => 'Zwei Tabellen untereinander: Rollen der Organisation und Rollen der Plattform.',
        'resources/views/admin/accounting-migration/index.blade.php' => 'Ablaufseite des Buchhaltungswechsels: Status mit Aktionen, Blocker, Zähler je Bereich, Positionen (geblättert) und Verlauf.',
        'resources/views/admin/ai/index.blade.php' => 'Übersicht aus Verbrauchskacheln, Verbindungen und KI-Funktionen — zwei Tabellen.',
        'resources/views/admin/b2b-catalog/index.blade.php' => 'Vier Karten: Punchout-Adresse, neuer Zugang, Zugänge und Bestellungen (geblättert) mit Upload.',
        'resources/views/admin/backup-targets/index.blade.php' => 'Schlüsselwarnungen und Karten je Sicherungsziel über der Karte der Generationen (geblättert).',
        'resources/views/admin/branch-profiles/index.blade.php' => 'Profilkarten, darunter Varianten-Tabelle und Importformular.',
        'resources/views/admin/chat/index.blade.php' => 'Kanaltabelle über dem Formular zum Verbinden eines Kanals.',
        'resources/views/admin/classifications/index.blade.php' => 'Je Domäne ein Abschnitt mit zwei Tabellen (Organisationswerte, Plattform-Defaults).',
        'resources/views/admin/cloud-intake/index.blade.php' => 'Karten je Cloud-Verbindung mit Ordnerwahl und Routentabelle über dem Eingangsprotokoll (geblättert).',
        'resources/views/admin/components/index.blade.php' => 'Systemübersicht aus sieben Karten (Zustand, Version, Laufzeit, Module, Plugins, SBOM, Manifest) über den Update-Hinweisen.',
        'resources/views/admin/cti/index.blade.php' => 'Formular für neue Anbindungen über der Anbindungstabelle, darunter die Wähleinstellungen je Anbindung.',
        'resources/views/admin/document-design/index.blade.php' => 'Einrichtungs-Checkliste über den Karten für Belegprofile und Briefpapier — zwei Tabellen.',
        'resources/views/admin/license/index.blade.php' => 'Lizenzübersicht aus sechs Karten (Lizenz, Mandantenstatus, Org-Lizenz, Limits, Feature-Flags, Module).',
        'resources/views/admin/mail/index.blade.php' => 'Abrufstatus und Postfachtabelle über dem Formular zum Verbinden eines Postfachs.',
        'resources/views/admin/metrics/index.blade.php' => 'Kennzahlen-Dashboard aus acht Karten mit zwei Aggregat-Tabellen.',
        'resources/views/admin/privacy/index.blade.php' => 'Datenschutz-Übersicht aus acht Karten mit sechs Tabellen.',
        'resources/views/admin/security/index.blade.php' => 'Sicherheitsübersicht aus neun Karten mit sechs Tabellen.',
        'resources/views/admin/shift-rotations/index.blade.php' => 'Karten je Dienstrhythmus mit Wochenraster, Zuweisungstabelle und Formularen.',
        'resources/views/admin/shipments/index.blade.php' => 'Formular für eine neue Anbindung über der Anbindungstabelle.',
        'resources/views/admin/sso/index.blade.php' => 'Einrichtungsseite aus Karten für Anbieter, Domains, Notfallkonten, SCIM-Tokens und Gruppen.',
        'resources/views/admin/terminals/index.blade.php' => 'Vier Karten mit je einer Tabelle: Terminals, Ausweise (geblättert), PINs und Kontrollpunkte.',
        'resources/views/admin/time-accounts/index.blade.php' => 'Karten je Zusatzkonto mit Regeltabelle und Buchungsformularen.',
        'resources/views/asset-compliance/index.blade.php' => 'Übersicht aus Kennzahlen, Prüfpflichten und Sperren mit Sperrformular.',
        'resources/views/availability/index.blade.php' => 'Selbstbedienung in zwei Abschnitten (Verfügbarkeiten, Wunschdienste), je mit Schnellerfassung über der Tabelle; geblättert werden die Wunschdienste.',
        'resources/views/bill-of-quantities/call-offs/index.blade.php' => 'Reiter des Rahmen-LV aus drei Karten: Rahmenschalter, Abrufe (geblättert) und darunter die Restmengen je Position.',
        'resources/views/club/fees/collections/index.blade.php' => 'Einzugsvorschlag mit Laufformular neben der Karte der Sammelläufe.',
        'resources/views/club/fees/donations/index.blade.php' => 'Spenden des Jahres neben Sammelbestätigungen und ausgestellten Bestätigungen — drei Karten.',
        'resources/views/club/fees/tariffs/index.blade.php' => 'Tarife neben den Abteilungszuschlägen — zwei Karten.',
        'resources/views/club/my/index.blade.php' => 'Persönliche Übersicht aus Terminen, Spieltagen, Anwesenheit, Graduierung und Änderungen.',
        'resources/views/collections/index.blade.php' => 'Zweispaltig: Sammlungsbaum neben der Karte der gewählten Sammlung mit Beschreibung und Inhalten.',
        'resources/views/customer/agreements/index.blade.php' => self::PORTAL_LAYOUT,
        'resources/views/customer/appointments/index.blade.php' => self::PORTAL_LAYOUT,
        'resources/views/customer/assets/index.blade.php' => self::PORTAL_LAYOUT,
        'resources/views/customer/billing/index.blade.php' => self::PORTAL_LAYOUT,
        'resources/views/customer/catalog/index.blade.php' => self::PORTAL_LAYOUT,
        'resources/views/customer/claims/index.blade.php' => self::PORTAL_LAYOUT,
        'resources/views/customer/diary/index.blade.php' => self::PORTAL_LAYOUT,
        'resources/views/customer/documents/index.blade.php' => self::PORTAL_LAYOUT,
        'resources/views/customer/intakes/index.blade.php' => self::PORTAL_LAYOUT,
        'resources/views/customer/invoices/index.blade.php' => self::PORTAL_LAYOUT,
        'resources/views/customer/rental/index.blade.php' => self::PORTAL_LAYOUT,
        'resources/views/customer/subscriptions/index.blade.php' => self::PORTAL_LAYOUT,
        'resources/views/customer/tickets/index.blade.php' => self::PORTAL_LAYOUT,
        'resources/views/customer/time-entries/index.blade.php' => self::PORTAL_LAYOUT,
        'resources/views/domain/reports/index.blade.php' => 'Bericht aus Kennzahlen und vier Karten (Prognose, Abdeckung, ohne Zuordnung, riskante Verlängerung).',
        'resources/views/helpdesk/catalog/index.blade.php' => 'Eine Karte je Fachdienst mit den Tabellen seiner Angebote.',
        'resources/views/helpdesk/reports/index.blade.php' => 'Bericht aus Kennzahlkarten, Diagrammen und sechs Tabellen.',
        'resources/views/helpdesk/routing/index.blade.php' => 'Werkzeugseite: Dry-Run und Regelanlage als Formularkarten über der Regeltabelle.',
        'resources/views/holidays/index.blade.php' => 'Zwei per Skript umgeschaltete Reiter (Jahresübersicht, eigene Feiertage) mit je einer Tabelle auf derselben Seite.',
        'resources/views/inventory/index.blade.php' => 'Unter dem Bestand folgen Reservierungen, Beschaffungsbedarf und Bestandsgrenzen.',
        'resources/views/knowledge-hub/index.blade.php' => 'Zweispaltig: Sammlungsbaum neben den Inhalten, die wahlweise als Liste oder als Kacheln erscheinen, mit Auswahlleiste darüber.',
        'resources/views/passenger/masterdata/index.blade.php' => 'Stammdatenseite aus vier Karten: Tarife, Zuschlagsregeln des gewählten Tarifs, Konzessionen und Fahrzeugprofile.',
        'resources/views/payroll/index.blade.php' => 'Einstellungsseite aus vier Karten: Betrieb & Finanzamt, Mindestlohn, Eurostat-Referenz und Mitarbeiter unter Mindestlohn.',
        'resources/views/privacy/compliance/index.blade.php' => 'Unter den Befunden (geblättert) folgt der konfigurierbare Anforderungskatalog mit einem Formular je Anforderung.',
        'resources/views/privacy/retention/index.blade.php' => 'Zwei Karten: Fristen je Bereich über den Lösch-Vorschlägen (geblättert) mit den Löschläufen je freigegebenem Bereich.',
        'resources/views/rental/rates/index.blade.php' => 'Formular für eine neue Version über den Karten je Preislistenversion (geblättert) mit Konditionstabelle, Formular und Regeln.',
        'resources/views/shift-exchanges/index.blade.php' => 'Zwei Abschnitte: Freigabeliste der Leitung über den eigenen Anträgen (geblättert).',
        'resources/views/suppliers/questionnaires/index.blade.php' => 'Fragebögen über den versandten Anfragen — zwei Tabellen; geblättert wird die Anfragenliste.',
        'resources/views/sustainability/index.blade.php' => 'Dashboard aus Kennzahlen und sieben Karten (Emissionen, Ziele, Bewertungen, Kriterien, Maßnahmen, Faktoren, VSME-Matrix).',
        'resources/views/time-accounts/index.blade.php' => 'Kacheln je Zeitkonto über dem Journal des gewählten Kontos (geblättert).',
    ];

    public function test_index_lists_paginate_standing(): void {
        $found = $this->indexListsWithout('<x-pagination', self::UNPAGINATED_ALLOW);

        $this->assertSame([], $found, "L1 Indexliste ohne <x-pagination standing> — Controller paginieren lassen oder mit Grund in UNPAGINATED_ALLOW:\n" . implode("\n", $found));
    }

    public function test_index_lists_fill_the_page(): void {
        $found = $this->indexListsWithout('scroll="flex"', self::PAGE_FLOW_ALLOW);

        $this->assertSame([], $found, "L2 Indexliste ohne scroll=\"flex\" — <x-table scroll=\"flex\"> mit @include('partials.page-fill') oder mit Grund in PAGE_FLOW_ALLOW:\n" . implode("\n", $found));
    }

    /**
     * @param  array<string, string>  $allowList
     * @return list<string>
     */
    private function indexListsWithout(string $needle, array $allowList): array {
        $found = [];
        foreach ($this->bladeFiles() as $file) {
            $relative = $this->relativePath($file);
            if (basename($relative) !== 'index.blade.php' || $this->isAllowListed($relative, self::SKIP_PREFIXES) || $this->isAllowListed($relative, $allowList)) {
                continue;
            }
            $source = $this->stripBladeComments((string) file_get_contents($file));
            if (str_contains($source, '<x-table') && ! str_contains($source, $needle)) {
                $found[] = $relative;
            }
        }
        sort($found);

        return $found;
    }
}
