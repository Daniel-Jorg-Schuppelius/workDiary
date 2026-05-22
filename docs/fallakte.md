# Fallakte (Auftragsdetailseite)

Status: Aktiv (MVP-013, Issue #13) • Quelle:
[Feature 023 — Suche, Timeline und Fallakte](features/023-suche-timeline-fallakte.md).
• Bündelt:
[Auftrags-Timeline](auftrags-timeline.md),
[Auftrags-Lebenszyklus](auftrags-lebenszyklus.md),
[Kommunikationsnotizen](kommunikationsnotizen.md),
[Status- und Aktionsglossar](status-aktionsglossar.md),
[UX-Pattern-Katalog](ux-pattern-katalog.md).

## 1. Zweck

Eine Auftragsdetailseite, die **alle relevanten Daten zum Fall an einer
Stelle** zeigt — als „digitale Akte". Sie ist die zentrale
Arbeitsoberfläche während der Auftragsabwicklung und das
Nachweisinstrument im Streitfall.

Prinzipien:

- **Vollständigkeit** — Jede dokumentationspflichtige Info ist sichtbar
  oder höchstens einen Klick entfernt.
- **Eindeutige Reihenfolge** — Layout-Konvention statt
  Modul-spezifischer Reihenfolge.
- **Drill-down** — Jede Aggregatzeile linkt auf das Originalobjekt.
- **Sichtbarkeit explizit** — Kunden-/Intern-Pillen an jedem Eintrag.
- **Kein leeres Modul** — Sektionen, die nicht existieren, werden
  ausgeblendet (nicht „0 Einträge" anzeigen).

## 2. Seitenarchitektur

```
┌─────────────────────────────────────────────────────────────────────┐
│ Header: Titel + Statuspille + Aktionsleiste (Phasenwechsel)         │
├──────────────────────────────────┬──────────────────────────────────┤
│ Hauptspalte (8/12)               │ Seitenspalte (4/12)              │
│                                  │                                  │
│ 1) Kerndaten                     │ A) Kennzahlen-Karte              │
│ 2) Beschreibung & Inhalt         │ B) Beteiligte & Rollen           │
│ 3) Termine & Lebenszyklus        │ C) Adressen & Anfahrt            │
│ 4) Zeiterfassung                 │ D) Vertrags-/Abrechnungsdaten    │
│ 5) Material & Aufwand            │ E) Offene Folgeaktionen          │
│ 6) Offene Punkte (Mängel)        │ F) Tags                          │
│ 7) Protokolle & Abnahmen         │ G) Verknüpfte Objekte/Assets     │
│ 8) Anhänge                       │ H) Rechte/Sichtbarkeit-Indikator │
│ 9) Kommentare                    │                                  │
│ 10) Kommunikation                │                                  │
│ 11) Timeline (Auftrag)           │                                  │
│ 12) Audit-Logs (Admin)           │                                  │
└──────────────────────────────────┴──────────────────────────────────┘
```

Auf Mobile kollabieren Seiten- in Hauptspalte (Reihenfolge: Kerndaten,
Kennzahlen, Beteiligte, …).

## 3. Hauptspalten-Sektionen

### 3.1 Kerndaten

Kunde (Link), Projekt (Link), Auftragsnummer, externe Referenz, Typ,
Priorität, Erstellt am/von. Inline-Bearbeitung nur über „Bearbeiten"-Modal
(Pattern §3.4). Kein Inline-Edit auf der Seite (Audit-Klarheit).

### 3.2 Beschreibung & Inhalt

Vereinbarter Inhalt / Beauftragung (Text, Markdown light). Quelle des
„Was wurde beauftragt?"-Belegs.

### 3.3 Termine & Lebenszyklus

Tabelle der Lebenszyklus-Daten aus
[Auftrags-Lebenszyklus](auftrags-lebenszyklus.md) §1:

| Feld                | Wert / Status      |
| ------------------- | ------------------ |
| Geplant             | …                  |
| Akzeptiert          | …                  |
| Gestartet           | …                  |
| Pausen gesamt       | …                  |
| Beendet (Erfasser)  | …                  |
| Abgenommen (Kunde)  | … + Unterschrift   |
| Abgerechnet         | …                  |
| Storniert           | … (falls)          |

Aktionsleiste am Sektionskopf: nur die laut Lebenszyklus aktuell
**zulässigen** Fachaktionen (Akzeptieren / Starten / Pause /
Fortsetzen / Beenden / Übergeben / Stornieren).

### 3.4 Zeiterfassung

Aggregat: Stunden geplant / erfasst / abrechenbar / nicht abrechenbar.
Tabelle der Zeit-Einträge (Datum, Person, Dauer, Aktivität, Kommentar,
billable, Status). Aktionen: „Zeit erfassen" (`<x-modal>`), Korrekturen
nur über Korrektur-Workflow (Audit).

### 3.5 Material & Aufwand

Verbrauchtes Material, Auslagen, Spesen, jeweils mit Quelle (manuell /
aus Lieferschein), Preis-Sichtbarkeit gemäß Rolle.

### 3.6 Offene Punkte (Mängel)

Liste offener Punkte (Snagging) gemäß späterem MVP. Status-Pillen aus
Glossar. Aktion: „Offenen Punkt anlegen".

### 3.7 Protokolle & Abnahmen

Liste aller Abnahmeprotokolle (Bauakt, Wartung, Übergabe) mit Status
(Entwurf/unterzeichnet) und Empfänger. Aktion: „Protokoll erstellen".

### 3.8 Anhänge

Sektion aus UX-Pattern §3.7. Anhänge-Source: direkt am Auftrag +
**aggregiert** aus Zeit-Einträgen, Protokollen, Kommentaren,
Kommunikationsnotizen, offenen Punkten (nur Anzeige, Filter nach
Herkunft).

### 3.9 Kommentare

Sektion aus UX-Pattern §3.8 — interne Diskussion / kurze Notizen.

### 3.10 Kommunikation

Sektion aus [Kommunikationsnotizen](kommunikationsnotizen.md) §9.1 —
strukturierte Kommunikationsereignisse.

### 3.11 Timeline (Auftrag)

Vollständige chronologische Auftrags-Timeline gemäß
[Auftrags-Timeline](auftrags-timeline.md). Filter: Typ, Sichtbarkeit,
Person, Zeitraum. Pagination 50.

### 3.12 Audit-Logs (Admin)

Nur Org-Admin: Rohzugriff auf `audit_logs` (gefiltert auf
`auditable = DiaryEntry` + verwandte). Drilldown via Modal.

## 4. Seitenspalte

### 4.1 Kennzahlen-Karte

Aus [Auftrags-Lebenszyklus](auftrags-lebenszyklus.md) §3.

- Wartezeit gesamt
- Aktive Bearbeitungszeit
- Dauer Beauftragung → Abnahme
- Anzahl Pausen
- Anzahl offener Punkte
- Anzahl offener Folgeaktionen aus Kommunikation
- Abrechenbar / Erfasst (Quote)

### 4.2 Beteiligte & Rollen

Auftragsverantwortlicher, Bearbeiter, Bauleitung, Kundenkontakt,
Subunternehmer. Aus User-/Customer-Contacts. Aktion „Person hinzufügen".

### 4.3 Adressen & Anfahrt

Einsatzort + Karten-Link + Anfahrtsdauer-Schätzung (späterer MVP). Bei
mehreren Adressen Liste.

### 4.4 Vertrags-/Abrechnungsdaten

Pauschal / Zeit-Material, Stundensatz-Modell-ID, abweichende
Konditionen, Festpreis. Sichtbarkeit nach Rolle.

### 4.5 Offene Folgeaktionen

Aggregat aus Kommunikationsnotizen + offenen Punkten, sortiert nach
Frist. Aktion „erledigen".

### 4.6 Tags

Freitext-Schlagworte. Hilft Suche/Filter.

### 4.7 Verknüpfte Objekte/Assets

Aus späterem Asset-Modul (MVP-035+). Liste mit Link zum Objekt.

### 4.8 Rechte/Sichtbarkeit-Indikator

Anzeige: „Dieser Auftrag ist für Kunde [Kontaktperson] im Portal sichtbar
(seit …)" oder „nur intern sichtbar". Wechsel über Aktion.

## 5. Aktionen (Header)

Globale Header-Aktionsleiste:

- Primär: jeweils einzige *fachliche* Hauptaktion (z. B. „Starten",
  „Pausieren", „Beenden", „Abnahme erfassen") aus
  [Lebenszyklus](auftrags-lebenszyklus.md).
- Sekundär (Icon-Buttons): Bearbeiten, Drucken, Teilen, Duplizieren,
  Stornieren, Löschen — gemäß UX-Pattern §3.1.
- Status-Pille rechts neben Titel.

## 6. Sichtbarkeit für Kunden-Portal

Im Kundenportal (Rolle `kunde`) wird die Auftragsdetailseite **reduziert**
dargestellt:

| Sektion           | Kundensicht                                        |
| ----------------- | -------------------------------------------------- |
| Kerndaten         | Ja, ohne interne Preise.                            |
| Beschreibung      | Ja.                                                 |
| Termine           | Planned/Started/Completed/Accepted nur (keine Pausen-Detail). |
| Zeiterfassung     | Aggregat (Stunden gesamt) — keine Einzelzeilen.    |
| Material          | Sichtbar wenn vertraglich vereinbart.              |
| Offene Punkte     | Nur `visibility=customer`.                          |
| Protokolle        | Nur Unterzeichnete.                                 |
| Anhänge           | Nur `visibility=customer`.                          |
| Kommentare        | **Nein** (intern).                                  |
| Kommunikation     | Nur `visibility=customer`.                          |
| Timeline          | Nur Events mit `visibility=customer`.               |
| Audit-Logs        | **Nein**.                                           |

Diese Filter sind serverseitig im Repository / Policy realisiert,
nicht nur per CSS.

## 7. Performance

- N+1-frei: jede Sektion lädt ihr Aggregat über benannte
  Query-Builder (z. B. `DiaryEntry::withFallakteAggregates()`).
- Cache pro Sektion 60 s (gleiche Regel wie Timeline).
- Lazy-Load (Turbo-Frame oder htmx) für Sektionen ab §3.7 abwärts,
  damit oberer Bereich sofort interaktiv ist.
- Print-Stylesheet: alle Sektionen einklappbar, „Druck-Fallakte"-Aktion
  erzeugt PDF (späterer MVP).

## 8. Permissions (Übersicht)

| Sektion             | Permission                                          |
| ------------------- | --------------------------------------------------- |
| Sehen Detailseite   | `order.view.own` oder höher.                        |
| Termine ändern      | Lebenszyklus-Aktionen (siehe dortige Permissions).  |
| Zeit erfassen       | `time.create.own`.                                  |
| Anhänge hochladen   | `attachment.create`.                                |
| Kommentare          | `comment.create`.                                   |
| Kommunikation       | siehe [Kommunikationsnotizen](kommunikationsnotizen.md) §7. |
| Audit-Logs anzeigen | `audit.view` (nur Org-Admin).                       |

## 9. Akzeptanzkriterien

1. Auftragsdetailseite zeigt die 12 Hauptspalten-Sektionen + 8
   Seitenspalten-Karten in der definierten Reihenfolge.
2. Leere Sektionen ohne Daten werden ausgeblendet (außer §3.1, §3.2,
   §3.3, §3.11 — immer vorhanden).
3. Jede Liste linkt auf das jeweilige Originalobjekt (Drill-down).
4. Aktionsleiste zeigt **nur** zulässige Lebenszyklus-Aktionen.
5. Kunden-Portal-Sicht hält die Sichtbarkeitsmatrix §6 ein
   (serverseitig erzwungen, Tests vorhanden).
6. Audit-Sektion ist Org-Admin-only.
7. Mobile-Layout kollabiert Spalten in einer Spalte mit definierter
   Reihenfolge.
8. Lighthouse Performance ≥ 80 auf einer Akte mit 50 Zeit-Einträgen,
   20 Protokollen, 50 Timeline-Events.

## 10. Out-of-scope (MVP-013)

- Drucken/PDF-Export der Fallakte (Folge-MVP).
- Globale Suche (MVP-014).
- Kunden-Timeline (MVP-Reihe Kunde).
- Echtzeit-Updates (WebSocket / Turbo-Streams) — Folge-MVP.

## 11. Folge-MVPs

- **MVP-014** Globale Suche, die ebenfalls in diese Sektionen drilldown.
- **MVP-015** Filter/Sortierung in Listen-/Aggregat-Sektionen.
- **MVP-020** Abnahmeprotokoll → §3.7.
- **MVP-035** Assets → §4.7.
- Folge: Druck-Fallakte (PDF).
