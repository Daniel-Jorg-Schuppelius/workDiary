---
title: "Versandanbindungen DHL, UPS und FedEx"
topic: admin.shipping-carriers
version: 1
keywords:
    - Versand
    - DHL
    - UPS
    - FedEx
    - Versandlabel
    - Paketlabel drucken
    - Retourenlabel
    - Sendungsnummer
    - Paketdienst
    - Geschäftskundenportal
    - Sandbox
    - Carrier
audience:
    - admin
modules:
    - module.versand
related:
    - admin.integrations
    - admin.plugins
    - manufacturing.orders
    - claims.overview
    - admin.organization-settings
    - admin.operations
---

Die Seite **Versand & Logistik** – im Menü unter **Versand** – hinterlegt die
Zugangsdaten zu den Paketdiensten DHL Paket, UPS und FedEx. Mit einer aktiven
Anbindung erzeugen Sie Versandlabels für Auslieferungen und Retourenlabels für
Rücksendungen direkt in WorkDiary. Je Paketdienst gibt es eine Anbindung pro
Organisation; Passwörter und Schlüssel werden verschlüsselt gespeichert.

## Voraussetzungen

- Ihre Lizenz enthält das Modul Versand & Logistik.
- Das Plugin des Paketdienstes ist unter **Plugins** aktiviert: **DHL Paket**,
  **UPS** oder **FedEx**. Danach erscheint im Systemmenü (Zahnrad-Symbol
  **System**) in der Gruppe **Plugins** der Eintrag **Versand**.
- Sie haben einen Geschäftskundenzugang beim Paketdienst:
  - **DHL:** Benutzer und Passwort des DHL-Geschäftskundenportals, einen bei
    DHL freigeschalteten API-Schlüssel (dhl-api-key) und die
    Abrechnungsnummer. Für Retourenlabels zusätzlich die
    Retourenempfänger-ID, die Sie im Geschäftskundenportal anlegen.
  - **UPS:** Client-ID und Client-Secret einer UPS-Entwickler-App sowie Ihre
    UPS-Kontonummer (Shipper-Nummer).
  - **FedEx:** Client-ID und Client-Secret einer FedEx-Entwickler-App sowie
    Ihre FedEx-Kontonummer.
- Für UPS und FedEx nimmt WorkDiary die Absenderadresse aus den Einstellungen
  der Organisation, Abschnitt **E-Rechnung (XRechnung)**: **Firmenname** (ohne
  Eintrag der Name der Organisation), **Straße und Hausnummer**, **PLZ** und
  **Ort**. Fehlt eine dieser Angaben, scheitert das Label.
- Die Seite steht Administratoren Ihrer Organisation offen.

## Anbindung anlegen oder ändern

Im Abschnitt **Anbindung anlegen / bearbeiten**:

1. **Carrier**: DHL, UPS oder FEDEX.
2. **Bezeichnung**: ein Name, unter dem die Anbindung später bei der
   Label-Erstellung zur Auswahl steht, etwa „DHL Versand Lager“.
3. **Benutzer / Client-ID** und **Passwort / Client-Secret**.
4. **API-Schlüssel (nur DHL: dhl-api-key)**.
5. **Retourenempfänger-ID (nur DHL)**: nötig für DHL-Retourenlabels.
6. **Abrechnungs-/Kontonummer**: bei DHL die Abrechnungsnummer, bei UPS die
   Shipper-Nummer, bei FedEx die Kontonummer.
7. **Sandbox / Testumgebung**: verbindet mit der Testumgebung des
   Paketdienstes; dort entstehen keine echten Sendungen.
8. **Aktiv** und **Speichern**.

Für eine neue Anbindung sind Benutzer/Client-ID und Passwort/Client-Secret
Pflicht, bei DHL zusätzlich der API-Schlüssel.

Zum Ändern speichern Sie das Formular erneut mit demselben Carrier; das
aktualisiert die bestehende Anbindung. Das Formular startet dabei immer leer:

- Leer gelassene Felder für Benutzer, Passwort, API-Schlüssel und
  Retourenempfänger-ID behalten den gespeicherten Wert.
- **Bezeichnung** und **Abrechnungs-/Kontonummer** tragen Sie jedes Mal neu
  ein – eine leere Abrechnungsnummer wird gelöscht.
- **Sandbox / Testumgebung** und **Aktiv** gelten so, wie sie beim Speichern
  gesetzt sind. Eine Sandbox-Anbindung wird also produktiv, wenn Sie den
  Haken nicht erneut setzen.

## Bestehende Anbindungen

Die Liste **Bestehende Anbindungen** zeigt je Anbindung den Carrier, die
Bezeichnung, den **Modus** (**Sandbox** oder **Produktiv**) und den Status
(**Aktiv** oder **Inaktiv**). **Deaktivieren** schaltet eine Anbindung ab; sie
steht dann nicht mehr zur Auswahl. Zum Reaktivieren speichern Sie sie mit
eingeschaltetem **Aktiv** erneut.

## Labels erzeugen

- **Versandlabel für Auslieferungen:** Unter **Fertigungsaufträge** finden Sie
  auf der Detailseite eines Auftrags den Abschnitt **Auslieferungen**. Bei einer
  Auslieferung mit Kunde wählen Sie die Anbindung, geben – falls keine
  Packstücke erfasst sind – das Gewicht in Gramm und optional Länge, Breite
  und Höhe in Zentimetern an und klicken auf **Versand**. UPS und FedEx nutzen
  die Maße nur, wenn alle drei angegeben sind. Erfasste Packstücke liefern
  Gewicht und Maße selbst. Empfänger ist der Kunde der Auslieferung. Danach
  zeigt die Auslieferung den Status **Label erstellt** mit Paketdienst und
  Sendungsnummer. Je Auslieferung gibt es einen Versandauftrag.
- **Retourenlabel für Rücksendungen:** In den **Reklamationsakten** wählen Sie
  bei einer Rücksendung im Status **Angekündigt** die Anbindung, geben das
  Gewicht an und klicken auf **Retourenlabel erstellen**. Absender ist der
  Kunde; seine Anschrift muss Straße, PLZ und Ort enthalten. Über **Label
  herunterladen** erhalten Sie die Datei; sind Rücksendungen für den Kunden
  im Kundenportal freigegeben, kann auch er das Label dort abrufen.
- **Berechtigung:** Versandlabels erzeugt, wer den Fertigungsauftrag bearbeiten
  darf; Retourenlabels, wer das Recht **Rückläufer prüfen und einlagern**
  hat.

UPS liefert das Label als Bild (GIF), FedEx als PDF. Lehnt der Paketdienst
den Auftrag ab, verwirft WorkDiary den Entwurf, und Sie können es nach der
Korrektur erneut versuchen.

## Grenzen

- Je Paketdienst eine Anbindung pro Organisation.
- DHL-Sendungen laufen standardmäßig als DHL Paket national; ein anderes
  Produkt stellt nur der Betreiber der Installation ein.
- Zollpapiere für Sendungen außerhalb der EU erstellen Sie getrennt an der
  Auslieferung (siehe Hilfe zu Fertigungsaufträgen).

## Typische Fehlerbilder

- **„Für eine neue Anbindung sind Benutzer/Client-ID und
  Passwort/Client-Secret erforderlich (DHL zusätzlich: API-Schlüssel).“**
  Ergänzen Sie die fehlenden Zugangsdaten.
- **Keine Anbindung zur Auswahl:** Es gibt keine aktive Anbindung, oder die
  Auslieferung hat keinen Kunden bzw. bereits einen Versandauftrag.
- **„Für den gewählten Carrier ist keine aktive Anbindung hinterlegt.“** Die
  Anbindung wurde inzwischen deaktiviert.
- **„Versandlabel konnte nicht erstellt werden: …“** Prüfen Sie Zugangsdaten,
  Abrechnungs- bzw. Kontonummer, den Schalter **Sandbox / Testumgebung** und
  die Anschrift des Empfängers. Bei UPS und FedEx fehlt oft die
  Absenderadresse in den Einstellungen der Organisation; bei
  DHL-Retourenlabels die Retourenempfänger-ID.
- **„Für das Retourenlabel fehlt die Anschrift des Kunden (Straße, PLZ,
  Ort).“** Ergänzen Sie die Anschrift im Kundendatensatz.
- **Zugangsdaten prüfen:** Den Health-Check des Paketdienstes führen Sie unter
  **Plugins** aus. Scheitert eine Anbindung, meldet WorkDiary die gestörte
  Verbindung unter **Betriebsaufgaben**.
