---
title: "Betrieb: Zeitaufteilung, Prozeduren, Material, Notdienst"
topic: reports.operations
version: 6
keywords:
    - Betriebsauswertung
    - Service-Aufträge
    - Kostenstellen-Auswertung
    - Prozedur-Abweichungen
    - blockierte Prozedurläufe
    - Materialverbrauch
    - Bereitschaftsdienst
    - Defektrate
    - Projektstunden
    - Projekte archivieren
    - Pflichtklassifikation fehlt
    - Tourenauswertung
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
    - buchhaltung
    - user
    - aussendienst
related:
    - reports.overview
    - reports.drilldown
    - procedures.run
    - materials.manage
    - duties.overview
    - projects.manage
    - admin.time-dimensions
    - admin.classifications
---

Diese Auswertungen zeigen, was im Betrieb geschieht: Service-Aufträge,
Tasks und Touren, die Aufteilung der Arbeitszeit auf Projekte,
Kostenstellen und weitere Dimensionen, Abweichungen und Sperren in
Prozeduren, verbrauchtes Material, Notdienste, Defekte an Produkten sowie
Stunden und Ruhephasen einzelner Projekte. Die meisten Seiten finden Sie
unter **Auswertungen** → **Projekte & Kunden** und **Auswertungen** →
**Ressourcen**.

## Zeitraum, Filter und Export

- Den Zeitraum wählen Sie über die Zeitraumwahl in der Kopfzeile. Die
  Filterleiste zeigt ihn nur als Hinweis an; Abweichungen davon stehen beim
  jeweiligen Bericht.
- Filter wirken sofort nach der Auswahl. Der Schalter **Ausgeblendete Kunden
  einbeziehen** erscheint nur, wenn Kunden mit **In Auswertungen ausblenden**
  markiert sind; ohne ihn bleiben deren Daten außen vor.
- Einige Berichte haben das Feld **Bereich**. Es erscheint nur für
  Administratoren, die damit zwischen den eigenen Daten und dem gesamten Team
  wechseln. Alle anderen sehen dort immer ihre eigenen Daten.
- **PDF** lädt eine Druckfassung, unter **Export** stehen **CSV** und
  **Excel** bereit. Exporte übernehmen die gesetzten Filter. Jeder Export
  wird im Audit-Protokoll vermerkt.
- Exporte setzen das Recht **Auswertungen exportieren** voraus, auch bei
  **Blockierte Prozedurläufe**; ohne das Recht fehlen die Exportknöpfe.
  Administratoren dürfen immer exportieren. Frei bleiben Exporte, die nur Ihre
  eigenen Daten enthalten – siehe „Auswertungen verwenden“.

## Operations

**Auswertungen** → **Projekte & Kunden** → **Operations** öffnet die
**Operations-Auswertung**: Service-Aufträge (Aufträge des Auftragstyps
Service), Tasks und Touren im Zeitraum.

- Kacheln: **Service-Aufträge** mit der Abschlussquote (**Abschluss**),
  **Servicezeit Σ**, **Tasks** mit der Zahl der überfälligen Tasks und ihrer
  Abschlussquote (die Kachel färbt sich, sobald ein Task überfällig ist) sowie
  **Touren** mit Plan-Kilometern und Plan-Dauer.
- Diagramme: **Service-Aufträge: erstellt vs. erledigt je Woche** und
  **Backlog je Kunde (Top 15)** mit den noch offenen Service-Aufträgen je
  Kunde. Mit dem Recht **Auswertungen einsehen** öffnet ein Klick auf einen
  Balken die offenen Punkte des Kunden; ohne dieses Recht sind die Balken
  nicht anklickbar.
- Tabellen: **Service-Aufträge – Status**, **Service-Aufträge – Priorität**,
  **Tasks – Status**, **Tasks – Priorität** und **Touren – pro Mitarbeiter**
  (Touren, **Plan-km**, **Plan-Dauer**).

Die Service-Aufträge sind in vier Gruppen zusammengefasst: **Offen** (geplant
oder angenommen), **In Arbeit**, **Problem** (wartet auf Rückmeldung oder
Material) und **Erledigt** (abgeschlossen, abgenommen oder berechnet).
Stornierte Aufträge zählen zur Gesamtzahl, aber weder zu einer Gruppe noch
zur Abschlussquote. Maßgeblich ist der geplante Termin des Auftrags. Tasks
zählen, wenn sie im Zeitraum angelegt, geändert oder fällig wurden;
archivierte Tasks bleiben außen vor.

Filter: **Bereich**, **Kunde**, **Projekt**, **Mitarbeiter**,
**Auftragsstatus** und **Ausgeblendete Kunden einbeziehen**. Kunde und
Projekt wirken auf Service-Aufträge und Tasks, der Mitarbeiter auf alle drei
Bereiche; Touren kennen weder Kunde noch Projekt. Der **Auftragsstatus**
grenzt nur die Kacheln und Tabellen der Service-Aufträge ein.

Ohne Administratorrechte sehen Sie Ihnen zugewiesene Service-Aufträge, Tasks,
die Ihnen zugewiesen sind oder die Sie angelegt haben, und Ihre eigenen
Touren. Export als PDF, CSV und Excel.

## Zeitaufteilung

**Auswertungen** → **Projekte & Kunden** → **Zeitaufteilung** öffnet die
Seite **Zeitaufteilung nach Dimension**. Sie zeigt, wie aufgeteilte
Zeiteinträge des Zeitraums auf **Aufgaben**, **Assets**, **Projekte**,
**Kostenstellen**, **Standorte**, **Fahrzeuge**, **Tätigkeiten** und die
freien Dimensionen aus **Zeit-Dimensionen** verteilt sind.

- Kacheln: **Aufgeteilte Zeit** und die Zahl der **Dimensionen**.
- Je Dimension eine Karte mit **Ziel**, **Minuten** (als Stunden und
  Minuten) und **Einträge** (Zahl der Zeiteinträge), absteigend nach Zeit.
- Datenbasis sind ausschließlich die Aufteilungs-Anteile. Nicht aufgeteilte
  Zeit erscheint in den übrigen Zeitauswertungen.

Die Seite zeigt die ganze Organisation und hat keine weiteren Filter. Sie
steht im Menü für Administratoren und für Rollen mit dem Recht
**Auswertungen einsehen**. Export als PDF, CSV und Excel.

## Prozedur-Abweichungen

**Auswertungen** → **Projekte & Kunden** → **Prozedur-Abweichungen** wertet
die Abweichungen aus, die bei der Ausführung von Prozeduren im Zeitraum
erfasst wurden. Der Menüpunkt erscheint mit dem Recht **Prozedur-Abweichungen
einsehen**.

- Kacheln: **Abweichungen**, **Kritisch**, **Quote mit Folgemaßnahme**
  (Anteil mit offenem Punkt oder Folgeauftrag) und **Ø Stunden bis
  Entscheidung** (von der Anlage bis zur Risikoakzeptanz, nur entschiedene
  Abweichungen).
- Diagramme: **Abweichungen je Typ**, die Abweichungen im Verlauf nach
  Schweregrad und **Prozeduren mit den meisten Abweichungen (Top 10)**.
- Liste: **Datum**, **Prozedur**, **Schritt**, **Typ**, **Schweregrad**,
  **Folgemaßnahme** (**Offener Punkt** oder **Folgeauftrag**), **Risiko
  akzeptiert am** und **Std. bis Entscheidung**. Das Symbol am Zeilenende
  öffnet den Prozedurlauf.

Filter: **Prozedur**, **Typ**, **Schweregrad**, **Risiko akzeptiert**
(**Nur akzeptiert** oder **Nur offen**) und **Folgemaßnahme** (**Mit
Folge-Punkt/-Auftrag** oder **Ohne Folgemaßnahme**). Export als PDF, CSV und
Excel; CSV und Excel enthalten zusätzlich die vorgeschlagene Aktion und die
Begründung.

## Blockierte Prozedurläufe

**Auswertungen** → **Projekte & Kunden** → **Blockierte Prozedurläufe** zeigt
Läufe, die auf eine Wartezeit, eine zweite Person oder eine
Risikoentscheidung warten. Der Menüpunkt erscheint mit dem Recht
**Prozedurläufe einsehen**.

- **Aktuell blockiert**: **Prozedur**, **Sperrgrund**, **Blockiert seit** und
  **Stunden**, dazu ein Sprung in den Lauf. Sperrgründe sind eine kritische
  Abweichung ohne Risikoentscheidung, eine noch nicht abgelaufene Wartezeit
  und eine fehlende zweite Person. Beim Öffnen der Seite werden abgelaufene
  Wartezeiten freigegeben.
- **Beendete Sperren im Zeitraum**: je Sperrgrund und Prozedur **Anzahl**,
  **Ø Stunden** und **Längste (Std.)**.

Den Zeitraum stellen Sie hier im Von-bis-Feld der Filterleiste ein; ohne
eigene Angabe gilt die Zeitraumwahl der Kopfzeile. Export als CSV und Excel;
er enthält die beendeten Sperren.

## Materialien

**Auswertungen** → **Ressourcen** → **Materialien** öffnet die Seite
**Materialverbrauch**. Grundlage sind die Materialpositionen auf
Stundenzetteln, deren Arbeitstag im Zeitraum liegt.

- Diagramme: **Verbrauchswert je Material (Top 20)** und die Materialkosten
  im Verlauf.
- Kacheln: **Materialien**, **Verwendungen** und **Netto Σ**.
- Tabelle **Verbrauch pro Material**: **SKU**, **Material**, **Einheit**,
  **Menge**, **Verwendungen** und **Netto**, absteigend nach Nettobetrag.
  Dasselbe Material in verschiedenen Einheiten steht in getrennten Zeilen;
  Positionen ohne Materialstamm erscheinen unter ihrer Beschreibung.

Filter: **Bereich**, **Kunde**, **Projekt** und **Ausgeblendete Kunden
einbeziehen**; der Kunde wirkt über das Projekt des Stundenzettels. Ohne
Administratorrechte sehen Sie nur Ihre eigenen Stundenzettel. Export als PDF,
CSV und Excel.

## Notdienst

**Auswertungen** → **Ressourcen** → **Notdienst** öffnet die
**Notdienst-Auswertung** mit Bereitschaftsschichten und tatsächlichen
Einsätzen je Mitarbeiter, wie sie in der **Arbeitsliste** gepflegt werden.
Zeiten, die über den Zeitraum hinausreichen, zählen nur anteilig;
archivierte Einträge zählen nicht.

- Kacheln: **Mitarbeiter**, **Bereitschaft** (mit Zahl der Schichten),
  **Aktiv-Einsätze** (Einsatzzeit mit Zahl der Einsätze) und **Aktiv-Anteil**
  (Einsatzzeit im Verhältnis zur Bereitschaftszeit).
- Diagramme: **Bereitschaft je Mitarbeiter und Woche** als Wärmekarte und
  die Einsätze im Verlauf.
- Tabelle je Mitarbeiter: **Schichten**, **Bereitschaft**, **Einsätze**,
  **Einsatzzeit** und **Aktiv-Anteil** mit Summenzeile.

Filter: **Bereich** (**Nur meine Bereitschaft** oder **Gesamtes Team**, nur
für Administratoren), **Mitarbeiter** und **Team**. Export als PDF (mit der
Wärmekarte), CSV und Excel.

## Produktanalyse

**Auswertungen** → **Projekte & Kunden** → **Produktanalyse** zeigt Defekte,
offene Punkte und Aufwand je Asset, Produktgruppe oder Modell. Der Menüpunkt
erscheint für Administratoren und mit dem Recht **Auswertungen einsehen**.

- **Ebene**: **Pro Asset**, **Pro Produktgruppe** oder **Pro Modell**;
  weitere Filter sind **Produktgruppe**, **Hersteller**, **Kunde** und
  **Ausgeblendete Kunden einbeziehen**.
- Spalten: **Assets**, **Aufträge** (im Zeitraum angelegte Aufträge zum
  Asset), **Offene Punkte** (derzeit offen, unabhängig vom Zeitraum),
  **Eskaliert** (offene Punkte im Status **Blockiert**), **Defekte**
  (Defektprotokolle dieser Aufträge im Zeitraum), **Defektrate %** (Defekte im
  Verhältnis zu den Aufträgen) und **Letzter Vorfall**.
- Gibt es im Zeitraum Zeiteinträge aus Fernwartungssitzungen zu den
  Geräten, kommen **Wartungssitzungen** und **Wartungszeit** sowie ein
  Diagramm der Wartungszeit hinzu.
- Diagramme: **Defekte im Zeitraum (Top 20)** und **Defektrate (Top 15)**.
  Die Zahlen bei offenen Punkten, Eskalationen und Defekten sowie die Balken
  führen in die zugehörigen Detaillisten.

Export als PDF, CSV und Excel.

## Projekt-Details

**Auswertungen** → **Projekte & Kunden** → **Projekt-Details** zeigt Stunden
und Erlöse eines einzelnen Projekts je Monat. Ausgewertet wird das
Kalenderjahr, in dem der gewählte Zeitraum beginnt.

- Wählen Sie **Kunde** und **Projekt**. Ohne Auswahl erscheint das erste
  Projekt der Liste. Den Filter **Mitarbeiter** gibt es nur mit
  organisationsweiter Zeitsicht.
- Die Projektkarte nennt die Jahressummen **Σ Std.** und **Σ €** und listet
  **Monat**, **Stunden** und **Erlös**; darunter folgt die **Aufteilung pro
  Mitarbeiter**. Der Erlös ist die Summe der bei den Zeiteinträgen
  gespeicherten Beträge.
- Diagramme: **Stundenverlauf im Zeitraum**, **Ist- und Plan-Stunden je Monat**
  (Plan aus dem Feld **Geplante Dauer (HH:MM)** der Projektaufträge nach deren
  Beginn, ohne Angabe aus Servicedauer, Zeitfenster bzw. Termindauer; mit gewähltem
  Mitarbeiter nur die ihm zugewiesenen Aufträge, ohne organisationsweite
  Zeitsicht nur die Ihnen zugewiesenen; ohne Plandaten zeigt eine Linie den
  Median der Ist-Monate) und **Stunden nach Auftragstyp je Monat**.

Administratoren und Rollen mit **Alle Zeiteinträge sehen** sehen alle
Projekte und Stunden. Alle anderen sehen nur Projekte, auf die sie selbst
Zeit gebucht haben, und dort nur ihre eigenen Stunden. Export als PDF, CSV
und Excel, sobald ein Projekt gewählt ist.

## Inaktive Projekte

**Auswertungen** → **Projekte & Kunden** → **Inaktive Projekte** listet alle
nicht archivierten Projekte der Organisation, auf die im Zeitraum keine Zeit
gebucht wurde.

- Diagramm **Projekte je Inaktivitätsdauer**: **≤ 3 Monate**, **3–6
  Monate**, **6–12 Monate**, **> 12 Monate** und **Ohne Buchung**, gemessen
  vom letzten Zeiteintrag bis zum Ende des Zeitraums.
- Tabelle: **Projekt**, **Kunde**, **Status** und **Letzte Aktivität** (der
  jüngste Zeiteintrag überhaupt).
- Filter: **Kunde**.

Zum Aufräumen markieren Sie Projekte und wählen **Ausgewählte archivieren**;
die Rückfrage bestätigen Sie mit **Archivieren**. Archiviert werden nur
Projekte, die Sie selbst angelegt haben, Administratoren können alle
archivieren. Die Meldung nennt die Zahl der tatsächlich archivierten
Projekte. Export als CSV und Excel.

## Datenqualität

**Auswertungen** → **Projekte & Kunden** → **Datenqualität** öffnet die Seite
**Datenqualität: Pflichtklassifikationen**. Sie listet Aufträge des
Zeitraums, denen Angaben nach den Pflichtregeln aus **Klassifikationen**
fehlen. Menüpunkt und Seite verlangen das Recht **Auswertungen einsehen**.

- Kacheln: **Aufträge mit Lücken**, **Harte Lücken** (blockierende Regeln)
  und **Weiche Lücken** (Hinweise).
- Diagramme: Aufträge mit Klassifikationslücken im Verlauf und **Fehlende
  Klassifikationen je Kunde (Top 15)**.
- **Nach Domäne** und **Nach Phase**: wo die Lücken liegen und ab welcher
  Phase die Angabe verlangt wird (bei Erstellung, vor Abschluss oder vor
  Signatur).
- **Betroffene Aufträge**: **Auftrag**, **Datum** und **Fehlende
  Klassifikationen** (rot = hart, gelb = weich). **Nachtragen** öffnet den
  Auftrag.

Filter: **Kunde**, **Projekt**, **Auftragstyp** und **Ausgeblendete Kunden
einbeziehen**. Geprüft werden höchstens die 1.000 jüngsten nicht archivierten
Aufträge im Zeitraum. Die Seite ändert nichts und bietet keinen Export.
