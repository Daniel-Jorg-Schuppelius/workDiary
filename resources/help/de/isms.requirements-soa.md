---
title: "Anforderungen & SoA"
topic: isms.requirements-soa
version: 3
keywords:
    - Statement of Applicability
    - Erklärung zur Anwendbarkeit
    - Anwendbarkeitserklärung
    - Anforderungskatalog
    - Normkatalog
    - Normanforderungen
    - Annex A
    - ISO 27001
    - ISO 9001
    - ISO 27701
    - Katalog importieren
    - nicht anwendbar begründen
audience: []
modules:
    - module.isms
related:
    - isms.overview
    - isms.controls
    - isms.conformity
    - glossary.core
---

Hier verwalten Sie den Anforderungskatalog und das **Statement of
Applicability (SoA)** je Geltungsbereich. Sie finden die Seite unter
**ISMS** → **Steuerung** → **Anforderungen & SoA**.

Typischer Ablauf:

1. **Normkatalog laden**: ein **Normprofil** wählen und laden – ISO/IEC
   27001:2022 mit vollständigem Anhang A, außerdem ISO/IEC 27701,
   ISO 9001, ISO 22301, ISO 45001, ISO 37301 und ISO/IEC 42001 mit ihren
   Hauptkapiteln 4 bis 10 sowie das NIST Cybersecurity Framework 2.0.
   Geladen werden nur Nummer und Kurztitel, keine Normtexte. Ein erneutes
   Laden überschreibt bestehende Anforderungen und gepflegte SoA-Aussagen
   nicht. Alternativ übernimmt **OSCAL importieren** einen Katalog aus
   einer JSON-Datei.
2. Optional mit **Anforderung anlegen** eigene Anforderungen ergänzen; die
   **Quelle** lautet dann „Eigene Anforderung“ statt „Referenzkatalog“.
3. **SoA-Aussagen anlegen**: legt für den gewählten Geltungsbereich die
   fehlenden Aussagen zu allen Anforderungen an; bestehende bleiben
   unverändert. Danach pflegen Sie jede Aussage mit **SoA-Aussage
   bearbeiten**.
4. Die druckbare Ansicht **SoA** (**Drucken / PDF speichern**) für
   Nachweise und Audits nutzen; **Export (CSV)** und **Export (JSON)**
   liefern die Daten als Datei.

Wichtige Felder je Anforderung: **Norm**, **Ausgabe**, **Ref-Nr.**
(z. B. „A.5.1“) und ein eigener **Titel** – bewusst kein Normtext.

Je SoA-Aussage:

- **Anwendbar** ja/nein – bei „nein“ ist eine **Begründung** Pflicht
  und der **Umsetzungsstatus** wird automatisch **„Nicht anwendbar“**.
- **Umsetzungsstatus**: „Offen“, „Teilweise umgesetzt“, „Umgesetzt“,
  „Nicht anwendbar“.
- **Evidenz-Notiz**: Verweis auf Nachweis oder Dokument.

Berechtigungen: Das Recht **ISMS-Register sehen (Risiken, Maßnahmen,
SoA)** erlaubt die Einsicht. Katalog-Import und Pflege erfordern **ISMS
pflegen (Risiken, Maßnahmen, Katalog-Import)**.

Nächste Schritte: Verknüpfen Sie Anforderungen mit normneutralen
**Maßnahmen** (Spalte **Verknüpfte Maßnahmen**) – so entsteht die Brücke
vom „Was“ der Norm zum „Wie“ Ihrer Umsetzung.
