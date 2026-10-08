---
title: "Lexoffice-Produkte & Leistungen"
topic: articles.lexoffice
version: 2
keywords:
    - Lexware Office
    - Lexoffice Artikel
    - Artikel synchronisieren
    - Artikelabgleich
    - Produktkatalog
    - Preisliste
    - Sync-Konflikt
    - Artikelimport
    - Dienstleistungen
    - Steuersatz
audience: []
modules:
    - module.vertrieb
related:
    - articles.master
    - invoices.manage
    - glossary.core
---

Diese Seite zeigt das aus Lexoffice synchronisierte Verzeichnis der
Produkte und Leistungen. Es ist eine reine Lesesicht der
ERP-Schnittstelle: Die eigentliche Pflege erfolgt in Lexoffice, ein
Pull-Sync hält den lokalen Cache aktuell.

Je Eintrag sehen Sie Bezeichnung, Artikelnummer, Art (Produkt oder
Leistung), Einheit, Netto-Einzelpreis und Steuersatz. Sie können nach
Text suchen sowie nach Art und Status (aktiv, archiviert, alle) filtern
und sortieren. Über die Detailansicht öffnen Sie die Stammdaten eines
Eintrags als Dialog.

Mit ausreichender Berechtigung lässt sich der Sync manuell anstoßen; er
meldet, wie viele Einträge neu, aktualisiert oder archiviert wurden.
Voraussetzung ist, dass Lexoffice für die Organisation konfiguriert ist.

Die Konflikt-Strategie aus den Lexoffice-Einstellungen (Lexoffice gewinnt,
Lokal gewinnt, Manuelle Prüfung) gilt auch für den Artikel-Sync: Bei
„Manuelle Prüfung“ landen lokal geänderte Artikel, deren Stand in Lexoffice
abweicht, als Konflikt in der Konfliktliste des Lagers (Lager → Konflikte).
Dort entscheiden Sie je Artikel, ob der lokale Stand bleibt, der
Lexoffice-Stand übernommen oder der Konflikt verworfen wird. Der manuelle
Sync meldet die Zahl der neuen Konflikte.
