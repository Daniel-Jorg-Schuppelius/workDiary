---
title: "Versandanbindungen DHL, UPS und FedEx"
topic: admin.shipping-carriers
version: 2
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
    - Sendungsverfolgung
    - Versand stornieren
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
Rücksendungen direkt in WorkDiary und verfolgen den Weg Ihrer Sendungen. Je Paketdienst gibt es eine Anbindung pro
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

Eine neue Anbindung legen Sie im Formular **Anbindung anlegen** an:

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
Pflicht, bei DHL zusätzlich der API-Schlüssel. Wählen Sie in diesem Formular
einen Carrier, für den schon eine Anbindung besteht, lehnt WorkDiary das
Speichern mit einem Hinweis ab – bestehende Anbindungen ändern Sie nur über
**Bearbeiten**.

Zum Ändern klicken Sie in der Liste **Bestehende Anbindungen** bei der
Anbindung auf **Bearbeiten**. Das Formular heißt dann **Anbindung … bearbeiten**
mit dem Carrier im Titel, etwa „Anbindung DHL bearbeiten“; der Carrier lässt
sich nicht ändern.

- **Bezeichnung**, **Abrechnungs-/Kontonummer**, **Sandbox / Testumgebung** und
  **Aktiv** sind mit den gespeicherten Werten vorbelegt. Was Sie hier ändern,
  gilt nach dem Speichern.
- Benutzer, Passwort, API-Schlüssel und Retourenempfänger-ID werden nie
  angezeigt. Leer gelassene Felder behalten den gespeicherten Wert; nur ein
  neuer Eintrag ersetzt ihn.
- Auch eine leer gelassene **Abrechnungs-/Kontonummer** behält den gespeicherten
  Wert.
- **Abbrechen** verlässt das Bearbeiten, ohne zu speichern.

## Bestehende Anbindungen

Die Liste **Bestehende Anbindungen** zeigt je Anbindung den Carrier, die
Bezeichnung, den **Modus** (**Sandbox** oder **Produktiv**) und den Status
(**Aktiv** oder **Inaktiv**). **Bearbeiten** öffnet die Anbindung im Formular.
**Deaktivieren** schaltet eine Anbindung ab; sie steht dann nicht mehr zur
Auswahl, und Sendungen dieses Carriers werden nicht mehr abgeglichen. Zum
Reaktivieren öffnen Sie sie mit **Bearbeiten**, setzen **Aktiv** und speichern.

## Labels erzeugen

- **Versandlabel für Auslieferungen:** Unter **Fertigungsaufträge** finden Sie
  auf der Detailseite eines Auftrags den Abschnitt **Auslieferungen**. Bei einer
  Auslieferung mit Kunde wählen Sie die Anbindung, geben – falls keine
  Packstücke erfasst sind – das Gewicht in Gramm und optional Länge, Breite
  und Höhe in Zentimetern an und klicken auf **Versand**. UPS und FedEx nutzen
  die Maße nur, wenn alle drei angegeben sind. Erfasste Packstücke liefern
  Gewicht und Maße selbst. Empfänger ist der Kunde der Auslieferung. Danach
  zeigt die Auslieferung den Status **Label erstellt** mit Paketdienst und
  Sendungsnummer. Je Auslieferung gibt es einen Versandauftrag; ein
  stornierter zählt nicht. An der Auslieferung laden Sie das Label mit **Label
  herunterladen** erneut herunter, fragen mit **Sendungsstatus abrufen** den
  aktuellen Stand ab und stornieren mit **Versand stornieren** – Einzelheiten
  in der Hilfe zu Fertigungsaufträgen.
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

## Sendungsverfolgung

Offene Sendungen – Status **Label erstellt**, **Unterwegs** oder
**Zustellproblem** – gleicht WorkDiary in der Standardeinstellung stündlich beim
Paketdienst ab. Jede Sendung wird dabei höchstens alle drei Stunden abgefragt
und nur bis 60 Tage nach ihrer Anlage; danach gilt sie nicht mehr als
verfolgbar. Der Abgleich übernimmt Status und Sendungsverlauf, bis die Sendung
**Zugestellt** ist.

- Wechselt eine Sendung auf **Zustellproblem**, löst WorkDiary die
  Benachrichtigung **Zustellproblem bei einer Sendung** aus.
- Den Zeitpunkt des letzten Abgleichs zeigt der Status an der Auslieferung beim
  Überfahren mit der Maus (**Zuletzt abgeglichen: …**).
- Abgeglichen wird nur über eine aktive Anbindung. Scheitert der Abruf beim
  Paketdienst, zählt das wie andere Verbindungsfehler für die Anbindung (siehe
  „Typische Fehlerbilder“).

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
- **„Für diesen Carrier besteht bereits eine Anbindung. Bitte ändern Sie sie
  über „Bearbeiten“.“** Sie haben im Formular **Anbindung anlegen** einen
  bereits angebundenen Carrier gewählt. Öffnen Sie die Anbindung in der Liste
  mit **Bearbeiten**.
- **„Für den gewählten Carrier ist keine aktive Anbindung hinterlegt.“** Die
  Anbindung wurde inzwischen deaktiviert.
- **„Versandauftrag konnte nicht storniert werden: …“** oder
  **„Sendungsstatus konnte nicht abgerufen werden: …“** Der Paketdienst hat die
  Anfrage abgelehnt oder war nicht erreichbar, oder die Anbindung ist inaktiv.
  Ist die Sendung bereits unterwegs, ist kein Storno mehr möglich.
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
