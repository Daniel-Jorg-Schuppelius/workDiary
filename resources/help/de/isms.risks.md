---
title: "Risikoregister"
topic: isms.risks
version: 3
keywords:
    - Risikoanalyse
    - Risikobewertung
    - Risikomatrix
    - Risiko erfassen
    - Risikoinventar
    - Risikobehandlung
    - Restrisiko
    - Risikoakzeptanz
    - Eintrittswahrscheinlichkeit
    - Bruttorisiko
    - Nettorisiko
    - Heatmap
audience: []
modules:
    - module.isms
related:
    - isms.controls
    - isms.overview
    - isms.audits
    - glossary.core
---

Im **Risikoregister** erfassen, bewerten (5×5) und behandeln Sie
Informationssicherheitsrisiken je Geltungsbereich. Sie finden es unter
**ISMS** → **Steuerung** → **Risikoregister**.

Typischer Ablauf:

1. **Risiko erfassen**: **Titel**, **Kategorie** („Organisatorisch“,
   „Technisch“, „Physisch“, „Personell“, „Lieferant“), **Bezug
   (System/Prozess/Standort)**, **Bedrohung** (die zugrunde liegende
   Bedrohung oder Schwachstelle), **Verantwortlich** und **Review
   fällig**.
2. **Bewerten**: **Eintrittswahrscheinlichkeit** (1–5) × Auswirkung
   (1–5) ergibt den **Score** (1–25). Ampel der Risikomatrix: Niedrig
   (Score ≤ 6), Mittel (Score 7–12), Hoch (Score > 12).
3. **Behandlung** wählen: „Vermeiden“, „Vermindern“, „Übertragen“ oder
   „Akzeptieren“ – und unter **Verknüpfte Maßnahmen** Maßnahmen
   zuordnen.
4. **Status** über **Status ändern** entlang der Kette pflegen:
   „Identifiziert“ → „Analysiert“ → „Behandelt“/„Akzeptiert“ →
   „Geschlossen“. Ein geschlossenes Risiko lässt sich wieder auf
   „Analysiert“ setzen.

Bewertungshistorie:

- Mit **Bewertung erfassen** legen Sie eine Bewertung an; die **Art der
  Bewertung** ist „Brutto“, „Netto“ oder „Ziel“. Jede Bewertung hat eine
  **Begründung**, optional ein **Gültig bis** (Ablauf- bzw.
  Reviewdatum) und durchläuft „Entwurf“ → „Freigegeben“ (**Freigeben**).
- **Freigegebene Bewertungen sind unveränderlich.**
- Die jüngste freigegebene **Netto**-Bewertung bestimmt die im Risiko
  angezeigten Werte. Ändern Sie Wahrscheinlichkeit oder Auswirkung direkt
  am Risiko, entsteht automatisch eine freigegebene Direktbewertung –
  die Historie bleibt lückenlos.

Wichtige Regel: Der Wechsel auf **„Akzeptiert“** (Restrisiko-Akzeptanz)
verlangt eine freigegebene Netto-Bewertung **mit Datum „Gültig bis“**.

Berechtigungen: Die Einsicht erfordert das Recht **ISMS-Register sehen
(Risiken, Maßnahmen, SoA)**; Änderungen erfordern **ISMS pflegen
(Risiken, Maßnahmen, Katalog-Import)**.

Nächste Schritte: Rückt das **Gültig bis** der jüngsten freigegebenen
Netto-Bewertung eines offenen Risikos heran oder ist es überschritten,
wird die verantwortliche Person benachrichtigt; über
**Benachrichtigungsregeln** lässt sich das zusätzlich eskalieren. Das
Feld **Review fällig** am Risiko dient der Planung und Sortierung, löst
selbst aber keine Benachrichtigung aus.
