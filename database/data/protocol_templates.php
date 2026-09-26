<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : protocol_templates.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Katalog der Protokollvorlagen für die Branchenprofile (MVP-902). Profile
// nennen im Abschnitt `protocol_templates` nur den Code; der
// ProtocolTemplateInstallStep holt Name, Art und Punkte von hier. Punkte im
// Format von ProtocolTemplateService::snapshot(). Die Punkte sind
// Arbeitshilfen, keine rechtliche oder fachliche Vorgabe — Prüfumfang und
// Grenzwerte legt der Betrieb fest.

$text = static fn (string $label, bool $required = false, ?string $description = null): array => array_filter(['label' => $label, 'item_type' => 'text', 'required' => $required ?: null, 'description' => $description], static fn ($v) => $v !== null);
$check = static fn (string $label, bool $required = false): array => array_filter(['label' => $label, 'item_type' => 'boolean', 'required' => $required ?: null], static fn ($v) => $v !== null);
$choice = static function (string $label, array $options, bool $required = false): array {
    $opts = [];
    foreach ($options as $key => $option) {
        $opts[] = ['key' => is_string($key) ? $key : 'o' . ($key + 1), 'label' => $option];
    }

    return array_filter(['label' => $label, 'item_type' => 'choice', 'required' => $required ?: null, 'config' => ['options' => $opts]], static fn ($v) => $v !== null);
};
$number = static fn (string $label, string $unit, bool $required = false): array => array_filter(['label' => $label, 'item_type' => 'number', 'required' => $required ?: null, 'config' => ['unit' => $unit]], static fn ($v) => $v !== null);
$date = static fn (string $label, bool $required = false): array => array_filter(['label' => $label, 'item_type' => 'date', 'required' => $required ?: null], static fn ($v) => $v !== null);
$time = static fn (string $label, bool $required = false): array => array_filter(['label' => $label, 'item_type' => 'datetime', 'required' => $required ?: null], static fn ($v) => $v !== null);
$photo = static fn (string $label, bool $required = false): array => array_filter(['label' => $label, 'item_type' => 'photo', 'required' => $required ?: null], static fn ($v) => $v !== null);
$defect = static fn (string $label = 'Festgestellte Mängel'): array => ['label' => $label, 'item_type' => 'defect'];
$sign = static fn (string $label = 'Unterschrift Kunde'): array => ['label' => $label, 'item_type' => 'signature'];
$group = static fn (string $label, array $children): array => ['label' => $label, 'item_type' => 'group', 'children' => $children];

$ok = ['ok' => 'in Ordnung', 'nok' => 'nicht in Ordnung', 'na' => 'nicht zutreffend'];
$result = ['passed' => 'bestanden', 'restricted' => 'mit Einschränkungen', 'failed' => 'nicht bestanden'];
$acceptance = ['accepted' => 'abgenommen', 'reserved' => 'abgenommen mit Vorbehalt', 'refused' => 'nicht abgenommen'];

// Wiederkehrende Abnahme: Leistungsumfang, Mängel, Ergebnis, Unterschriften.
$acceptanceItems = static fn (string $scope) => [
    $text($scope, true),
    $defect(),
    $choice('Ergebnis der Abnahme', $acceptance, true),
    $date('Frist zur Mängelbeseitigung'),
    $sign('Unterschrift Auftraggeber'),
    ['label' => 'Unterschrift Auftragnehmer', 'item_type' => 'signoff_internal'],
];

return [
    // ── Anlagenwartung ───────────────────────────────────────────────
    'AW_WARTUNGSPROTOKOLL' => ['name' => 'Wartungsprotokoll Anlage', 'kind' => 'maintenance', 'items' => [
        $text('Anlage / Standort', true),
        $group('Sichtprüfung', [$choice('Allgemeinzustand', $ok, true), $choice('Befestigung und Verkleidung', $ok), $choice('Leckagen', $ok)]),
        $group('Funktionsprüfung', [$choice('Steuerung und Sicherheitseinrichtungen', $ok, true), $choice('Betriebsgeräusche', $ok)]),
        $text('Durchgeführte Arbeiten', true),
        $text('Getauschte Teile'),
        $defect(),
        $date('Nächste Wartung'),
        $sign(),
    ]],
    'AW_STOERBERICHT' => ['name' => 'Störungsbericht', 'kind' => 'service', 'items' => [
        $time('Störung gemeldet am', true),
        $text('Fehlerbild laut Meldung', true),
        $text('Festgestellte Ursache'),
        $text('Maßnahmen', true),
        $choice('Anlage nach Einsatz', ['running' => 'in Betrieb', 'limited' => 'eingeschränkt in Betrieb', 'down' => 'außer Betrieb'], true),
        $photo('Fotos'),
        $sign(),
    ]],
    'AW_MESSPROTOKOLL' => ['name' => 'Messprotokoll', 'kind' => 'inspection', 'items' => [
        $text('Messobjekt', true),
        $text('Messmittel (Inventar-Nr.)', true),
        ['label' => 'Messreihe', 'item_type' => 'measurement.timestamped'],
        $choice('Bewertung', $result, true),
        $text('Bemerkungen'),
        ['label' => 'Freigabe Prüfer', 'item_type' => 'signoff_internal'],
    ]],
    'AW_KALIBRIERNACHWEIS' => ['name' => 'Kalibriernachweis', 'kind' => 'inspection', 'items' => [
        $text('Messmittel (Inventar-Nr.)', true),
        $text('Referenznormal / Zertifikat', true),
        $number('Abweichung vor Justage', '%'),
        $number('Abweichung nach Justage', '%'),
        $choice('Ergebnis', $result, true),
        $date('Nächste Kalibrierung', true),
        ['label' => 'Freigabe Prüfer', 'item_type' => 'signoff_internal'],
    ]],
    'AW_ERSATZTEILNACHWEIS' => ['name' => 'Ersatzteilnachweis', 'kind' => 'service', 'items' => [
        $text('Anlage', true),
        $text('Ausgebaute Teile (Bezeichnung, Seriennummer)', true),
        $text('Eingebaute Teile (Bezeichnung, Seriennummer)', true),
        $choice('Verbleib Altteile', ['customer' => 'beim Kunden', 'disposal' => 'entsorgt', 'return' => 'Rücksendung Hersteller']),
        $photo('Fotos Typenschilder'),
        $sign(),
    ]],
    'AW_ABNAHME' => ['name' => 'Abnahmeprotokoll Anlage', 'kind' => 'acceptance', 'items' => $acceptanceItems('Abgenommene Anlage / Leistung')],

    // ── Bau und Ausbau ───────────────────────────────────────────────
    'BAU_TAGESBERICHT' => ['name' => 'Bautagesbericht', 'kind' => 'siteVisit', 'items' => [
        $choice('Wetter', ['dry' => 'trocken', 'rain' => 'Regen', 'frost' => 'Frost', 'wind' => 'Sturm']),
        $number('Temperatur', '°C'),
        $number('Personal vor Ort', 'Personen', true),
        $text('Ausgeführte Arbeiten', true),
        $text('Behinderungen und Besonderheiten'),
        $text('Anordnungen des Auftraggebers'),
        $photo('Baustellenfotos'),
        ['label' => 'Bauleitung', 'item_type' => 'signoff_internal'],
    ]],
    'BAU_AUFMASSPROTOKOLL' => ['name' => 'Aufmaßprotokoll', 'kind' => 'siteVisit', 'items' => [
        $text('Bauteil / Position', true),
        $text('Aufmaß (Maße und Mengen)', true),
        $photo('Skizze oder Foto'),
        $check('Gemeinsames Aufmaß mit Auftraggeber'),
        $sign('Unterschrift Auftraggeber'),
    ]],
    'BAU_MAENGELLISTE' => ['name' => 'Mängelliste', 'kind' => 'defect', 'items' => [
        $defect('Mängel'),
        $date('Frist zur Beseitigung', true),
        $text('Verantwortliches Gewerk'),
        $sign('Kenntnisnahme Auftragnehmer'),
    ]],
    'BAU_NACHTRAGSPROTOKOLL' => ['name' => 'Nachtragsprotokoll', 'kind' => 'other', 'items' => [
        $text('Anlass des Nachtrags', true),
        $text('Geänderte oder zusätzliche Leistung', true),
        $check('Anordnung durch Auftraggeber liegt vor', true),
        $text('Auswirkung auf Termine'),
        $photo('Nachweise'),
        $sign('Unterschrift Auftraggeber'),
    ]],
    'BAU_ABNAHME' => ['name' => 'Abnahmeprotokoll Bauleistung', 'kind' => 'acceptance', 'items' => $acceptanceItems('Abgenommene Bauleistung')],
    'BAU_MATERIALVERBRAUCH' => ['name' => 'Materialverbrauch Baustelle', 'kind' => 'service', 'items' => [
        $text('Verbrauchtes Material (Bezeichnung, Menge)', true),
        $text('Rücklieferung / Restmengen'),
        $photo('Lieferscheine'),
        ['label' => 'Bestätigung', 'item_type' => 'signoff_internal'],
    ]],

    // ── Elektro ──────────────────────────────────────────────────────
    'EL_PRUEFPROTOKOLL' => ['name' => 'Prüfprotokoll elektrische Anlage', 'kind' => 'inspection', 'items' => [
        $text('Anlage / Stromkreise', true),
        $choice('Prüfanlass', ['new' => 'Errichtung', 'change' => 'Änderung', 'repeat' => 'Wiederholungsprüfung'], true),
        $group('Besichtigung', [$choice('Schutz gegen direktes Berühren', $ok, true), $choice('Auswahl und Kennzeichnung der Betriebsmittel', $ok)]),
        $group('Messungen', [$number('Isolationswiderstand', 'MΩ'), $number('Schleifenimpedanz', 'Ω'), $number('Auslösestrom RCD', 'mA'), $number('Auslösezeit RCD', 'ms')]),
        $choice('Ergebnis', $result, true),
        $text('Messgerät (Inventar-Nr.)', true),
        ['label' => 'Prüfer', 'item_type' => 'signoff_internal'],
    ]],
    'EL_MESSPROTOKOLL' => ['name' => 'Messprotokoll Elektro', 'kind' => 'inspection', 'items' => [
        $text('Messobjekt', true),
        ['label' => 'Messwerte', 'item_type' => 'measurement.timestamped'],
        $text('Messgerät (Inventar-Nr.)', true),
        $choice('Bewertung', $result, true),
        ['label' => 'Prüfer', 'item_type' => 'signoff_internal'],
    ]],
    'EL_SERVICEBERICHT' => ['name' => 'Servicebericht Elektro', 'kind' => 'service', 'items' => [
        $text('Auftrag / Fehlerbild', true),
        $text('Durchgeführte Arbeiten', true),
        $text('Verbautes Material'),
        $check('Anlage nach Abschluss geprüft und in Betrieb', true),
        $photo('Fotos'),
        $sign(),
    ]],
    'EL_ABNAHME' => ['name' => 'Abnahmeprotokoll Elektroinstallation', 'kind' => 'acceptance', 'items' => $acceptanceItems('Abgenommene Installation')],
    'EL_VERTEILER_DOKU' => ['name' => 'Dokumentation Verteiler', 'kind' => 'service', 'items' => [
        $text('Verteiler / Standort', true),
        $check('Stromkreise beschriftet', true),
        $check('Stromlaufplan im Verteiler', true),
        $photo('Foto Verteiler geöffnet', true),
        $photo('Foto Beschriftung'),
    ]],
    'EL_WALLBOX_INBETRIEBNAHME' => ['name' => 'Inbetriebnahme Ladeeinrichtung', 'kind' => 'handover', 'items' => [
        $text('Hersteller, Typ, Seriennummer', true),
        $number('Ladeleistung', 'kW', true),
        $check('Anmeldung beim Netzbetreiber erfolgt', true),
        $group('Prüfung', [$choice('Schutzeinrichtungen', $ok, true), $choice('Probeladung', $ok, true)]),
        $check('Kunde eingewiesen', true),
        $photo('Fotos Installation'),
        $sign(),
    ]],

    // ── Facility-Management ──────────────────────────────────────────
    'FM_OBJEKTBERICHT' => ['name' => 'Objektbegehung', 'kind' => 'inspection', 'items' => [
        $text('Objekt / Bereich', true),
        $group('Zustand', [$choice('Außenanlagen', $ok), $choice('Treppenhaus und Flure', $ok), $choice('Technikräume', $ok), $choice('Beleuchtung', $ok)]),
        $defect(),
        $photo('Fotos'),
        ['label' => 'Objektbetreuung', 'item_type' => 'signoff_internal'],
    ]],
    'FM_MAENGELPROTOKOLL' => ['name' => 'Mängelprotokoll Objekt', 'kind' => 'defect', 'items' => [
        $defect('Mängel'),
        $choice('Zuständigkeit', ['own' => 'eigene Leistung', 'owner' => 'Eigentümer', 'third' => 'Fremdfirma']),
        $date('Beseitigung bis'),
        $sign('Kenntnisnahme Auftraggeber'),
    ]],
    'FM_SCHLUESSELNACHWEIS' => ['name' => 'Schlüsselnachweis', 'kind' => 'handover', 'items' => [
        $text('Schlüssel / Transponder (Nummern)', true),
        $choice('Vorgang', ['out' => 'Ausgabe', 'in' => 'Rücknahme'], true),
        $text('Empfänger', true),
        $time('Zeitpunkt', true),
        $sign('Unterschrift Empfänger'),
    ]],
    'FM_WINTERDIENSTNACHWEIS' => ['name' => 'Winterdienstnachweis', 'kind' => 'service', 'items' => [
        $time('Einsatzbeginn', true),
        $time('Einsatzende', true),
        $choice('Wetterlage', ['snow' => 'Schneefall', 'ice' => 'Glätte', 'freezing_rain' => 'Eisregen'], true),
        $choice('Leistung', ['clear' => 'Räumen', 'grit' => 'Streuen', 'both' => 'Räumen und Streuen'], true),
        $text('Streumittel und Menge'),
        $photo('Fotos'),
    ]],
    'FM_ZAEHLERABLESUNG' => ['name' => 'Zählerablesung', 'kind' => 'inspection', 'items' => [
        $text('Zähler (Nummer, Art)', true),
        $number('Zählerstand', 'Einheit laut Zähler', true),
        $time('Ablesezeitpunkt', true),
        $photo('Foto Zählerstand', true),
    ]],
    'FM_NOTFALLBERICHT' => ['name' => 'Notfallbericht Objekt', 'kind' => 'service', 'items' => [
        $time('Alarmierung', true),
        $time('Eintreffen vor Ort', true),
        $text('Lage bei Eintreffen', true),
        $text('Sofortmaßnahmen', true),
        $text('Informierte Stellen'),
        $photo('Fotos'),
        ['label' => 'Einsatzkraft', 'item_type' => 'signoff_internal'],
    ]],

    // ── Garten- und Landschaftsbau ───────────────────────────────────
    'GL_PFLEGENACHWEIS' => ['name' => 'Pflegenachweis', 'kind' => 'service', 'items' => [
        $text('Fläche / Objekt', true),
        $group('Leistungen', [$check('Rasen gemäht'), $check('Beete gepflegt'), $check('Gehölze geschnitten'), $check('Laub entfernt'), $check('Bewässerung')]),
        $text('Grünschnitt (Menge, Verbleib)'),
        $photo('Fotos'),
    ]],
    'GL_PFLANZPROTOKOLL' => ['name' => 'Pflanzprotokoll', 'kind' => 'service', 'items' => [
        $text('Gepflanzte Arten und Stückzahl', true),
        $choice('Bodenzustand', ['good' => 'gut', 'improved' => 'verbessert', 'poor' => 'ungünstig']),
        $check('Angegossen', true),
        $text('Pflegehinweise an den Kunden'),
        $photo('Fotos'),
        $sign(),
    ]],
    'GL_BAUTAGESBERICHT' => ['name' => 'Bautagesbericht GaLaBau', 'kind' => 'siteVisit', 'items' => [
        $choice('Wetter', ['dry' => 'trocken', 'rain' => 'Regen', 'frost' => 'Frost']),
        $number('Personal vor Ort', 'Personen', true),
        $text('Ausgeführte Arbeiten', true),
        $text('Eingesetzte Maschinen'),
        $text('Besonderheiten'),
        $photo('Fotos'),
    ]],
    'GL_WINTERDIENSTNACHWEIS' => ['name' => 'Winterdienstnachweis GaLaBau', 'kind' => 'service', 'items' => [
        $time('Einsatzbeginn', true),
        $time('Einsatzende', true),
        $choice('Leistung', ['clear' => 'Räumen', 'grit' => 'Streuen', 'both' => 'Räumen und Streuen'], true),
        $text('Streumittel und Menge'),
        $photo('Fotos'),
    ]],
    'GL_MAENGEL' => ['name' => 'Mängelprotokoll GaLaBau', 'kind' => 'defect', 'items' => [
        $defect('Mängel'),
        $date('Beseitigung bis'),
        $sign('Kenntnisnahme Auftraggeber'),
    ]],
    'GL_ABNAHME' => ['name' => 'Abnahmeprotokoll GaLaBau', 'kind' => 'acceptance', 'items' => $acceptanceItems('Abgenommene Leistung')],

    // ── Gebäudereinigung ─────────────────────────────────────────────
    'GR_REINIGUNGSNACHWEIS' => ['name' => 'Reinigungsnachweis', 'kind' => 'service', 'items' => [
        $text('Objekt / Bereich', true),
        $time('Beginn', true),
        $time('Ende', true),
        $group('Leistungen', [$check('Böden'), $check('Sanitärbereiche'), $check('Oberflächen'), $check('Abfall entsorgt')]),
        $text('Besonderheiten'),
    ]],
    'GR_QS_PROTOKOLL' => ['name' => 'Qualitätskontrolle Reinigung', 'kind' => 'inspection', 'items' => [
        $text('Objekt / Bereich', true),
        $group('Bewertung', [$choice('Böden', $ok, true), $choice('Sanitär', $ok, true), $choice('Oberflächen', $ok), $choice('Glas', $ok)]),
        $defect(),
        $photo('Fotos'),
        ['label' => 'Objektleitung', 'item_type' => 'signoff_internal'],
    ]],
    'GR_REKLAMATIONSBERICHT' => ['name' => 'Reklamationsbericht Reinigung', 'kind' => 'defect', 'items' => [
        $text('Reklamation laut Kunde', true),
        $text('Feststellung vor Ort', true),
        $text('Maßnahme'),
        $photo('Fotos'),
        $sign('Kenntnisnahme Kunde'),
    ]],
    'GR_MATERIALVERBRAUCH' => ['name' => 'Materialverbrauch Reinigung', 'kind' => 'service', 'items' => [
        $text('Verbrauchte Reinigungsmittel (Bezeichnung, Menge)', true),
        $text('Verbrauchsmaterial (Papier, Seife, Beutel)'),
        $check('Nachbestellung nötig'),
    ]],
    'GR_ABNAHME' => ['name' => 'Abnahmeprotokoll Reinigung', 'kind' => 'acceptance', 'items' => $acceptanceItems('Abgenommene Reinigungsleistung')],

    // ── Handwerk ─────────────────────────────────────────────────────
    'HW_SERVICEBERICHT' => ['name' => 'Servicebericht', 'kind' => 'service', 'items' => [
        $text('Auftrag / Anliegen', true),
        $text('Durchgeführte Arbeiten', true),
        $text('Verbautes Material'),
        $check('Arbeitsbereich gereinigt'),
        $photo('Fotos'),
        $sign(),
    ]],
    'HW_WARTUNGSPROTOKOLL' => ['name' => 'Wartungsprotokoll', 'kind' => 'maintenance', 'items' => [
        $text('Gerät / Anlage', true),
        $group('Prüfpunkte', [$choice('Sichtprüfung', $ok, true), $choice('Funktion', $ok, true), $choice('Verschleißteile', $ok)]),
        $text('Durchgeführte Arbeiten', true),
        $defect(),
        $date('Nächste Wartung'),
        $sign(),
    ]],
    'HW_ABNAHMEPROTOKOLL' => ['name' => 'Abnahmeprotokoll Handwerk', 'kind' => 'acceptance', 'items' => $acceptanceItems('Abgenommene Leistung')],
    'HW_INSPEKTION' => ['name' => 'Inspektion', 'kind' => 'inspection', 'items' => [
        $text('Objekt / Bauteil', true),
        $choice('Zustand', $ok, true),
        $defect(),
        $text('Empfehlung'),
        $photo('Fotos'),
    ]],
    'HW_AUFMASS' => ['name' => 'Aufmaß', 'kind' => 'siteVisit', 'items' => [
        $text('Raum / Bauteil', true),
        $text('Maße und Mengen', true),
        $photo('Skizze oder Foto'),
        $sign('Bestätigung Kunde'),
    ]],
    'HW_GERAETEUEBERGABE' => ['name' => 'Geräteübergabe', 'kind' => 'handover', 'items' => [
        $text('Gerät (Hersteller, Typ, Seriennummer)', true),
        $check('Bedienungsanleitung übergeben', true),
        $check('Kunde eingewiesen', true),
        $text('Hinweise zu Wartung und Garantie'),
        $sign(),
    ]],

    // ── IT ───────────────────────────────────────────────────────────
    'IT_CHANGE_PROTOCOL' => ['name' => 'Change-Protokoll', 'kind' => 'service', 'items' => [
        $text('Änderung / Ziel', true),
        $check('Sicherung vor der Änderung erstellt', true),
        $time('Wartungsfenster Beginn', true),
        $time('Wartungsfenster Ende', true),
        $text('Durchgeführte Schritte', true),
        $choice('Ergebnis', ['success' => 'erfolgreich', 'partial' => 'teilweise', 'rollback' => 'zurückgerollt'], true),
        $text('Rückfallplan / Hinweise'),
        ['label' => 'Freigabe', 'item_type' => 'signoff_internal'],
    ]],
    'IT_INCIDENT_REPORT' => ['name' => 'Störungsbericht IT', 'kind' => 'service', 'items' => [
        $time('Beginn der Störung', true),
        $time('Ende der Störung'),
        $text('Betroffene Systeme / Nutzer', true),
        $text('Ursache'),
        $text('Maßnahmen', true),
        $text('Vorbeugende Maßnahmen'),
    ]],
    'IT_HANDOVER_DEVICE' => ['name' => 'Geräteübergabe IT', 'kind' => 'handover', 'items' => [
        $text('Gerät (Typ, Seriennummer, Inventar-Nr.)', true),
        $text('Zubehör'),
        $check('Zugangsdaten übergeben / eingerichtet', true),
        $check('Nutzungsrichtlinie ausgehändigt'),
        $sign('Unterschrift Empfänger'),
    ]],
    'IT_MAINTENANCE_LOG' => ['name' => 'Wartungsprotokoll IT', 'kind' => 'maintenance', 'items' => [
        $text('System / Gerät', true),
        $group('Prüfpunkte', [$check('Updates eingespielt'), $check('Sicherung geprüft'), $check('Speicherplatz geprüft'), $check('Protokolle gesichtet')]),
        $text('Auffälligkeiten'),
        $date('Nächste Wartung'),
    ]],
    'IT_SECURITY_INCIDENT' => ['name' => 'Sicherheitsvorfall', 'kind' => 'other', 'items' => [
        $time('Entdeckt am', true),
        $text('Art des Vorfalls', true),
        $text('Betroffene Systeme und Daten', true),
        $check('Personenbezogene Daten betroffen'),
        $text('Sofortmaßnahmen', true),
        $text('Informierte Stellen'),
        ['label' => 'Verantwortlich', 'item_type' => 'signoff_internal'],
    ]],

    // ── Kfz und Fuhrparkservice ──────────────────────────────────────
    'KFZ_ANNAHMEPROTOKOLL' => ['name' => 'Fahrzeugannahme', 'kind' => 'handover', 'items' => [
        $text('Kennzeichen / Fahrzeug', true),
        $number('Kilometerstand', 'km', true),
        $choice('Tankfüllung', ['e' => 'Reserve', 'q' => '1/4', 'h' => '1/2', 't' => '3/4', 'f' => 'voll']),
        $defect('Vorschäden'),
        $photo('Fotos Fahrzeug', true),
        $text('Auftrag laut Kunde', true),
        $sign(),
    ]],
    'KFZ_SERVICEBERICHT' => ['name' => 'Servicebericht Kfz', 'kind' => 'service', 'items' => [
        $text('Durchgeführte Arbeiten', true),
        $text('Verbaute Teile'),
        $number('Kilometerstand bei Abgabe', 'km'),
        $text('Empfehlungen'),
        $sign(),
    ]],
    'KFZ_DIAGNOSEBERICHT' => ['name' => 'Diagnosebericht', 'kind' => 'inspection', 'items' => [
        $text('Fehlerbild laut Kunde', true),
        $text('Ausgelesene Fehlercodes'),
        $text('Befund', true),
        $text('Empfohlene Reparatur'),
        $photo('Fotos'),
    ]],
    'KFZ_SCHADENPROTOKOLL' => ['name' => 'Schadenprotokoll Kfz', 'kind' => 'defect', 'items' => [
        $text('Kennzeichen / Fahrzeug', true),
        $time('Schadenzeitpunkt'),
        $defect('Schäden'),
        $photo('Fotos', true),
        $sign(),
    ]],
    'KFZ_REIFENEINLAGERUNG' => ['name' => 'Reifeneinlagerung', 'kind' => 'handover', 'items' => [
        $text('Reifen (Größe, Hersteller, DOT)', true),
        $group('Profiltiefe', [$number('vorne links', 'mm'), $number('vorne rechts', 'mm'), $number('hinten links', 'mm'), $number('hinten rechts', 'mm')]),
        $check('Mit Felgen'),
        $text('Lagerplatz', true),
        $sign(),
    ]],
    'KFZ_UEBERGABE' => ['name' => 'Fahrzeugübergabe', 'kind' => 'handover', 'items' => [
        $number('Kilometerstand', 'km', true),
        $check('Arbeiten erläutert', true),
        $check('Altteile angeboten'),
        $text('Hinweise'),
        $sign(),
    ]],

    // ── Partyservice ─────────────────────────────────────────────────
    'PS_HACCP_PROTOKOLL' => ['name' => 'Temperatur- und Hygieneprotokoll', 'kind' => 'inspection', 'items' => [
        ['label' => 'Temperaturen (Kühlung, Ausgabe)', 'item_type' => 'measurement.timestamped'],
        $check('Hygieneregeln eingehalten', true),
        $check('Warenkontrolle bei Anlieferung erfolgt', true),
        $text('Abweichungen und Maßnahmen'),
        ['label' => 'Verantwortlich', 'item_type' => 'signoff_internal'],
    ]],
    'PS_ABNAHME' => ['name' => 'Abnahme Veranstaltung Catering', 'kind' => 'acceptance', 'items' => $acceptanceItems('Erbrachte Leistung (Personen, Menü, Service)')],
    'PS_REKLAMATIONSBERICHT' => ['name' => 'Reklamationsbericht Catering', 'kind' => 'defect', 'items' => [
        $text('Reklamation laut Kunde', true),
        $text('Stellungnahme'),
        $text('Vereinbarte Lösung'),
        $sign('Kenntnisnahme Kunde'),
    ]],

    // ── Pflege ───────────────────────────────────────────────────────
    'PF_PFLEGEBERICHT' => ['name' => 'Pflegebericht', 'kind' => 'service', 'items' => [
        $time('Einsatz', true),
        $text('Durchgeführte Leistungen', true),
        $text('Beobachtungen'),
        $check('Rücksprache mit Angehörigen oder Ärztin/Arzt nötig'),
        ['label' => 'Pflegekraft', 'item_type' => 'signoff_internal'],
    ]],
    'PF_STURZBERICHT' => ['name' => 'Sturzereignis', 'kind' => 'other', 'items' => [
        $time('Zeitpunkt', true),
        $text('Ort und Hergang', true),
        $text('Folgen / Verletzungen'),
        $text('Sofortmaßnahmen', true),
        $text('Informierte Personen'),
        ['label' => 'Pflegekraft', 'item_type' => 'signoff_internal'],
    ]],
    'PF_WUNDDOKUMENTATION' => ['name' => 'Wunddokumentation', 'kind' => 'service', 'items' => [
        $text('Lokalisation', true),
        $number('Länge', 'cm'),
        $number('Breite', 'cm'),
        $text('Wundzustand'),
        $text('Versorgung'),
        $photo('Fotos'),
        ['label' => 'Pflegekraft', 'item_type' => 'signoff_internal'],
    ]],
    'PF_BERATUNGSNACHWEIS' => ['name' => 'Beratungsnachweis', 'kind' => 'service', 'items' => [
        $time('Beratung am', true),
        $text('Beratene Person(en)', true),
        $text('Themen', true),
        $text('Vereinbarungen'),
        $sign('Unterschrift beratene Person'),
    ]],
    'PF_PFLEGEANAMNESE' => ['name' => 'Aufnahmegespräch', 'kind' => 'siteVisit', 'items' => [
        $text('Wohnsituation'),
        $text('Unterstützungsbedarf', true),
        $text('Wünsche und Gewohnheiten'),
        $text('Ansprechpartner'),
        $sign('Unterschrift Klient/in'),
    ]],

    // ── Sanitär, Heizung, Klima ──────────────────────────────────────
    'SHK_WARTUNGSPROTOKOLL' => ['name' => 'Wartungsprotokoll Heizung', 'kind' => 'maintenance', 'items' => [
        $text('Wärmeerzeuger (Hersteller, Typ)', true),
        $group('Prüfpunkte', [$choice('Brenner / Wärmetauscher gereinigt', $ok, true), $choice('Sicherheitseinrichtungen', $ok, true), $choice('Anlagendruck', $ok), $choice('Abgasweg', $ok)]),
        $number('Anlagendruck', 'bar'),
        $text('Getauschte Teile'),
        $defect(),
        $sign(),
    ]],
    'SHK_DRUCKPROTOKOLL' => ['name' => 'Druckprüfung', 'kind' => 'inspection', 'items' => [
        $text('Leitungsabschnitt', true),
        $choice('Prüfmedium', ['water' => 'Wasser', 'air' => 'Luft / Inertgas'], true),
        $number('Prüfdruck', 'bar', true),
        $number('Prüfdauer', 'min', true),
        $number('Druckabfall', 'bar'),
        $choice('Ergebnis', $result, true),
        ['label' => 'Prüfer', 'item_type' => 'signoff_internal'],
    ]],
    'SHK_DICHTHEITSPROTOKOLL' => ['name' => 'Dichtheitsprüfung', 'kind' => 'inspection', 'items' => [
        $text('Anlage / Leitung', true),
        $number('Prüfdruck', 'mbar', true),
        $number('Prüfdauer', 'min', true),
        $choice('Ergebnis', $result, true),
        $text('Messgerät (Inventar-Nr.)'),
        ['label' => 'Prüfer', 'item_type' => 'signoff_internal'],
    ]],
    'SHK_ABNAHME' => ['name' => 'Abnahmeprotokoll SHK', 'kind' => 'acceptance', 'items' => $acceptanceItems('Abgenommene Anlage / Leistung')],
    'SHK_SERVICEBERICHT' => ['name' => 'Servicebericht SHK', 'kind' => 'service', 'items' => [
        $text('Anliegen', true),
        $text('Durchgeführte Arbeiten', true),
        $text('Verbautes Material'),
        $check('Anlage in Betrieb übergeben', true),
        $photo('Fotos'),
        $sign(),
    ]],

    // ── Sicherheitsdienst ────────────────────────────────────────────
    'SD_WACHBUCH_EINTRAG' => ['name' => 'Wachbucheintrag', 'kind' => 'other', 'items' => [
        $time('Zeitpunkt', true),
        $text('Ereignis', true),
        $text('Veranlasst'),
        ['label' => 'Sicherheitskraft', 'item_type' => 'signoff_internal'],
    ]],
    'SD_REVIERBERICHT' => ['name' => 'Revierbericht', 'kind' => 'inspection', 'items' => [
        $text('Objekt / Route', true),
        $time('Kontrolle Beginn', true),
        $time('Kontrolle Ende', true),
        $group('Feststellungen', [$choice('Türen und Tore verschlossen', $ok, true), $choice('Fenster verschlossen', $ok), $choice('Beleuchtung', $ok)]),
        $defect('Auffälligkeiten'),
        $photo('Fotos'),
    ]],
    'SD_ALARMBERICHT' => ['name' => 'Alarmverfolgung', 'kind' => 'other', 'items' => [
        $time('Alarmeingang', true),
        $time('Eintreffen vor Ort', true),
        $choice('Alarmursache', ['false' => 'Fehlalarm', 'technical' => 'technischer Defekt', 'intrusion' => 'Einbruch / Versuch', 'unknown' => 'ungeklärt'], true),
        $text('Maßnahmen', true),
        $text('Informierte Stellen'),
        $photo('Fotos'),
    ]],
    'SD_VORFALLMELDUNG' => ['name' => 'Vorfallmeldung', 'kind' => 'other', 'items' => [
        $time('Zeitpunkt', true),
        $text('Ort', true),
        $text('Sachverhalt', true),
        $text('Beteiligte / Zeugen'),
        $text('Maßnahmen'),
        ['label' => 'Sicherheitskraft', 'item_type' => 'signoff_internal'],
    ]],
    'SD_SCHLUESSELNACHWEIS' => ['name' => 'Schlüsselnachweis Sicherheitsdienst', 'kind' => 'handover', 'items' => [
        $text('Schlüssel / Transponder', true),
        $choice('Vorgang', ['out' => 'Entnahme', 'in' => 'Rückgabe'], true),
        $time('Zeitpunkt', true),
        $sign('Unterschrift'),
    ]],
    'SD_UEBERGABE' => ['name' => 'Schichtübergabe', 'kind' => 'handover', 'items' => [
        $time('Übergabe', true),
        $text('Besondere Vorkommnisse'),
        $text('Offene Aufgaben'),
        $check('Schlüssel und Ausrüstung vollständig', true),
        ['label' => 'Übergebende Kraft', 'item_type' => 'signoff_internal'],
    ]],

    // ── Spedition ────────────────────────────────────────────────────
    'SP_ABHOLBELEG' => ['name' => 'Abholbeleg', 'kind' => 'handover', 'items' => [
        $time('Abholung', true),
        $number('Packstücke', 'Stück', true),
        $number('Gewicht', 'kg'),
        $defect('Äußerlich erkennbare Schäden'),
        $sign('Unterschrift Absender'),
    ]],
    'SP_LADUNGSSICHERUNG' => ['name' => 'Ladungssicherung', 'kind' => 'inspection', 'items' => [
        $text('Fahrzeug / Kennzeichen', true),
        $choice('Sicherungsart', ['form' => 'formschlüssig', 'force' => 'kraftschlüssig', 'combined' => 'kombiniert'], true),
        $check('Hilfsmittel in ausreichender Zahl und intakt', true),
        $photo('Fotos Ladung', true),
        ['label' => 'Fahrer', 'item_type' => 'signoff_internal'],
    ]],
    'SP_ZUSTELLNACHWEIS' => ['name' => 'Zustellnachweis', 'kind' => 'handover', 'items' => [
        $time('Zustellung', true),
        $text('Empfänger (Name)', true),
        $number('Packstücke', 'Stück', true),
        $defect('Vorbehalte / Schäden'),
        $photo('Foto Ablage'),
        $sign('Unterschrift Empfänger'),
    ]],
    'SP_PALETTENSCHEIN' => ['name' => 'Palettenschein', 'kind' => 'handover', 'items' => [
        $number('Paletten übergeben', 'Stück', true),
        $number('Paletten zurückgenommen', 'Stück', true),
        $choice('Palettenart', ['epal' => 'Europalette', 'gitter' => 'Gitterbox', 'other' => 'sonstige']),
        $sign('Unterschrift'),
    ]],
    'SP_SCHADENSPROTOKOLL' => ['name' => 'Schadensprotokoll Transport', 'kind' => 'defect', 'items' => [
        $text('Sendung / Packstück', true),
        $defect('Schäden'),
        $photo('Fotos', true),
        $text('Stellungnahme Fahrer'),
        $sign('Unterschrift Empfänger'),
    ]],
    'SP_WARTEZEITNACHWEIS' => ['name' => 'Wartezeitnachweis', 'kind' => 'other', 'items' => [
        $time('Ankunft', true),
        $time('Beginn Be-/Entladung', true),
        $time('Ende Be-/Entladung', true),
        $text('Grund der Wartezeit'),
        $sign('Bestätigung Verlader / Empfänger'),
    ]],
    'SP_TOURBERICHT' => ['name' => 'Tourbericht', 'kind' => 'other', 'items' => [
        $number('Kilometerstand Beginn', 'km', true),
        $number('Kilometerstand Ende', 'km', true),
        $text('Besonderheiten'),
        $check('Fahrzeug ohne Mängel abgestellt', true),
        ['label' => 'Fahrer', 'item_type' => 'signoff_internal'],
    ]],

    // ── Steuerberatung ───────────────────────────────────────────────
    'STB_BERATUNGSPROTOKOLL' => ['name' => 'Beratungsprotokoll', 'kind' => 'other', 'items' => [
        $time('Gespräch am', true),
        $text('Teilnehmende', true),
        $text('Themen und Ergebnisse', true),
        $text('Vereinbarte Aufgaben und Fristen'),
        $sign('Unterschrift Mandant'),
    ]],
    'STB_TELEFONNOTIZ' => ['name' => 'Telefonnotiz', 'kind' => 'other', 'items' => [
        $time('Anruf', true),
        $text('Gesprächspartner', true),
        $text('Inhalt', true),
        $text('Wiedervorlage / Aufgabe'),
    ]],
    'STB_FRISTENVERLAENGERUNG' => ['name' => 'Fristverlängerung', 'kind' => 'other', 'items' => [
        $text('Steuerart / Zeitraum', true),
        $date('Ursprüngliche Frist', true),
        $date('Beantragte Frist', true),
        $text('Begründung'),
        $choice('Bescheid', ['open' => 'offen', 'granted' => 'gewährt', 'refused' => 'abgelehnt']),
    ]],
    'STB_FREIGABE_MANDANT' => ['name' => 'Freigabe durch Mandant', 'kind' => 'acceptance', 'items' => [
        $text('Freigegebenes Dokument / Erklärung', true),
        $check('Inhalt erläutert', true),
        $check('Vollständigkeit der Angaben bestätigt', true),
        $sign('Unterschrift Mandant'),
    ]],
    'STB_PRUEFUNGSVERMERK' => ['name' => 'Prüfungsvermerk', 'kind' => 'inspection', 'items' => [
        $text('Geprüfter Vorgang', true),
        $text('Feststellungen', true),
        $choice('Ergebnis', ['ok' => 'ohne Beanstandung', 'note' => 'mit Hinweis', 'rework' => 'Nacharbeit nötig'], true),
        ['label' => 'Prüfende Person', 'item_type' => 'signoff_internal'],
    ]],
    'STB_GELDWAESCHE_VERMERK' => ['name' => 'Identifizierung und Risikovermerk', 'kind' => 'other', 'items' => [
        $text('Identifizierte Person / Gesellschaft', true),
        $text('Ausweis- bzw. Registerdokument', true),
        $check('Wirtschaftlich Berechtigte festgestellt'),
        $choice('Risikoeinschätzung', ['low' => 'gering', 'normal' => 'normal', 'high' => 'erhöht'], true),
        $text('Begründung'),
        ['label' => 'Verantwortlich', 'item_type' => 'signoff_internal'],
    ]],

    // ── Veranstalter ─────────────────────────────────────────────────
    'VA_GENEHMIGUNGSLISTE' => ['name' => 'Genehmigungen und Anzeigen', 'kind' => 'other', 'items' => [
        $group('Vorliegend', [$check('Genehmigung Veranstaltungsort'), $check('Sondernutzung / Straße'), $check('Ausschank'), $check('Anmeldung Musiknutzung')]),
        $text('Offene Punkte'),
        ['label' => 'Verantwortlich', 'item_type' => 'signoff_internal'],
    ]],
    'VA_SICHERHEITSKONZEPT' => ['name' => 'Sicherheitsbegehung Veranstaltung', 'kind' => 'inspection', 'items' => [
        $group('Prüfpunkte', [$choice('Flucht- und Rettungswege frei', $ok, true), $choice('Notbeleuchtung', $ok), $choice('Feuerlöscher', $ok), $choice('Erste Hilfe', $ok, true)]),
        $defect(),
        $check('Veranstaltung freigegeben', true),
        ['label' => 'Veranstaltungsleitung', 'item_type' => 'signoff_internal'],
    ]],
    'VA_ABLAUFPLAN' => ['name' => 'Ablaufkontrolle', 'kind' => 'other', 'items' => [
        $time('Einlass', true),
        $time('Beginn Programm'),
        $time('Ende'),
        $text('Abweichungen vom Ablauf'),
    ]],
    'VA_ABNAHME' => ['name' => 'Abnahme Veranstaltung', 'kind' => 'acceptance', 'items' => $acceptanceItems('Erbrachte Leistung')],
    'VA_ZWISCHENFALLBERICHT' => ['name' => 'Zwischenfallbericht', 'kind' => 'other', 'items' => [
        $time('Zeitpunkt', true),
        $text('Ort', true),
        $text('Sachverhalt', true),
        $text('Maßnahmen'),
        $text('Informierte Stellen'),
        ['label' => 'Verantwortlich', 'item_type' => 'signoff_internal'],
    ]],

    // ── Veranstaltungstechnik ────────────────────────────────────────
    'VT_EVENTBRIEFING' => ['name' => 'Briefing Veranstaltung', 'kind' => 'other', 'items' => [
        $text('Ansprechpartner vor Ort', true),
        $time('Aufbaubeginn', true),
        $time('Showbeginn'),
        $text('Besondere Anforderungen'),
        $check('Stromversorgung geklärt', true),
    ]],
    'VT_EQUIPMENTLISTE' => ['name' => 'Equipment-Ausgabe', 'kind' => 'handover', 'items' => [
        $text('Ausgegebenes Equipment (Bezeichnung, Anzahl)', true),
        $check('Funktionsprüfung vor Ausgabe', true),
        $sign('Unterschrift Empfänger'),
    ]],
    'VT_SAFETY_CHECK' => ['name' => 'Sicherheitscheck Technik', 'kind' => 'inspection', 'items' => [
        $group('Prüfpunkte', [$choice('Rigging / Lasten gesichert', $ok, true), $choice('Kabelwege gesichert', $ok, true), $choice('Elektrische Verteilung', $ok, true), $choice('Pyro / Effekte', $ok)]),
        $defect(),
        $check('Freigabe erteilt', true),
        ['label' => 'Verantwortliche Fachkraft', 'item_type' => 'signoff_internal'],
    ]],
    'VT_SOUNDCHECK' => ['name' => 'Soundcheck', 'kind' => 'inspection', 'items' => [
        $time('Soundcheck', true),
        $number('Pegel am Messpunkt', 'dB(A)'),
        $text('Anmerkungen'),
    ]],
    'VT_ABNAHME' => ['name' => 'Abnahme Veranstaltungstechnik', 'kind' => 'acceptance', 'items' => $acceptanceItems('Abgenommener Aufbau')],
    'VT_RUECKNAHME' => ['name' => 'Equipment-Rücknahme', 'kind' => 'handover', 'items' => [
        $text('Zurückgenommenes Equipment', true),
        $check('Vollständig', true),
        $defect('Schäden'),
        $sign('Unterschrift Rückgebender'),
    ]],
    'VT_SCHADEN' => ['name' => 'Schadensmeldung Equipment', 'kind' => 'defect', 'items' => [
        $text('Equipment (Bezeichnung, Inventar-Nr.)', true),
        $defect('Schaden'),
        $text('Hergang'),
        $photo('Fotos', true),
    ]],
];
