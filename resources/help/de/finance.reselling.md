---
title: "Lizenz-Abgleich"
topic: finance.reselling
version: 1
audience: []
modules:
    - module.finance
related:
    - finance.transfers
    - roles.buchhaltung
    - glossary.core
---

Der **Lizenz-Reselling-Abgleich** prüft, ob jede Abrechnungsperiode der
weiterverkauften Microsoft-365-Abos mit einer Ausgangsrechnung in Lexoffice
belegt ist, und stellt die Verkaufspreise dem Einkauf gegenüber.

**Was du hochlädst:** den Export des Telekom Cloud Marketplace
(purchases.csv), den Vertragsexport des Quality-Hosting-Partnerportals
(XLSX) und optional dessen Preisliste. Beide Exporte zusammen ergeben den
Bestand vor und nach der Migration; die Ablösung wird erkannt und die
Telekom-Laufzeit am Vertragsstart bei Quality Hosting gekappt.

**Was der Lauf tut:** Er zerlegt jede Position in Jahres- bzw.
Monatsperioden, ordnet jede Marketplace-Firma einem Lexoffice-Kontakt zu
(Zuordnungsdatei, Partner-Kundennummer, Kundenstamm, eindeutige Namenssuche —
nie geraten) und sucht je Periode eine passende Rechnungsposition im
Zeitfenster um den Periodenbeginn.

**Status je Periode:** Gedeckt, Unter Einkauf (Stückpreis unter Einkauf),
Teilweise (Lizenzen und Lizenzmonate), Nur Betrag (Beleg ohne Positionen), Fehlt, Nicht
zugeordnet. Firmen ohne Zuordnung löst du beim nächsten Lauf über eine
Zuordnungsdatei auf: eine Zeile je Firma, `Firma;Lexoffice-Kontakt-UUID`
oder `Firma;customer:<Sqid>`.

**Preisprüfung:** Je Produkt siehst du den Einkauf laut Vertrag, den
aktuellen Listenpreis und die Hersteller-UVP sowie die tatsächlich
berechneten Verkaufspreise je Stück. Liegt dein Preis unter Einkauf oder
UVP, oder ist ein laufender Vertrag teurer als die aktuelle Liste, steht
ein Hinweis in der Zeile.

Der Lauf liest Lexoffice im Hintergrund und dauert bei vielen Kunden
einige Minuten. Er schreibt nichts in Lexoffice und nichts in die
Stammdaten — der Bericht liegt nur am Lauf und lässt sich als CSV laden.
