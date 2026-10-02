---
title: "EBICS-Bankzugang"
topic: finance.ebics
version: 1
audience: []
modules:
    - module.finance
related:
    - finance.reconciliation
---

Mit EBICS (Version 3.0) holt workDiary die Tagesauszüge direkt bei der
Bank und reicht Zahlläufe ein — ohne Datei-Download und Upload im
Online-Banking.

**Einrichtung:** Unter Bankkonten öffnet das Bank-Symbol den EBICS-Zugang
des Kontos. Tragen Sie die EBICS-URL, Host-ID, Kunden-ID und Teilnehmer-ID
aus dem Zugangsbrief der Bank ein. Danach in dieser Reihenfolge: Schlüssel
erzeugen, an die Bank senden (INI und HIA), den Initialisierungsbrief
herunterladen, unterschreiben und der Bank schicken. Hat die Bank den
Zugang freigeschaltet, rufen Sie die Bankschlüssel ab — erst dann ist der
Zugang aktiv.

**Tagesauszüge:** Ein freigeschalteter Zugang holt jeden Morgen die
Auszüge (camt.053) und übernimmt sie in den Zahlungsabgleich; „Auszüge
jetzt abrufen“ tut das sofort. Bereits importierte Auszüge werden erkannt
und übersprungen.

**Zahlläufe:** Ein freigegebener Zahllauf lässt sich mit „Per EBICS
einreichen“ an die Bank senden — dieselbe Datei, die auch zum Download
bereitsteht, und genau einmal. Die Freigabe der Zahlung (elektronische
Unterschrift) erteilt der Zeichnungsberechtigte anschließend bei der Bank.

**Sicherheit:** Die Schlüssel liegen verschlüsselt und sind zusätzlich mit
einer Passphrase geschützt. Jeder Schritt und jeder Auftrag steht im
Verlauf des Zugangs. Bei Verdacht auf Missbrauch sperrt „Zugang sperren“
die Schlüssel bei der Bank; danach beginnt die Einrichtung mit neuen
Schlüsseln.
