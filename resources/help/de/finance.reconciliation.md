---
title: "Zahlungsabgleich"
topic: finance.reconciliation
version: 1
audience: []
related:
    - finance.transfers
    - roles.buchhaltung
    - glossary.core
---

Der **Zahlungsabgleich** liest Bankauszüge im Format **CAMT.053** (bevorzugt)
oder **MT940** (Fallback) ein, normalisiert die Bankumsätze in einem
**Prüfbereich** und schlägt offene Rechnungen bzw. freigegebene Spesen zur
Zuordnung vor. **Der Import allein ändert keinen Beleg** – erst die
**Bestätigung** setzt `Rechnung → bezahlt` (mit Zahldatum) bzw. markiert eine
Spese als erstattet.

## Ablauf

1. **Importieren:** Bankdatei hochladen (optional ein eigenes Bankkonto
   wählen; sonst wird es automatisch über die IBAN zugeordnet). Gleiche Dateien
   werden über den Datei-Hash als Dublette abgewiesen; bereits bekannte Umsätze
   werden beim erneuten Import übersprungen.
2. **Prüfen:** Im Auszug-Detail zeigt jeder Umsatz einen Status
   (Offen/Zugeordnet/Beiseitegelegt/Nicht zuordenbar) und – sofern offen –
   **Zuordnungsvorschläge** mit Trefferwert und Begründung
   (Rechnungsnummer, Betrag, Skonto, IBAN-Treffer, Datumsnähe).
3. **Bestätigen:** Mit *Bestätigen* wird die Zuordnung angelegt und die Wirkung
   auf den Beleg gesetzt. Alternativ *Beiseitelegen* (z. B. Bankgebühr) oder
   *Nicht zuordenbar*.
4. **Zurücknehmen:** Eine bestätigte Zuordnung ist **reversibel** – sie wird
   aufgehoben und die Belegwirkung (bezahlt/erstattet) nur dann zurückgenommen,
   wenn dieser Umsatz die Zahlung war. **Der Bankumsatz selbst wird nie
   verändert.**

## Praxisfälle

- **Skonto:** Eine Unterzahlung innerhalb der Skonto-Toleranz (Standard 3 %)
  gilt als vollständige Zahlung.
- **Cent-Toleranz:** Rundungsdifferenzen bis 2 Cent verhindern keinen
  Vorschlag.
- **Teilzahlung/Überzahlung:** werden als eigene Zuordnungsart geführt; bei
  Teilzahlung bleibt die Rechnung offen.
- **Saldenkette:** Eröffnungssaldo + Summe der Umsätze wird gegen den
  Schlusssaldo geprüft; Differenzen werden als Warnung ausgewiesen.
- **Fremdwährung:** Umsätze in abweichender Währung werden nur erkannt und zur
  manuellen Klärung markiert.

## Datenschutz

Bankdaten mit Personenbezug (Name, IBAN, Verwendungszweck der Gegenpartei)
liegen **verschlüsselt** vor. Das Matching läuft ausschließlich über
unverschlüsselte Ableitungen (Hash der IBAN, herausgelöste Rechnungsnummern,
Beträge, Daten). Jede Zuordnungsaktion wird revisionssicher in einer
Hash-Kette protokolliert.

## Berechtigungen

- **Bankdatei importieren** und **Zuordnungen bestätigen/zurücknehmen:**
  Rolle *Buchhaltung* (sowie Administration).
- **Eigene Bankkonten verwalten:** nur Administration.
