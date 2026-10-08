---
title: "Lizenzbestand"
topic: finance.license-stock
version: 1
keywords:
    - Lizenzverwaltung
    - Lizenzschlüssel
    - Seriennummer
    - Aktivierungsschlüssel
    - Softwarelizenzen
    - Product Key
    - Lizenz verkaufen
    - Lizenzpaket
    - Lizenzen weiterverkaufen
    - Meldebestand
    - Nachbestellen
    - Schlüssel importieren
audience: []
modules:
    - module.reselling
related:
    - finance.resale
---

Der **Lizenzbestand** verwaltet Lizenzen, die Sie paketweise einkaufen und
einzeln weiterverkaufen — etwa zehn VPN-Lizenzen, von denen jede aus mehreren
zusammengehörigen Schlüsseln besteht. Gezählt werden immer Lizenzen, nie
Schlüssel.

## Produkt und Paket

1. **Lizenzprodukt anlegen:** Name, Hersteller und die Schlüssel je Lizenz,
   z. B. „Seriennummer“ und „Aktivierungsschlüssel“. Der **Meldebestand**
   legt fest, ab wann „Nachbestellen“ erscheint: leer = nie, 0 = erst bei
   ausverkauft. Optional verknüpfen Sie einen Artikel aus dem Artikelkatalog
   (Artikelstamm oder angebundenes Buchhaltungsprogramm).
2. **Lizenzpaket anlegen:** Paketkennung, Kaufdatum, Lieferant, Anzahl und
   optional der Einkaufsbeleg. Es entstehen so viele nummerierte Lizenzen
   wie gekauft, zunächst ohne Schlüssel. Nachkäufe sind neue Pakete.
3. **Schlüssel erfassen:** einzeln über „Schlüssel pflegen“ oder als CSV mit
   einer Zeile je Lizenz (Vorlage im Paket). Die Vorschau zeigt Zeilen und
   Fehler; übernommen wird nur eine fehlerfreie Datei, und erst nach Ihrer
   Bestätigung.

## Status einer Lizenz

- **Unvollständig:** mindestens ein Schlüssel fehlt — nicht verkaufbar.
- **Verfügbar:** alle Schlüssel vorhanden, nicht verkauft, nicht gesperrt.
- **Verkauft:** genau einem Kunden zugeordnet, mit dem ganzen Schlüsselsatz.
- **Gesperrt:** mit Grund aus dem Verkauf genommen.

Gekauft ist immer die Summe aus verfügbar, verkauft, unvollständig und
gesperrt. Der Bestand gilt aktuell und hängt nicht vom Zeitraum im Kopf ab.

## Verkaufen und korrigieren

- **Lizenz verkaufen** schlägt die älteste verfügbare Lizenz vor. Der Verkauf
  wird dokumentiert, erzeugt aber keine Rechnung; eine Rechnungsnummer ist
  nur eine Referenz. Mit einem Fremdkunden als Halter bleibt der Kunde der
  Rechnungsempfänger.
- **Verkauf berichtigen** ordnet dieselbe Lizenz nahtlos einem anderen
  Kunden zu; beide stehen in der Historie.
- **Verkauf zurücknehmen** sperrt die Lizenz, denn ihr Schlüssel könnte schon
  genutzt sein. Freigeben lässt sie sich nur mit Grund und Ihrer Bestätigung.

## Schlüssel schützen

Schlüssel liegen verschlüsselt. Listen, Kundenakte und Formulare zeigen sie
nie; der Klartext erscheint nur über „Schlüssel anzeigen“ mit dem eigenen
Recht „Lizenzschlüssel im Klartext anzeigen“, das keiner Rolle automatisch
zugewiesen wird. Jeder Zugriff wird protokolliert — ohne den Wert.
