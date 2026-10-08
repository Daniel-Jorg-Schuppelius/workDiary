---
title: "Altsystem (Legacy)"
topic: legacy.overview
version: 2
keywords:
    - altes System
    - Vorgängersystem
    - Altdaten
    - Datenübernahme
    - Datenmigration
    - Notdienstplan
    - Bereitschaftsplan
    - Callcenter-Login
    - Altsystem-Archiv
    - alte Tagebucheinträge
    - Altsystem-Benutzer
    - Zentrale
    - Legacy-Modus
related:
    - auth.login
    - admin.tenants
---

Der Legacy-Bereich ist eine Brücke zum Altsystem. Er stellt Daten und
Funktionen der früheren Anwendung weiterhin bereit, bis sie vollständig
in WorkDiary überführt sind. Der Zugang ist nur für Nutzer mit einer
zugeordneten Altsystem-Kennung sowie für Administratoren möglich.

Der Bereich umfasst:

- **Tagebuch**: Wochenansicht sowie Anlegen, Bearbeiten und Löschen von
  Altsystem-Einträgen.
- **Notdienst und Bereitschaft**: Planung und Pflege der Notdienst- und
  Bereitschaftseinträge.
- **Archiv**: Lesezugriff auf archivierte Tagebuch-Einträge inklusive
  Wochenansicht und Einzelansicht; Archivläufe können angestoßen werden.
- **Nutzerverwaltung**: Verwaltung der Altsystem-Benutzer.
- **Callcenter**: Eigener Login mit Notdienstplan für den
  Callcenter-Zugang.

Lese-Funktionen wie Wochen- und Archivansicht sind stets verfügbar.
Schreibende Aktionen (Anlegen, Bearbeiten, Löschen) sowie die
Passwortänderung im Altsystem sind nur freigeschaltet, wenn der
Schreibzugriff auf das Altsystem aktiviert ist. Für Administratoren gibt
es zudem ein Migrations-Dashboard, um Daten aus dem Altsystem zu
übernehmen.

## Zentrale und Mitarbeiter

Im **Legacy-Modus** – umschaltbar unter **Einstellungen** in der Kopfzeile,
sofern Sie Zugang zu beiden Bereichen haben – zeigt die Hauptnavigation
**Wochenansicht**, **Arbeitsliste** und **Zentrale**.

**Zentrale** ist das Lagebild des Altsystems:

- Kacheln **Probleme**, **Offen**, **Bestätigt** und **Erledigt (7d)** sowie
  **Überfällig**, **Heute fällig** und **Nächste 7d**; ein Klick öffnet die
  Arbeitsliste mit dem passenden Filter.
- Der **Wochenplan** mit **Notdienst** und **Bereitschaft**, beginnend mit
  gestern; geblättert wird mit **Vorherige Woche**, **Nächste Woche** und
  **Aktuelle Woche**.
- **Wochenende & Feiertage**, **Neue Einträge (14 Tage)**, **Top
  Verantwortliche (offen)**, **Nächste Feiertage (30 Tage)** und **Offene
  Meldungen**.

Die Dienstpläne sieht jede Person. Die Tagebuchdaten aller Personen sehen
Altsystem-Administratoren und die Rolle **Buchhaltung**, alle anderen nur ihre
eigenen. Der Callcenter-Login führt auf dieselbe Seite.

**Mitarbeiter** steht im Verwaltungsmenü (Symbol **Verwaltung** in der
Kopfzeile) unter **Personal** und listet die Benutzer des Altsystems mit
**Name** und **E-Mail**:

- **Neuer Mitarbeiter** legt eine Person mit **Name**, **E-Mail** und
  **Passwort** an; beim Bearbeiten bleibt das Passwort unverändert, wenn das
  Feld leer bleibt.
- Die ersten drei Konten des Altsystems (Administratoren) erscheinen nicht und
  lassen sich hier nicht ändern.
- **Löschen** ist nur möglich, solange zu der Person keine Tagebuch-,
  Notdienst- oder Bereitschaftseinträge bestehen.
- Anlegen, Ändern und Löschen setzen den Schreibzugriff auf das Altsystem
  voraus.

**Berechtigung:** Die Seite **Mitarbeiter** öffnen Altsystem-Administratoren
und der Plattformbetrieb; die Administratorrolle der Organisation allein
genügt nicht.
