---
title: "Auftrag anlegen"
topic: diary-entries.create
version: 2
audience: []
schema: process
related:
    - protocols.create
    - time-entries.start
    - projects.manage
    - reports.entry-type-analysis
---

## Zweck und Hintergrund

Auftragseinträge sind das Auftragsbuch von WorkDiary: Jede Wartung,
Störung oder Montage bekommt einen Eintrag mit Kunde, Typ und Status.
Der Eintrag ist der Anker für Protokolle, Zeiten und die spätere
Abrechnung — und seine Statusübergänge bilden den Auftrags-Lebenszyklus
nachvollziehbar ab.

## Voraussetzungen

- Ein angelegter **Kunde** (Pflicht), optional ein **Projekt**.
- Passende **Eintragstypen** (z. B. Wartung, Störung, Montage) — sie
  pflegt die Verwaltung.
- Das Recht, Auftragseinträge anzulegen.

## Empfohlener Ablauf

1. Öffne **„Neuer Eintrag"** in der Topbar oder die Schnellaktion auf
   dem Dashboard.
2. Erfasse **Kunde** (Pflicht) und ggf. **Projekt**.
3. Wähle den **Eintragstyp** und beschreibe den **Inhalt** in ein bis
   zwei Sätzen.
4. Optional: **Plan-Dauer** in Minuten hinterlegen.
5. Statusübergänge laufen anschließend über das **Detail-Modal** — kein
   Massen-Update aus der Liste.

![Arbeitsliste des Auftragsbuchs mit Statuszählern und Einträgen](media/auftraege/arbeitsliste.png)
*Die Arbeitsliste: Statuszähler oben, darunter die Aufträge mit Status und Aktionen.*

## Beispiel aus der Praxis

Eine Störungsmeldung kommt telefonisch herein: Der Innendienst legt in
unter einer Minute einen Eintrag vom Typ „Störung" mit Kunde und
Kurzbeschreibung an. Der Techniker findet den Auftrag auf seiner Liste,
startet die Zeit darauf und hängt später das Protokoll an.

## Typische Fehler

- **Sammel-Statuswechsel erwarten:** Übergänge laufen bewusst einzeln
  über das Detail-Modal — das hält die Audit-Spur sauber und
  verhindert Sammel-Rücksetzer.
- **Kunde „Diverses" verwenden:** Ohne echten Kundenbezug fehlen
  später Auswertung und Abrechnung.
- **Inhalt als Roman:** Ein bis zwei Sätze reichen — Details gehören
  ins Protokoll.

## Auswirkungen und nächste Schritte

Mit dem Eintrag existiert der Anker für alles Weitere: Zeit darauf
buchen, bei Bedarf ein Protokoll erzeugen und den Status bis zum
Abschluss führen. Die Typen-Auswertung zeigt später, womit der Betrieb
seine Zeit wirklich verbringt.
