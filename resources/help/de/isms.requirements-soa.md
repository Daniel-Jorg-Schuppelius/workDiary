---
title: "Anforderungen & SoA"
topic: isms.requirements-soa
version: 1
audience: []
related:
    - isms.overview
    - isms.controls
    - isms.conformity
    - glossary.core
---

Hier verwaltest du den Anforderungskatalog und das **Statement of
Applicability (SoA)** je Geltungsbereich.

Typischer Ablauf:

1. **Katalog laden**: Normprofil importieren (ISO/IEC 27001:2022 mit
   vollständigem Annex A, außerdem 27701, 9001, 22301, 45001, 37301,
   42001 auf HLS-Ebene). Der Import ist idempotent – bestehende
   Anforderungen und SoA-Aussagen werden nicht überschrieben.
2. Optional **eigene Anforderungen** ergänzen (Quelle „Eigene" statt
   „Katalog").
3. **SoA-Aussagen anlegen**: je Geltungsbereich für alle Anforderungen
   erzeugen, dann pro Aussage pflegen.
4. Druckbare **SoA-Ansicht** für Nachweise und Audits nutzen.

Wichtige Felder je Anforderung: **Norm**, **Ausgabe**, **Referenz**
(z. B. „A.5.1") und ein eigener **Kurztitel** – bewusst kein Normtext.

Je SoA-Aussage:

- **Anwendbar** ja/nein – bei „nein" ist eine **Begründung** Pflicht
  und der Umsetzungsstatus wird automatisch **„Nicht anwendbar"**.
- **Umsetzungsstatus**: „Offen", „Teilweise", „Umgesetzt",
  „Nicht anwendbar".
- **Nachweis**: Verweis auf Dokument oder Beleg.

Berechtigungen: ISMS-Leserechte erlauben die Einsicht. Katalog-Import
und Pflege erfordern ISMS-Pflegerechte.

Nächste Schritte: Verknüpfe Anforderungen mit normneutralen
**Maßnahmen** – so entsteht die Brücke vom „Was" der Norm zum „Wie"
deiner Umsetzung.
