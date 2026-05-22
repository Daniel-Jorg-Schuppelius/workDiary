# Accessibility-Checkliste für neue Seiten

## Zweck

Diese Checkliste ist verbindlich für **jede neue Seite, jedes neue Modal und
jede neue UI-Komponente** in WorkDiary. Sie ergänzt den
[UX-Pattern-Katalog](ux-pattern-katalog.md) und das
[UI-Audit](ui-unification-audit.md).

Ziel: WorkDiary auf Niveau **WCAG 2.1 AA** halten. Die Checkliste soll im
PR-Template oder bei der UI-Review (siehe Pattern-Katalog §11) Punkt für Punkt
abgehakt werden.

## Grundregeln (immer)

1. **Tastatur first.** Jede Aktion, jeder Filter, jedes Modal muss ohne Maus
   bedienbar sein (Tab, Shift+Tab, Enter, Space, Esc, Pfeiltasten in Listen).
2. **Sichtbarer Fokus.** Kein `outline: none` ohne Ersatz. Default-Fokusring
   (`focus-visible:ring-2 focus-visible:ring-primary/60`) darf nicht entfernt
   werden.
3. **Semantik vor Stil.** Buttons sind `<button>`, Links sind `<a href>`,
   Überschriften sind `<h1>…<h6>` in korrekter Reihenfolge. Keine `<div>` mit
   `onclick`.
4. **Status nie nur durch Farbe.** Status braucht zusätzlich Text, Icon oder
   Form (Badge mit Label, `aria-label`, Material-Symbol).
5. **Kontrast ≥ 4,5:1** für normalen Text, ≥ 3:1 für große Schrift, Icons und
   Statusfarben gegenüber Hintergrund. Theme-Wechsel (Hell/Dunkel) prüfen.
6. **Sprachen-Attribut.** `<html lang="de">` bzw. dynamisch nach Locale.
7. **Reduzierte Bewegung.** Animationen respektieren
   `prefers-reduced-motion: reduce`.
8. **Zoom + Schriftgröße.** Layout muss bei 200 % Zoom und Schriftgröße
   `xx-large` benutzbar bleiben (kein horizontales Scrollen außer in echten
   Tabellen).

## Checkliste pro Seite

### Struktur

- [ ] Genau eine `<h1>` pro Seite (Page-Title in `<x-page-shell>`-Toolbar).
- [ ] Überschriften (`<h2>`, `<h3>`) folgen visueller Hierarchie ohne Sprünge.
- [ ] Landmarks vorhanden: `<header>`, `<nav>`, `<main>`, `<footer>` (Layout
      übernimmt dies; einzelne Seiten dürfen `<main>` nicht doppelt setzen).
- [ ] Seitentitel im Browser-Tab via `@section('title', …)` aussagekräftig.

### Tastaturbedienung

- [ ] Tab-Reihenfolge folgt der Lesereihenfolge (visuell oben → unten,
      links → rechts).
- [ ] Erstes interaktives Element ist sinnvoll fokussiert (z. B. erstes
      Filterfeld, nicht ein verstecktes Hilfe-Icon).
- [ ] Kein Tastatur-Trap außerhalb von Modalen.
- [ ] Skip-Link „Zum Inhalt springen" ist in `<x-page-shell>` aktiv.

### Modale (`<x-modal>`)

- [ ] Beim Öffnen Fokus in erstes interaktives Element setzen.
- [ ] Fokus innerhalb des Modals gefangen (`focus trap`).
- [ ] `Esc` schließt das Modal und gibt Fokus an den auslösenden Trigger
      zurück.
- [ ] Modal hat `role="dialog"`, `aria-modal="true"`, `aria-labelledby` zeigt
      auf den Titel.
- [ ] Hintergrund ist mit `aria-hidden="true"` ausgeblendet (Inert).

### Formulare

- [ ] Jedes Eingabefeld hat ein sichtbares `<label>` (kein Placeholder als
      Ersatz).
- [ ] Pflichtfelder sind sowohl visuell (`*`) als auch via `required` /
      `aria-required="true"` markiert.
- [ ] Fehlermeldungen werden per `aria-invalid="true"` und
      `aria-describedby` mit dem Feld verknüpft.
- [ ] Fehlerzusammenfassung am Seitenanfang ist fokussierbar
      (`role="alert"` oder `tabindex="-1"` + Programmatic Focus).
- [ ] Hilfetexte sind über `aria-describedby` verknüpft, nicht nur visuell.
- [ ] Autofill-Hinweise (`autocomplete`) sind gesetzt (Name, E-Mail,
      Telefonnummer, Adressfelder).

### Tabellen (`<x-table>`)

- [ ] `<th scope="col">` für Spaltenköpfe, `<th scope="row">` wenn sinnvoll.
- [ ] Sortierbare Spalten haben `aria-sort="ascending|descending|none"`.
- [ ] Zeilenaktionen sind echte `<button>`/`<a>`, keine reinen Icons ohne
      `aria-label`.
- [ ] Bulk-Auswahl: Checkbox in Spaltenkopf hat `aria-label="Alle auswählen"`.
- [ ] Leerzustand verwendet `<x-empty-state>` mit Text, nicht nur Grafik.

### Status & Feedback

- [ ] Statuspille trägt zusätzlich zum Farb-Tone (`ok`, `warning`, `danger`
      etc.) einen Textwert.
- [ ] Toast/Flash-Meldungen erscheinen in `aria-live="polite"`,
      Fehlertoasts in `aria-live="assertive"`.
- [ ] Lade-/Skeleton-Zustände sind als `aria-busy="true"` markiert.

### Icons und Bilder

- [ ] Dekorative Icons: `aria-hidden="true"`.
- [ ] Bedeutungstragende Icons: `aria-label` oder begleitender Text.
- [ ] Bilder mit Inhalt: aussagekräftiges `alt`. Reine Dekoration: `alt=""`.

### Farben und Themes

- [ ] Kontrast geprüft in **Hell + Dunkel** mit Browser-DevTools oder
      [contrast-finder.tanaguru.com](https://contrast-finder.tanaguru.com/).
- [ ] Fokusring auch im Dunkelmodus klar sichtbar.
- [ ] Statusfarben (Erfolg/Warnung/Fehler) sind nicht die einzige
      Informationsquelle.

### Mobile

- [ ] Touch-Ziele ≥ 44 × 44 px (Buttons, Icon-Buttons, Tabellenaktionen).
- [ ] Bottom-Sheet-Variante des Modals (Pattern-Katalog §3.3) auf < 640 px
      aktiv.
- [ ] Spaltenanzahl in Tabellen mobil reduziert (`hidden md:table-cell`).
- [ ] Kein Hover-only-Inhalt (Tooltips müssen per Long-Press / Fokus
      erreichbar sein).

### Inhalt und Sprache

- [ ] Texte in **klarer deutscher Sprache**, kurze Sätze, keine Anglizismen
      ohne Erklärung.
- [ ] Aktionslabels stammen aus dem Aktions-Glossar
      (Pattern-Katalog §4).
- [ ] Datumsangaben mit `<time datetime="…">` ausgezeichnet.
- [ ] Zahlen, Währungen und Datum lokalisiert (de-DE).

## Tests und Prüfwerkzeuge

| Werkzeug                                    | Wofür                                          |
| ------------------------------------------- | ---------------------------------------------- |
| Tastatur (Tab, Shift+Tab, Enter, Esc)       | Bedienflows ohne Maus                          |
| Browser-DevTools → Lighthouse Accessibility | Schneller Score + Befunde                      |
| axe DevTools (Browser-Extension)            | Detaillierte WCAG-Verstöße                     |
| NVDA / VoiceOver / Orca                     | Screenreader-Stichprobe pro neuem Modul        |
| Browser-Zoom 200 % + Schrift `xx-large`     | Layout-Bruchprüfung                            |
| `prefers-reduced-motion` (DevTools)         | Animationen drosselbar                         |
| Farbkontrast-Checker                        | Theme- und Statusfarben gegen Hintergrund      |

Pflicht pro neuer Seite: **mindestens** Tastatur-Durchgang, Lighthouse-Score
≥ 95 und axe ohne Critical/Serious-Befunde.

## ARIA-Spickzettel (häufige Fälle)

| Situation                                    | Markup                                                              |
| -------------------------------------------- | ------------------------------------------------------------------- |
| Schließen-Button im Modal                    | `<button aria-label="Schließen"><span aria-hidden="true">×</span>` |
| Icon-Button (Bearbeiten)                     | `<button aria-label="Bearbeiten"><x-icon name="edit"/></button>`    |
| Toggle-Button                                | `aria-pressed="true|false"`                                         |
| Expandierbare Sektion                        | `aria-expanded="true|false"` + `aria-controls="…id"`                |
| Tab-Navigation                               | `role="tablist"`/`tab`/`tabpanel`, `aria-selected`                  |
| Statuspille                                  | `role="status"` oder Text + `aria-label`                            |
| Sortierbare Spalte                           | `aria-sort="ascending|descending|none"`                             |
| Live-Region für Toasts                       | `aria-live="polite"` / `aria-live="assertive"`                      |
| Dekoratives Icon                             | `aria-hidden="true"`                                                |
| Pflichtfeld                                  | `required` + `aria-required="true"`                                 |
| Feldfehler                                   | `aria-invalid="true"` + `aria-describedby="feld-error"`             |

## Out-of-scope (MVP)

- WCAG 2.1 AAA (z. B. Kontrast 7:1).
- Vollständige Screenreader-Skripte für alle Seiten — Stichprobe genügt.
- High-Contrast-Mode-spezifische Themes (Windows Forced Colors) — wird im
  Folge-MVP aufgegriffen.
- Mehrsprachige ARIA-Labels (nur Deutsch).

## Verantwortlichkeit und Pflege

- Diese Checkliste ist **Bestandteil jedes UI-PRs**. Erkennbare Verstöße
  blocken den Merge.
- Erweiterungen werden im selben PR vorgenommen, der das Pattern einführt
  (z. B. neue Komponente). Änderungen brauchen UX- und Review-Sign-off.
- Die Liste ist auf den UX-Pattern-Katalog abgestimmt; bei Konflikten gilt
  diese Datei für Accessibility, der Pattern-Katalog für Komponenten-Form.

## Folge-MVPs

- Forced-Colors-Theme prüfen.
- Vollständiger Screenreader-Bedien-Guide pro Modul (Diary, Protokoll,
  Prozedur, Report).
- Automatisierte Accessibility-Tests in CI (axe oder pa11y) für die
  Kern-Routen.
