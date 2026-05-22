# Einheitliche Bedienung und UX-Konventionen

## Status

Proposed — der **UX-Pattern-Katalog** ist mit MVP-006 (Issue #6) in
[`docs/ux-pattern-katalog.md`](../ux-pattern-katalog.md)
verbindlich festgeschrieben (Komponenten, Aktions-Glossar, Status-Tones,
Detailseiten-Anatomie, UI-Review-Checkliste). Mit MVP-009 (Issue #9) ergänzt
um das fachliche [Status- und Aktionsglossar](../status-aktionsglossar.md).

## Ziel

WorkDiary soll über alle Module hinweg einheitlich bedienbar sein. Nutzer sollen
nicht für Zeiterfassung, Protokolle, Aufträge, Kunden, Assets, Abnahmen,
Reports oder Administration jeweils neue Bedienmuster lernen müssen. Aktionen,
Filter, Tabellen, Dialoge, Status, Suche, Export, Freigabe und mobile Abläufe
sollen konsistent funktionieren.

## Warum

Ein breites Produkt wird schnell unübersichtlich, wenn jedes Modul eigene
Bedienlogik hat. Einheitliche Bedienung senkt Schulungsaufwand, vermeidet
Fehler, erhöht Akzeptanz im Außendienst und macht WorkDiary verkaufbarer. Gerade
bei Kunden mit vielen Rollen ist Konsistenz ein Qualitätsmerkmal.

## Bediengrundsätze

- Gleiche Aktion sieht gleich aus und heißt gleich.
- Gleiche Statuslogik wird modulübergreifend wiederverwendet.
- Listen, Filter, Suche, Sortierung, Bulk-Aktionen und Export folgen einem
  gemeinsamen Muster.
- Formulare nutzen gleiche Struktur: Pflichtfelder, Validierung, Speichern,
  Abbrechen, Löschen, Archivieren.
- Dialoge, Detailseiten und Protokolle haben vorhersehbare Positionen für
  Aktionen, Historie, Anhänge, Kommentare und Status.
- Mobile Bedienung priorisiert schnelle Erfassung, geringe Ablenkung und klare
  Offline-/Sync-Zustände.
- Fehler, Warnungen und leere Zustände sind verständlich und handlungsorientiert.

## MVP

- Produktweite UX-Konventionen als Dokumentation.
- Komponenten- und Pattern-Katalog für Tabellen, Filter, Formulare, Modale,
  Detailseiten, Status, Timelines, Anhänge, Kommentare und Exporte.
- Einheitliche Aktionsnamen und Icons.
- Einheitliche Status- und Farbsemantik.
- Einheitlicher Umgang mit Leerzuständen, Ladezuständen und Fehlern.
- Mobile Pattern für Auftrag, Zeit, Foto, Protokoll, Unterschrift und Sync.
- UI-Review-Checkliste für neue Features.

## Akzeptanzkriterien

- Ein Nutzer erkennt in neuen Modulen bekannte Bedienmuster wieder.
- Neue Feature-Seiten bestehen eine UI-Konventionsprüfung.
- Tabellen, Filter, Aktionen und Status verhalten sich konsistent.
- Mobile und Desktop-Flows unterscheiden sich nur dort, wo der Nutzungskontext
  es erfordert.
- Barrierefreiheit, Tastaturbedienung und responsive Darstellung werden
  berücksichtigt.

## Vorhandene Basis

- [UI-Vereinheitlichung — Seiten-Audit](../ui-unification-audit.md)
- Blade-Komponenten wie `page-shell`, `card`, `table`, `empty-state`, `modal`,
  `page-toolbar`, `icon-btn`.
- Globale Suche und wiederverwendbare Filter-/Tabellenkomponenten.

## Später

- Storybook- oder Pattern-Preview für Komponenten.
- Usability-Tests mit typischen Rollen: Außendienst, Teamleitung, Buchhaltung,
  Geschäftsführung, Kundenadmin.
- Keyboard-Shortcuts für Power-User.
- Geführte Onboarding-Flows pro Rolle.
- Rollenbasierte Startseiten und reduzierte Oberflächen.

## Abhängigkeiten

- Mobiler Field-Workflow
- Prozeduren, Arbeitsanweisungen und Checklisten
- Suche, Timeline und Fallakte
- Rollen, Rechte und Produktprofile
- Import, Migration und Onboarding
- Barrierefreiheit und Lokalisierung

## GitHub Issues

- TBD
