---
title: "Angebote"
topic: quotes.overview
version: 6
keywords:
    - Angebot erstellen
    - Kostenvoranschlag
    - KVA
    - Offerte
    - Angebot versenden
    - Online-Annahme
    - Teilannahme
    - Bindefrist
    - Auftragsbestätigung
    - Angebot in Rechnung umwandeln
    - optionale Positionen
    - Angebotsversion
    - Angebot als PDF
    - zugehörige Belege
    - Belegkette
    - Angebotsnummer
    - Angebot an Lexoffice
    - AB-Nummer
    - Lieferschein
    - Teillieferung
    - Lieferscheinnummer
audience: []
modules:
    - module.vertrieb
related:
    - invoices.manage
---

Angebote durchlaufen einen festen Lebenszyklus: Entwurf → Freigabe →
Versand → Kundenentscheidung → Überführung in eine Rechnung. Die Übersicht
filtert nach Kunde und Status (Entwurf, freigegeben, versendet, angenommen,
teilweise angenommen, abgelehnt, abgelaufen).

**Entwurf:** Ein Angebot wird mit Kunde, optionalem Projekt, Bindefrist und
Konditionstext angelegt. Positionen (Beschreibung, Menge, Einheit,
Einzelpreis, Steuersatz) lassen sich nur im Entwurf hinzufügen, ändern und
entfernen; die Summen werden dabei automatisch neu berechnet. Einzelne
Positionen können als optional gekennzeichnet werden — lehnt der Kunde nur
diese ab, bleibt es trotzdem eine Vollannahme.

**Freigabe & Versand:** Nach der Freigabe wird das Angebot als versendet
markiert. Dabei entsteht ein Annahme-Link für den Kunden: Er wird genau
einmal im Klartext angezeigt, gespeichert wird nur ein Prüfwert — der Link
sollte also sofort kopiert und mit der Angebotsnachricht (E-Mail oder
Brief) verschickt werden. Ab dem Versand ist der Stand fachlich
unveränderlich; Änderungen laufen ausschließlich über eine neue Version,
die auf den Vorgänger verweist. Die gesamte Versionskette bleibt am
Angebot sichtbar.

**Kundenentscheidung:** Über den Link kann der Kunde ohne Anmeldung das
Angebot einsehen und annehmen, teilweise annehmen (Auswahl einzelner
Positionen) oder ablehnen. Alternativ dokumentiert der Bearbeiter eine
telefonisch oder schriftlich mitgeteilte Entscheidung intern, bei
Ablehnung mit optionalem Grund. Nach Ablauf der Bindefrist ist keine
Annahme mehr möglich; abgelaufene, abgelehnte oder versendete Angebote
lassen sich in eine neue Version überführen und erneut anbieten.

**Rechnung:** Angenommene oder teilweise angenommene Angebote werden per
Klick in eine Entwurfsrechnung überführt. Übernommen werden ausschließlich
die angenommenen Positionen; die Rechnung durchläuft anschließend den
normalen Rechnungsprozess (Prüfen, Ausstellen, Versand). Am Angebot
bleiben die daraus entstandenen Rechnungen verlinkt, sodass der Weg vom
Angebot bis zum Beleg nachvollziehbar ist.

Angebots-Entwürfe ohne Versand können gelöscht werden; alles ab Versand
bleibt als Historie erhalten.

**Im Buchhaltungsprogramm abgerechnet:** Wird ein angenommenes Angebot nicht
hier, sondern im Buchhaltungsprogramm in Rechnung gestellt, markieren Sie es
über das Menü der Angebotsseite mit **Im Buchhaltungsprogramm abgerechnet**,
wahlweise mit der dortigen Rechnungsnummer. Das Angebot verlässt damit die
Liste **Angenommene Angebote ohne Rechnung**; die Markierung lässt sich
wieder aufheben. Die Aktion steht bereit, solange es zum Angebot keine
Rechnung gibt.

**PDF:** Jedes Angebot lässt sich als PDF herunterladen (inklusive
Optionen-Ausweis und Steueraufriss); versendete Angebote behalten dauerhaft
das Dokumentdesign des Versandzeitpunkts. Die Auftragsbestätigung ist ein
eigener Beleg (siehe unten).

## Zugehörige Belege

Unten auf der Seite listet **Zugehörige Belege** alle Belege, die mit
diesem zusammenhängen: Angebot und seine Versionen, Rechnungen, Abschläge und
Schlussrechnung, Gutschriften und Stornos, Lieferscheine, Fertigungsaufträge
und Übergaben. Hat ein Buchhaltungsprogramm einen Beleg übernommen, steht
sein Gegenstück dort mit der Nummer des Programms. Bezüge, die das Programm
selbst meldet, etwa ein in Lexoffice angelegter Abschlag, erscheinen ebenfalls.
Die Spalte **Bezug** sagt, was der Beleg für diesen ist; „über …“ nennt den
Beleg, über den der Zusammenhang besteht.

## Angebotsnummer vom Buchhaltungsprogramm

Stellen Sie auf der Seite **Nummernkreise** für **Angebot** ein
Buchhaltungsprogramm ein (Lexoffice oder easybill), trägt ein neues Angebot
zunächst eine Entwurfsnummer. Beim **Versand** legt WorkDiary das Angebot im
Programm an, schreibt es dort fest und übernimmt dessen Nummer; erst danach
entsteht das PDF. Scheitert die Übertragung, bleibt das Angebot freigegeben,
und Sie können den Versand wiederholen. Eine neue Version ist im Programm ein
neues Angebot mit eigener Nummer. Optionale Positionen kennzeichnet Lexoffice
als optional; easybill führt sie ohne Berechnung.

## Auftragsbestätigung

Zu einem angenommenen Angebot legt **Auftragsbestätigung erstellen** einen
eigenen Beleg mit den angenommenen Positionen an. Die Auftragsbestätigung hat
einen eigenen Nummernkreis (**Nummernkreise**, Kreis „Auftragsbestätigung“).
Mit **Bestätigen** erhält sie ihre Nummer; vergibt ein Buchhaltungsprogramm
(Lexoffice oder easybill) die Nummer, überträgt WorkDiary sie dabei. Danach
lassen sich PDF und E-Mail-Versand nutzen. Stornieren nimmt sie in WorkDiary
zurück; im Programm bleibt sie bestehen.

## Lieferschein

Eine bestätigte Auftragsbestätigung bietet **Lieferschein erstellen**. Der
Lieferschein übernimmt die noch nicht gelieferten Mengen ohne Preise. Für eine
Teillieferung passen Sie unter **Mengen bearbeiten** die Mengen an; 0 streicht
eine Position aus diesem Lieferschein, sie bleibt für den nächsten offen.
**Ausliefern** legt die Nummer aus dem Kreis „Lieferschein“ fest; vergibt ein
Buchhaltungsprogramm (Lexoffice oder easybill) die Nummer, überträgt WorkDiary
den Lieferschein dabei. Danach stehen PDF und E-Mail-Versand bereit. Lieferscheine
aus der Fertigung entstehen mit jeder Auslieferung (siehe Fertigungsaufträge).
