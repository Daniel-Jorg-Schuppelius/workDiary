---
title: "Softwareinventar"
topic: isms.software
version: 1
audience: []
modules:
    - module.isms
related:
    - isms.overview
    - admin.security
    - glossary.core
---

Das **Softwareinventar** dokumentiert eingesetzte Softwareprodukte und
ihre Installationen – inklusive Support-Status und End-of-Life.

Typischer Ablauf:

1. **Produkt anlegen**: Name, Hersteller, führende Version, Kategorie
   („Betriebssystem", „Anwendung", „Service", „Bibliothek",
   „Sonstige"), Verantwortlicher.
2. **Support-Status** pflegen: „Unterstützt", „Erweiterter Support",
   „End-of-Life", „Unbekannt" – plus **EOL-Datum**.
3. **Installationen** erfassen: installierte Version, Standort
   (z. B. „Server SRV-01, Notebook NB-12"), optional Asset-Bezug.

Automatik: Liegt das EOL-Datum in der Vergangenheit, wird der
Support-Status beim Speichern automatisch auf **„End-of-Life"**
gesetzt – so fallen veraltete Produkte sofort auf.

Abgrenzung: Das Inventar beschreibt die Software **deiner
Organisation**. Die Komponenten der WorkDiary-Installation selbst
(SBOM im CycloneDX-Format) findet der Plattform-Admin in der
Komponentenübersicht der Administration.

Berechtigungen: Einsicht erfordert ISMS-Leserechte; Änderungen
erfordern ISMS-Pflegerechte.

Nächste Schritte: Der Inventarstand fließt beim Finalisieren in
**Auditpakete** ein.
