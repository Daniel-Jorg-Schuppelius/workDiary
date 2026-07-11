---
title: "Prüfmittel & Kalibrierung"
topic: asset-compliance.overview
version: 1
audience: []
related:
    - rental.overview
    - asset-finance.overview
---

Das Modul verwaltet prüfpflichtige Messgeräte, Maschinen, Fahrzeuge und
Anlagen: Eichung, Kalibrierung, DGUV/UVV, HU/AU, elektrische Prüfung,
Herstellerwartung und interne Kontrollen — mit Nachweisen und
Einsatzsperren.

**Prüfprofile (Katalog):** Globale Vorlagen (z. B. DGUV V3, HU, Eichung)
werden durch Organisationsprofile mit gleichem Code überschrieben.
Profile tragen Intervall, Vorwarnzeit, Toleranz, Nachfrist und
Sperrwirkung — Regelwerksänderungen sind Datenpflege, kein Release.
Welche Pflicht im Einzelfall gilt, entscheidet der Betrieb (keine
Rechtsberatung).

**Prüfpflichten:** Die Zuweisung eines Profils an ein Asset erzeugt eine
Pflicht mit Fälligkeit und Verantwortlichen. Fällige Prüfungen erzeugen
Warnungen; nach Ablauf der Nachfrist sperrt das System gemäß Profil über
das gemeinsame Sperrmodell — Verleih, Disposition und Einsatz lesen
denselben Status.

**Prüfprotokolle & Zertifikate:** Prüfungen werden mit Messwerten gegen
die eingefrorenen Grenzwerte, Ergebnis, Gültigkeit, Unterschrift und
optionalem Kalibrierzertifikat (Nummer, Aussteller, Dokument-Hash)
erfasst. Nachweise sind unveränderbar — Korrekturen erfolgen versioniert.

**Ausnahmefreigaben:** Gesperrte Assets können befristet, begründet
(mindestens 20 Zeichen) und auditiert je Einsatzkontext freigegeben
werden.

**Externe Prüfer:** Prüfstellen erhalten über einen zeitlich begrenzten,
zweckgebundenen Zugang die Möglichkeit, Nachweise zu liefern — ohne
Zugriff auf andere Daten.

**Normen-Referenzmatrix:** Prüfarten sind Rechtsquellen zugeordnet
(MessEG/MessEV, BetrSichV, DGUV, § 29 StVZO, ISO/IEC 17025) — als
Referenz ohne Konformitätszusage.
