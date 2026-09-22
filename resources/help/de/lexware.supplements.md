---
title: "Lexware-Ergänzungen: Tarif, Funktionsmatrix und Übergabe"
topic: lexware.supplements
version: 1
audience: []
modules:
    - module.vertrieb
related:
    - invoices.manage
    - articles.lexoffice
---

Unter **Abrechnung → Lexware-Ergänzungen** hinterlegen Sie den gebuchten Lexware-Office-Tarif (S, M, L, XL oder „Unbekannt / Sondervertrag“) mit Quelle, Bestätigungsdatum und — bei Testzugängen — Ablaufdatum samt bestätigtem Folgetarif. Die Seite funktioniert ohne API-Verbindung.

**Funktionsmatrix:** Je Funktion sehen Sie, ob sie in Ihrem Tarif **in Lexware enthalten** ist, ob workDiary sie als **Ergänzung** anbietet oder ob sie **geplant** (Ausbau) ist. Bei unbekanntem Tarif gibt es keine sichere Aussage zu Lexware; lokale Funktionen bleiben nach ihren eigenen Voraussetzungen nutzbar. Im ersten Paket ergänzt workDiary für S Standard-/E-Rechnungen, Angebote und Mahnungen aus dem Bestand, für S/M/L die **Serienrechnungen** über Abrechnungspläne.

**Ergänzung aktivieren:** Nur bewusst im Tarifprofil aktivierte Ergänzungen erscheinen als „In workDiary verfügbar“. Voraussetzung sind das Modul „Vertrieb & Faktura“, die Rechnungshoheit **workDiary** (ein extern geführter Kunde erhält keine lokale Serie — die Umstellung ist ein eigener Vorgang mit Stichtag) und das Recht, Rechnungen einzusehen. Der Tarif ist eine Orientierung, keine Berechtigung; ein höherer Tarif entzieht nichts.

**Übergabeliste:** Unter „Übergabeliste Lexware“ stehen die ausgestellten Belege lokal geführter Kunden im gewählten Header-Zeitraum mit getrenntem Rechnungs-, Versand- und Übergabestatus. **Exportieren** lädt das eingefrorene Original je Beleg als PDF samt SHA-256 und einer Zuordnungsliste (CSV) als Paket herunter — ein Download für Sie, kein behauptetes Lexware-Importformat. **Manuell bestätigen** quittiert die Übergabe mit Benutzer, Zeitpunkt und Vermerk. „Exportiert“ oder „bestätigt“ bedeutet nie „gebucht“ oder „bezahlt“; Storno und Gutschrift bleiben eigene Belege mit Bezug zum Original.

**Automatische Übergabe:** Der Übergabeweg „automatisch“ setzt einen eigenen API-Schlüssel (Tarif XL) und den nachgewiesenen Übergabeweg voraus; bis dahin bleibt der manuelle Export der Regelweg. Offene Übergaben bleiben bei einem Tarifwechsel sichtbar.

**Rechte:** Die Seiten sehen alle mit „Rechnungen auflisten“; das Tarifprofil ändert nur, wer „Finanzkonfiguration“ hat; Export und Bestätigung brauchen „Rechnungen exportieren“.
