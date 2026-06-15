---
title: "Arbeitsschutz & Sicherheitsereignisse"
topic: safety.overview
version: 1
audience: []
related:
    - reports.overview
    - procedures.designer
---

Das Sicherheitsereignis-Register dokumentiert Unfälle, Beinaheunfälle,
Gefährdungen und Mängel nachvollziehbar – als Grundlage für Audit,
Haftung und kontinuierliche Verbesserung (Feature 013).

## Ereignis melden

Über „Ereignis melden" wird ein Vorfall mit Art (Unfall, Beinaheunfall,
Gefährdung, Mangel), Schweregrad, Zeitpunkt, Ort, betroffener Person,
Beschreibung und Sofortmaßnahme erfasst. Der Außendienst darf melden;
die Registerführung (Bearbeiten, Status, Abschluss) bleibt bei
Teamleitung und Administration.

## Kritische Ereignisse

Ein Unfall oder ein als kritisch eingestuftes Ereignis löst sofort eine
Benachrichtigung an die Leitung aus (Ereignis `safety.criticalEvent`).
So werden schwerwiegende Vorfälle nicht übersehen.

## Status und Abschluss

Der Status durchläuft *Gemeldet → In Untersuchung → Maßnahmen definiert
→ Geschlossen*. Der Abschluss erfordert eine Ursachenanalyse. Beim oder
nach dem Schließen kann ein offener Punkt als Folgemaßnahme (Nacharbeit)
angelegt werden.

## Qualifikationen und Pflichtchecks

Ablaufende Qualifikationen und Unterweisungen werden über den
Fristen-Scanner gemeldet (Ereignis `qualification.expiring`). Pflicht-
Sicherheitschecklisten je Auftragstyp werden über die Prozedurvorlagen
abgebildet: In der Anwendbarkeit einer Vorlage lässt sich der Auftragstyp
hinterlegen, sodass kritische Tätigkeiten verbindliche Schrittfolgen,
Nachweise und Vier-Augen-Prüfungen erzwingen.

## Auswertung

Die Sicherheits-Auswertung (Auswertungen → Arbeitsschutz) zeigt
Ereignisse je Art und Schweregrad im Zeitraum sowie offen gegen
geschlossen.
