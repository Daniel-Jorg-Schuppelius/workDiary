---
title: "Steuerregelmatrix"
topic: finance.tax-rules
version: 1
audience:
    - admin
related:
    - invoices.manage
---

Die Steuerregelmatrix ist der versionierte Katalog, aus dem die lokale
Fakturierung ihre Steuersätze ermittelt. WorkDiary liefert einen
Grundkatalog aus; eigene Zeilen der Organisation überschreiben ihn — der
ausgelieferte Katalog selbst bleibt unverändert.

**Aufbau:** Jede Regel gilt für ein Land (optional eine Region), eine
Kategorie (services, goods, shipping, materials, expenses, construction,
media, other) und eine Satzart (standard, reduced, zero, exempt,
reverse_charge, export) — mit Prozentsatz, Gültig-ab/-bis, Quellenangabe
und Notiz.

**Stichtagslogik:** Maßgeblich ist das Leistungsdatum, nicht das
Rechnungsdatum. Angewendet wird die jüngste zum Stichtag gültige aktive
Regel; Organisations-Zeilen haben Vorrang vor dem Katalog. Existiert für
eine Kategorie nichts Spezifisches, greift die Dienstleistungs-Kategorie
als Rückfall.

**Warnungen:** Beim Anlegen und beim Import verhindert die
Überschneidungsprüfung, dass zwei aktive Regeln desselben
Geltungsbereichs zeitlich überlappen. Die Übersicht warnt außerdem vor
Lücken in aktiven Regelketten — Zeiträumen, für die keine Regel gilt.

**CSV-Import:** Semikolon-getrennte Datei mit den Spalten country,
category, rate_type, rate, valid_from, valid_to, source, note (Kopfzeile
erlaubt). Zeilen mit unbekannter Kategorie/Satzart oder Überschneidung
werden gemeldet und übersprungen, der Rest wird importiert.

**Stilllegen statt löschen:** Regeln werden nie gelöscht, sondern
stillgelegt — danach greifen wieder der Katalog bzw. ältere Regeln.
Anlage und Stilllegung sind auditiert; nur eigene Zeilen der Organisation
lassen sich stilllegen.

**Einfrieren beim Ausstellen:** Beim Ausstellen einer Rechnung wird der
tatsächlich verwendete Steuerkontext (Satz, Regelquelle, Stichtag,
Kategorie, Steueraufriss) am Beleg eingefroren. Spätere Regeländerungen
wirken damit nur auf neue Belege, nie auf bereits gestellte.

**Sonderfälle mit Vorrang:** Die Kleinunternehmer-Einstellung (§ 19 UStG)
schaltet den Steuerausweis komplett ab. Ein fest hinterlegter
Standardsteuersatz der Organisation geht der Matrix im Inland vor.
EU-Kunden mit formal gültiger USt-IdNr. erhalten automatisch Reverse
Charge (0 %), Drittlandskunden den Export-Hinweis (0 %) — passende
Matrix-Zeilen liefern dafür den Hinweistext auf dem Beleg.
