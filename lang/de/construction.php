<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : construction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'VOB/B-Schreiben',
    'subtitle' => 'Behinderungsanzeigen und Bedenkenanmeldungen mit Zugangsnachweis.',
    'empty' => 'Keine Schreiben erfasst.',
    'dialog_hint' => 'Der Sachverhalt ist der Kern des Schreibens: knapp, prüfbar und mit Datum. Die Rechtsverweise sind Text — WorkDiary leistet keine Rechtsberatung.',
    'disclaimer' => 'Rechtsverweise sind Textbausteine und keine Rechtsberatung. Ob eine Frist läuft oder sich die Bauzeit verlängert, entscheiden die Vertragsparteien.',

    'kind' => [
        'obstruction' => 'Behinderungsanzeige',
        'concern' => 'Bedenkenanmeldung',
    ],

    'legal' => [
        'obstruction' => '§ 6 Abs. 1 VOB/B',
        'concern' => '§ 4 Abs. 3 VOB/B',
    ],

    'status' => [
        'draft' => 'Entwurf',
        'sent' => 'Versendet',
        'acknowledged' => 'Zugang bestätigt',
    ],

    'column' => [
        'number' => 'Nummer',
        'kind' => 'Art',
        'subject' => 'Betreff',
        'project' => 'Bauvorhaben',
        'occurred_on' => 'Datum',
        'status' => 'Status',
    ],

    'filter' => [
        'kind' => 'Art',
        'status' => 'Status',
    ],

    'field' => [
        'site' => 'Einsatzort',
        'customer' => 'Auftraggeber',
        'diary_entry' => 'Anlass (Tagebucheintrag)',
        'recipient_name' => 'Empfänger',
        'recipient_email' => 'Empfänger-E-Mail',
        'facts' => 'Sachverhalt',
        'facts_hint' => 'Was genau behindert oder begründet die Bedenken? Ursache, betroffene Leistung, Zeitpunkt.',
        'impact_schedule' => 'Auswirkung auf die Bauzeit',
        'impact_cost' => 'Auswirkung auf die Kosten',
        'claims_time_extension' => 'Bauzeitverlängerung beantragt',
        'claims_time_extension_hint' => 'Reiner Vermerk am Schreiben — WorkDiary verschiebt daraufhin keine Frist.',
        'legal_reference' => 'Rechtsverweis',
        'legal_reference_hint' => 'Erscheint als Text im Schreiben.',
        'acknowledged_note' => 'Vermerk zum Zugang',
    ],

    'section' => [
        'context' => 'Zuordnung',
        'weather' => 'Wetterlage am Anlasstag',
        'delivery' => 'Zugangsnachweis',
        'acknowledge' => 'Eingangsbestätigung',
    ],

    'action' => [
        'edit' => 'Bearbeiten',
        'pdf' => 'PDF',
        'send' => 'Versenden',
        'acknowledge' => 'Zugang bestätigen',
    ],

    'badge' => [
        'time_extension' => 'Bauzeitverlängerung beantragt',
    ],

    'note' => [
        'time_extension' => 'Vermerk: Es wurde eine Bauzeitverlängerung beantragt. Die Fristen in WorkDiary bleiben unverändert — eine Verlängerung wirkt erst, wenn sie zwischen den Vertragsparteien vereinbart und hier gepflegt ist.',
        'time_extension_short' => 'Eine beantragte Bauzeitverlängerung ist ein Vermerk; Fristen verschiebt WorkDiary nicht automatisch.',
    ],

    'delivery' => [
        'none' => 'Noch kein Zugangsnachweis erfasst.',
        'method' => 'Zustellweg',
        'method_registered_mail' => 'Einschreiben',
        'method_courier' => 'Bote',
        'method_handover' => 'Persönliche Übergabe',
        'method_fax' => 'Telefax',
        'method_portal' => 'Vergabe-/Bauportal',
        'delivered_at' => 'Zugestellt am',
        'recipient' => 'Empfänger',
        'reference' => 'Beleg-/Sendungsnummer',
        'record' => 'Zugang erfassen',
    ],

    'mail' => [
        'title' => ':label :nr per E-Mail senden',
    ],

    'pdf' => [
        'number' => 'Nummer',
        'subject' => 'Betreff',
        'occurred_on' => 'Datum',
        'project' => 'Bauvorhaben',
        'site' => 'Einsatzort',
        'legal_reference' => 'Rechtsverweis',
        'facts' => 'Sachverhalt',
        'impact_schedule' => 'Auswirkung auf die Bauzeit',
        'impact_cost' => 'Auswirkung auf die Kosten',
        'weather' => 'Wetterlage am Anlasstag',
        'weather_values' => 'Messwerte',
        'weather_source' => 'Quelle',
        'time_extension' => 'Bauzeitverlängerung beantragt',
        'time_extension_text' => 'Wir beantragen eine Verlängerung der Ausführungsfrist entsprechend der Dauer der Behinderung.',
        'disclaimer' => 'Dieses Schreiben nennt die einschlägigen Vorschriften als Textbaustein. Es ersetzt keine rechtliche Prüfung.',
    ],

    'error' => [
        'frozen' => 'Ein versendetes Schreiben ist festgeschrieben und kann nicht mehr geändert werden.',
    ],

    'created' => 'Schreiben angelegt.',
    'updated' => 'Schreiben gespeichert.',
    'deleted' => 'Entwurf gelöscht.',
    'delivery_recorded' => 'Zugangsnachweis erfasst.',
    'acknowledged' => 'Zugang bestätigt.',
];
