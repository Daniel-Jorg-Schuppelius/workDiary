<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : takeoff.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Aufmaßblatt (MVP-1058).
return [
    'title' => 'Aufmaß',
    'back' => 'Zurück',
    'default_title' => 'Aufmaß :carrier',
    'lines' => 'Aufmaßzeilen',
    'totals' => 'Mengen je Position',
    'photos' => 'Fotos und Skizzen',
    'values' => 'Werte',
    'empty' => 'Noch keine Zeilen — fügen Sie über „Zeile hinzufügen“ eine Formel hinzu.',
    'no_target' => '— ohne Zuordnung —',
    'pdf_note' => 'Gerechnet nach den Formeln der REB-VB 23.003. Werte in Metern, Winkel in Gon (Vollkreis = 400).',
    'formula' => [
        'Sum' => 'Stück / Summe',
        'Triangle' => 'Dreieck',
        'Rectangle' => 'Rechteck / Quader',
        'Trapezoid' => 'Trapez',
        'Circle' => 'Kreis / Kreissektor',
        'Mean' => 'Mittelwert',
        'Free' => 'Freie Formel',
    ],
    'value' => [
        'amount' => 'Wert',
        'base' => 'Grundseite',
        'height' => 'Höhe',
        'depth' => 'Tiefe / Höhe (Körper, optional)',
        'length' => 'Länge',
        'width' => 'Breite',
        'side_a' => 'Seite a',
        'side_c' => 'Seite c',
        'radius' => 'Radius',
        'angle' => 'Winkel in Gon (400 = Vollkreis)',
        'expression' => 'Ausdruck',
    ],
    'hint' => [
        'Sum' => 'Werte werden addiert; ein negativer Wert zieht ab.',
        'Triangle' => 'Grundseite × Höhe ÷ 2; mit Tiefe als Körper.',
        'Rectangle' => 'Länge × Breite; mit Tiefe/Höhe als Körper.',
        'Trapezoid' => '(a + c) ÷ 2 × Höhe; mit Tiefe als Körper.',
        'Circle' => 'Radius² × π × Winkel ÷ 400; 400 Gon ist der Vollkreis.',
        'Mean' => 'Arithmetisches Mittel der Werte.',
        'Free' => 'Rechenausdruck mit + − × ÷ und Klammern, Dezimalkomma oder -punkt.',
        'factor' => 'Anzahl gleicher Teile; negativ zieht ab (z. B. −1 für eine Tür).',
        'label' => 'Raum, Bauteil oder Achse.',
        'unit' => 'Leer = Einheit der LV-Position bzw. des Artikels.',
    ],
    'col' => [
        'label' => 'Raum / Bauteil',
        'formula' => 'Formel',
        'values' => 'Werte',
        'factor' => 'Faktor',
        'quantity' => 'Menge',
        'target' => 'Position',
    ],
    'field' => [
        'title' => 'Bezeichnung',
        'measured_on' => 'Aufgemessen am',
        'note' => 'Bemerkung',
        'boq_item' => 'LV-Position',
        'article' => 'Artikel / Leistung',
        'description' => 'Beschreibung (ohne Artikel)',
        'unit' => 'Einheit',
    ],
    'action' => [
        'create' => 'Neues Aufmaß',
        'edit' => 'Bearbeiten',
        'pdf' => 'PDF',
        'delete' => 'Löschen',
        'add_line' => 'Zeile hinzufügen',
    ],
    'transition' => [
        'completed' => 'Abschließen',
        'draft' => 'Wieder öffnen',
    ],
    'confirm' => [
        'completed' => 'Aufmaß abschließen? Danach sind die Zeilen gesperrt und die Mengen übernehmbar.',
        'draft' => 'Aufmaß wieder öffnen? Bereits übernommene Mengen ändern sich dadurch nicht.',
        'delete' => 'Aufmaß mit allen Zeilen löschen?',
        'delete_line' => 'Zeile wirklich entfernen?',
    ],
    'flash' => [
        'created' => 'Aufmaß angelegt.',
        'saved' => 'Aufmaß gespeichert.',
        'deleted' => 'Aufmaß gelöscht.',
        'status' => 'Status geändert.',
        'line_saved' => 'Zeile gespeichert.',
        'line_deleted' => 'Zeile entfernt.',
    ],
    'error' => [
        'locked' => 'Das Aufmaß ist abgeschlossen und kann nicht mehr geändert werden.',
        'not_computable' => 'Mit diesen Werten lässt sich die Formel nicht rechnen — prüfen Sie die Pflichtwerte bzw. den Ausdruck.',
        'not_found' => 'Aufmaß nicht gefunden.',
    ],
    'carrier' => [
        'section' => 'Aufmaße',
        'lines' => ':count Zeile|:count Zeilen',
        'none' => 'Noch kein Aufmaß.',
    ],
    'transfer' => [
        'title' => 'Mengen übernehmen',
        'action' => 'Übernehmen',
        'confirm' => [
            'quote' => 'Mengen als neues Angebot übernehmen?',
            'invoice' => 'Mengen als Rechnungsentwurf übernehmen? Das Aufmaß-PDF wird als Dokument angehängt.',
            'progress' => 'Mengen als Leistungsstand der LV-Positionen melden?',
        ],
        'targets' => 'Übernommen in',
        'kind' => [
            'quote' => 'Als Angebot',
            'invoice' => 'Als Rechnungsentwurf',
            'progress' => 'Als LV-Leistungsstand',
        ],
        'hint' => 'Jede Art einmal je Aufmaß; Positionen ohne Preis bekommen 0 € und werden im Beleg nachgetragen.',
        'done' => 'Übernommen',
        'based_on' => 'Mengen nach Aufmaß „:title“.',
        'document_title' => 'Aufmaß :title',
        'progress_note' => 'Aus Aufmaß „:title“',
        'flash' => [
            'quote' => 'Angebot aus dem Aufmaß angelegt.',
            'invoice' => 'Rechnungsentwurf aus dem Aufmaß angelegt; das Aufmaß-PDF liegt als Dokument an.',
            'progress' => ':count LV-Positionen gemeldet.',
        ],
        'error' => [
            'not_completed' => 'Erst das Aufmaß abschließen.',
            'already' => 'Bereits übernommen: :kind.',
            'no_customer' => 'Am Auftrag bzw. Projekt hängt kein Kunde.',
            'empty' => 'Das Aufmaß hat keine Mengen.',
            'no_boq' => 'Keine Zeile ist einer LV-Position zugeordnet.',
        ],
    ],
    'chain' => [
        'label' => 'Aufmaße ohne Beleg',
        'measured_on' => 'Aufgemessen am :date',
    ],
    'presets' => [
        'label' => 'Vorlagen',
    ],
    'quick' => [
        'title' => 'Schnellerfassung',
        'hint' => 'Funktioniert auch ohne Netz — die Zeile wird beim nächsten Verbindungsaufbau übertragen.',
        'photo' => 'Foto',
        'add' => 'Zeile erfassen',
    ],
];
