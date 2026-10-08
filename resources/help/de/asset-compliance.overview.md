---
title: "Prüfmittel & Kalibrierung"
topic: asset-compliance.overview
version: 2
keywords:
    - Prüfmittelverwaltung
    - Eichung
    - Messmittel
    - DGUV V3
    - UVV-Prüfung
    - E-Check
    - Hauptuntersuchung
    - TÜV
    - Prüffristen
    - Prüfprotokoll
    - Kalibrierzertifikat
    - Gerät sperren
audience: []
modules:
    - module.asset_compliance
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

## Prüfkalender

Menü **Prüfmittel** → **Prüfkalender**; die Seite ist auch als Reiter auf den
anderen Prüfmittel-Seiten erreichbar. Die Liste zeigt die offenen
Prüftermine (**Geplant**, **Angekündigt**, **In Durchführung**) nach
Fälligkeit sortiert. Über den Statusfilter sehen Sie auch durchgeführte,
versäumte oder stornierte Termine. Spalten: **Fällig** (mit geplantem Datum,
falls vorhanden), **Asset**, **Prüfprofil**, **Prüfer / Prüfstelle** und
**Status**.

**Prüftermin planen** (unten auf der Seite): **Prüfpflicht** wählen – die
Auswahl zeigt Asset, Profil und nächste Fälligkeit –, **Fällig am** (Pflicht),
optional **Geplant am**, **Interner Prüfer** oder **Externe Prüfstelle**, dann
**Termin planen**. Ein neuer Termin beginnt als **Geplant**. Bei Terminen mit
externer Prüfstelle lädt **Zugang einladen** die Prüfstelle über einen
befristeten Zugang ein.

**Prüfung erfassen** steht bei offenen Terminen bereit und öffnet das
Prüfprotokoll:

- **Ergebnis**: **Bestanden**, **Bestanden mit Einschränkungen** oder
  **Nicht bestanden**.
- **Durchgeführt am (leer = jetzt)** und **Gültig bis (leer = Intervall)**:
  Ohne Angabe gilt der Nachweis ein Prüfintervall ab der Durchführung. Eine
  nicht bestandene Prüfung erhält kein Gültig-bis.
- Ein Messwert je Anforderung des Profils; die Grenzwerte stehen daneben.
- **Folgeentscheidung**: **Keine / Freigabe**, **Nachkalibrierung (sperrt)**,
  **Reparatur (sperrt)**, **Eingeschränkte Nutzung** (wird nur vermerkt,
  sperrt nicht), **Sperre**, **Aussonderung** (sperrt ebenfalls) oder
  **Reklamation eröffnen** (legt eine Reklamation an, wenn Ihre Organisation
  das Modul Reklamation & Gewährleistung nutzt), dazu **Begründung der
  Maßnahme**.
- **Zertifikat / Prüfnachweis** (aufklappbar): Zertifikatsnummer, Aussteller
  (Pflicht, sobald eine Nummer eingetragen ist), Ausstellungsdatum,
  Gültigkeit, Messbereich, Toleranz und ein Dokument, von dem ein Hash
  gespeichert wird.
- **Unterschrift (Name)**, **Prüfkosten (netto, €)** und **Bemerkung**.

**Prüfung dokumentieren** legt einen unveränderbaren Nachweis an und schließt
den Termin als **Durchgeführt** ab. Eine bestandene Prüfung setzt die nächste
Fälligkeit auf ein Prüfintervall nach der Durchführung. Bestanden ohne
Folgemaßnahme hebt Sperren wegen überfälliger oder nicht bestandener Prüfung
auf; „Nicht bestanden“ ohne gewählte Maßnahme sperrt das Asset. Verlangt das
Profil ein Zertifikat, wird eine bestandene Prüfung ohne Zertifikatsnummer
abgelehnt.

**Berechtigung:** Ansehen mit **Prüfpflichten und Prüfmittel auflisten**;
Termine planen mit **Prüfprofile und Prüfpflichten pflegen**; Prüfungen
erfassen mit **Prüfungen durchführen und Nachweise erfassen**. **Zugang
einladen** erfordert eines der beiden letztgenannten Rechte.

## Prüfaufträge

Menü **Prüfmittel** → **Prüfaufträge**. Hier vergeben Sie fällige Prüfungen
an einen Prüfdienstleister; er braucht dafür kein Benutzerkonto. Die Liste
zeigt **Bezeichnung**, **Prüfdienstleister**, die Zahl der **Prüfmittel**,
**Status** und **Angebotspreis**; **Öffnen** führt zum Auftrag.

So läuft ein Auftrag ab:

1. **Prüfauftrag anlegen**: **Bezeichnung**, **Prüfdienstleister** (Auswahl
   aus Ihren Lieferanten), **E-Mail des Dienstleisters** und mindestens einen
   Prüftermin im Status **Geplant** oder **Angekündigt** wählen, dann
   **Auftrag senden**. Der Dienstleister erhält eine E-Mail mit einem Link,
   der 90 Tage gilt. Die gewählten Termine wechseln auf **Angekündigt**, der
   Auftrag steht auf **Angefragt**.
2. Über den Link gibt der Dienstleister ein Angebot mit Preis, geplantem
   Termin und Anmerkung ab; der Auftrag steht dann auf **Angebot liegt vor**.
   In der Auftragsansicht wählen Sie **Angebot annehmen** (Status
   **Beauftragt**) oder **Angebot ablehnen** (zurück auf **Angefragt**; der
   Dienstleister kann ein neues Angebot abgeben).
3. Nach der Beauftragung meldet der Dienstleister je Prüfmittel Ergebnis,
   Prüfdatum, Gültigkeit, Zertifikatsnummer und Bemerkung, optional mit dem
   Zertifikat als Datei. Der Auftrag steht dann auf **Ergebnisse gemeldet**.
4. **Ergebnisse übernehmen** legt für jedes gemeldete Prüfmittel einen
   Prüfnachweis an – wie eine Prüfung im Prüfkalender, mit dem Dienstleister
   als Prüfer und Aussteller und dem Zertifikat samt Prüfsumme. Der Auftrag
   ist danach **Abgeschlossen**; in der Tabelle steht bei den Prüfmitteln
   „übernommen“.

**Auftrag stornieren** ist möglich, solange noch keine Ergebnisse gemeldet
sind; die angekündigten Termine werden wieder **Geplant**. Verlangt ein
Profil ein Zertifikat und fehlt bei einem bestandenen Ergebnis die
Zertifikatsnummer, bricht die Übernahme mit einer Meldung ab.

**Berechtigung:** Liste und Auftrag ansehen mit **Prüfpflichten und
Prüfmittel auflisten**; anlegen, über das Angebot entscheiden und stornieren
mit **Prüfprofile und Prüfpflichten pflegen**; Ergebnisse übernehmen mit
**Prüfungen durchführen und Nachweise erfassen**.

## Prüfrunden

Reiter **Prüfrunden**, zum Beispiel im **Prüfkalender**. Eine Prüfrunde ist
eine Soll-Liste fälliger Prüfungen eines Standorts oder einer Gruppe, die
Sie vor Ort per Scan abarbeiten. Die Liste zeigt **Bezeichnung**, **Fällig
bis**, **Erledigt** (erledigte von allen Prüfungen) und **Status** (**Offen**
oder **Abgeschlossen**).

**Runde anlegen**: **Bezeichnung**, **Fällig bis** (vorbelegt mit heute in
30 Tagen) und optional **Standort**, **Gruppe (Kategorie)**, **Prüfprofil**
und **Kunde**. Die Runde übernimmt alle aktiven Prüfpflichten, die bis zu
diesem Datum fällig sind; ausgesonderte Assets bleiben außen vor. Später
fällig werdende Pflichten kommen nicht hinzu. Ist für die Auswahl nichts
fällig, wird keine Runde angelegt.

In der Runde sehen Sie die Kennzahlen **Erledigt**, **Fehlend** und
**Überfällig** und alle Positionen mit Asset, Prüfprofil, Fälligkeit und
Stand (**Offen**, **Überfällig** oder das erfasste Ergebnis).

- **Objekt scannen**: QR-Code, Anlagen-Nr., Inventar-Nr. oder Seriennummer
  eingeben oder scannen und **Öffnen**. Auf Geräten mit NFC-Unterstützung
  erscheint zusätzlich **NFC-Tag lesen**. Ist für das Objekt genau eine
  Prüfung offen, öffnet sich die Erfassung; sind es mehrere, wählen Sie das
  Prüfprofil aus.
- **Prüfung erfassen** (Schnellerfassung): **Ergebnis**, **Bemerkung** und
  **Unterschrift (Name)** (vorbelegt mit Ihrem Namen), dann **Prüfung
  speichern**. Das erzeugt denselben unveränderbaren Nachweis wie im
  Prüfkalender, nur ohne Messwerte und Zertifikat. „Nicht bestanden“ sperrt
  das Asset. Verlangt das Profil ein Zertifikat, erfassen Sie eine bestandene
  Prüfung im Prüfkalender – die Erfassung weist darauf hin.
- **Runde abschließen**: Noch offene Prüfungen bleiben als fehlend stehen;
  danach ist in der Runde keine Erfassung mehr möglich.

**Berechtigung:** Ansehen mit **Prüfpflichten und Prüfmittel auflisten**;
Runden anlegen, scannen, erfassen und abschließen mit **Prüfungen
durchführen und Nachweise erfassen**.

## Prüfertour

Im **Prüfkalender** über die Schaltfläche **Prüfertour**. Damit planen Sie
die offenen Prüftermine eines internen Prüfers als Tour.

- Oben wählen Sie den **Prüfer** (vorbelegt: Sie selbst) und **Fällig bis**
  (vorbelegt: heute in 14 Tagen).
- Die Tabelle zeigt die offenen Termine, bei denen diese Person als interner
  Prüfer eingetragen ist und die noch keinem Auftrag zugeordnet sind, mit
  Fälligkeit, Asset, Prüfprofil und **Standort**. Alle Termine sind
  vorausgewählt.
- Mit **Tourdatum** (vorbelegt: morgen) und **Tour planen** wird jeder
  gewählte Termin zu einem Auftrag für den Prüfer am Gerätestandort. Der
  Termin erhält das Tourdatum als geplantes Datum, die Tourenplanung legt die
  Tour an und optimiert die Reihenfolge; anschließend öffnet sich die Tour.
- Geräte mit dem Vermerk **ohne Koordinaten** bleiben in der Tour, fließen
  aber nicht in die Routenberechnung ein.
- Prüfertouren setzen das Modul Planung voraus. Ohne dieses Modul zeigt die
  Seite einen Hinweis und keine Schaltfläche zum Planen.

**Berechtigung:** **Prüfprofile und Prüfpflichten pflegen**.

## Auditbericht

Menü **Prüfmittel** → **Auditbericht** (Seitentitel **Auditbericht
Prüfwesen**). Den Zeitraum wählen Sie in der Filterleiste der Seite;
vorgegeben sind die letzten drei Monate.

- Aktueller Stand, unabhängig vom Zeitraum: **Prüfpflichten** (aktive
  Pflichten), **Überfällig** (Fälligkeit samt Toleranz überschritten),
  **Bald fällig** (innerhalb der Vorwarnzeit des Profils) und **Gesperrt
  (Prüfwesen)** (aktive Sperren wegen überfälliger oder nicht bestandener
  Prüfung).
- Bezogen auf den Zeitraum: **Prüfungen im Zeitraum**, **Nicht bestanden**,
  **Prüfquote** (Anteil der bestandenen Prüfungen, auch mit Einschränkungen),
  **Zertifikate**, **Prüfkosten im Zeitraum** und die Kosten von bis zu drei
  Prüfarten.
- Tabellen: **Prüfpflichten nach Prüfart**, **Prüfungen nach Prüfer (Top
  10)** und **Abweichungen (nicht bestanden)** mit Asset, Zeitpunkt und
  Bemerkung.

**Snapshot einfrieren** speichert die Kennzahlen des gewählten Zeitraums
unveränderlich. Die letzten zehn Snapshots stehen unter **Eingefrorene
Snapshots (P2)** mit Zeitraum, Erstellzeitpunkt, Zahl der überfälligen
Pflichten und Prüfquote.

**Berechtigung:** **Prüfpflichten und Prüfmittel auflisten** – das gilt auch
für das Einfrieren eines Snapshots.
