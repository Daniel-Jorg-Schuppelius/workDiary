<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : recipes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Rezepturen (MVP-455): branchenneutrale Rezeptpflege + Partyservice-Aufsatz.
return [
    'title' => [
        'materials' => 'Rezeptur / Materialbedarf',
        'party' => 'Partyservice: Grundausbeute & Portionen',
        'allergen_overrides' => 'Allergen-Abweichungen (mit Begründung)',
        'allergens' => 'Allergene',
        'plan' => 'Skalierung & Plankosten',
    ],
    'hint' => [
        'version' => 'Version :version',
        'readonly' => 'veröffentlicht — unveränderlicher Stand',
        'materials' => 'Feste Mengen, Mengen je Einheit/Portion oder Mischungsverhältnisse; Werkzeuge bleiben vom Verbrauch getrennt. Positionen sind nur in Entwurfsversionen änderbar.',
        'ratio_input' => 'Bei „Verhältnisanteil" gibt der Wert den Anteil an der Zielmenge an (Summe aller Anteile = Gesamtmenge).',
        'party' => 'Grundausbeute dokumentiert, wie viele Portionen das Rezept im Standardansatz liefert; Mengen je Einheit werden je Portion erfasst.',
    ],
    'empty' => [
        'no_version' => 'Noch keine Version vorhanden — zuerst eine Entwurfsversion anlegen.',
        'no_materials' => 'Noch keine Positionen erfasst.',
    ],
    'field' => [
        'position' => 'Pos.',
        'article' => 'Artikel/Zutat',
        'article_placeholder' => '… Artikel auswählen',
        'kind' => 'Mengenart',
        'quantity' => 'Menge',
        'quantity_or_ratio' => 'Menge / Anteil',
        'unit' => 'Einheit',
        'waste' => 'Verschnitt %',
        'tool' => 'Werkzeug',
        'tool_yes' => 'Werkzeug',
        'actions' => 'Aktionen',
        'base_portions' => 'Grundausbeute (Portionen)',
        'base_yield' => 'Ausgabemenge',
        'yield_unit' => 'Ausgabeeinheit',
        'allergen_added' => 'Zusätzlich ausweisen',
        'allergen_removed' => 'Nicht ausweisen',
        'override_reason' => 'Begründung der Abweichung',
        'portions' => 'Portionen',
        'demand' => 'Bedarf',
        'cost' => 'Plankosten',
    ],
    'kind' => [
        'fixed' => 'fest je Ansatz',
        'per_unit' => 'je Portion/Einheit',
        'ratio' => 'Verhältnisanteil',
    ],
    'action' => [
        'add' => 'Position hinzufügen',
        'remove' => 'Entfernen',
        'save_profile' => 'Profil speichern',
        'save_allergens' => 'Allergene speichern',
        'scale' => 'Skalieren',
        'back' => 'Zurück zur Übersicht',
    ],
    'allergens' => [
        'none' => 'Keine Allergene ausgewiesen.',
        'unresolved_heading' => 'Zutaten ohne Allergen-Zuordnung',
    ],
    'plan' => [
        'total' => 'Summe',
        'per_portion' => 'je Portion',
    ],
    'flash' => [
        'material_saved' => 'Position gespeichert.',
        'material_removed' => 'Position entfernt.',
        'profile_saved' => 'Rezeptprofil gespeichert.',
        'allergens_saved' => 'Allergene der Zutat gespeichert.',
        'menu_saved' => 'Menü gespeichert.',
    ],
    'error' => [
        'published_immutable' => 'Veröffentlichte Rezeptstände sind unveränderlich — bitte eine neue Version anlegen.',
        'override_reason_required' => 'Allergen-Abweichungen benötigen eine Begründung.',
        'ratio_required' => 'Für einen Verhältnisanteil bitte einen Wert größer 0 angeben.',
        'allergens_unresolved' => 'Freigabe blockiert: Zutaten ohne Allergen-Zuordnung (:articles). Allergene zuordnen oder begründete Abweichung erfassen.',
    ],
    'costs' => [
        'unit_unmapped' => ':article: Einheit „:unit" nicht in Basiseinheit umrechenbar — Kosten unvollständig.',
        'price_missing' => ':article: kein Einkaufspreis hinterlegt — Kosten unvollständig.',
    ],
    'menu' => [
        'title' => 'Menüplanung',
        'intro' => 'Menüs und Buffets aus veröffentlichten Rezepturen — die Gästezahl skaliert den aggregierten Materialbedarf.',
        'empty' => 'Noch keine Menüs angelegt.',
        'no_date' => 'ohne Termin',
        'no_dishes' => 'Noch keine Gerichte im Menü.',
        'not_published' => 'kein veröffentlichter Stand',
        'dishes_heading' => 'Gerichte',
        'aggregate_heading' => 'Aggregierter Materialbedarf',
        'missing_published' => 'Ohne veröffentlichten Rezeptstand nicht berücksichtigt: :dishes',
        'no_materials' => 'Kein Materialbedarf — Gerichte mit veröffentlichten Rezeptständen hinzufügen.',
        'field' => [
            'name' => 'Name',
            'event_date' => 'Termin',
            'guest_count' => 'Gästezahl',
            'dishes' => 'Gerichte',
            'dish' => 'Gericht',
            'dish_placeholder' => '… Gericht auswählen',
            'portions_per_guest' => 'Portionen je Gast',
            'portions_total' => 'Portionen gesamt',
            'version' => 'Rezeptstand',
        ],
        'action' => [
            'create' => 'Menü anlegen',
            'open' => 'Öffnen',
            'add_dish' => 'Gericht hinzufügen',
        ],
    ],
];
