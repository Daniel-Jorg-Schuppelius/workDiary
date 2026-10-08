---
title: "Fahrten, Spesen & Pauschalen"
topic: travel-expenses.manage
version: 2
keywords:
    - Reisekostenabrechnung
    - Spesenabrechnung
    - Reisekosten
    - Fahrtenbuch
    - Kilometergeld
    - Verpflegungsmehraufwand
    - Tagegeld
    - Beleg scannen
    - Quittung erfassen
    - Auslagen erstatten
    - Dienstwagen
    - 1-%-Regel
    - Fahrtbuch
    - Fahrt erfassen
audience: []
modules:
    - module.spesen
related:
    - invoices.manage
    - exports.payroll
    - reports.overview
---

Fahrtenbuch, Spesen und Verpflegungspauschalen dokumentieren
dienstliche Reisen getrennt, aber mit gemeinsamem Zeitraum- und
Belegbezug.

Typischer Ablauf:

1. Fahrt mit Datum, Strecke, Zweck, Fahrzeug und Kilometerständen
   erfassen.
2. Ausgaben mit Kategorie, Betrag, Zahlungsart und Beleg ergänzen.
3. Bei mehrtägigen Reisen die Pauschale aus Reisezeiten und Reiseziel
   berechnen lassen.
4. Angaben prüfen und zur Genehmigung oder Abrechnung weitergeben.

Belege, Kilometerstände und Reisezeiten müssen plausibel sein.
Genehmigte oder abgerechnete Datensätze werden nicht still geändert;
Korrekturen brauchen einen nachvollziehbaren Weg.

## Fahrt erfassen

Eine neue Fahrt legen Sie über **Neu …** in der Seitenleiste an: In der Gruppe
**Planung** öffnet **Fahrtbuch** den Dialog **Neue Fahrt erfassen**. Alle
erfassten Fahrten finden Sie unter **Reisen & Spesen** → **Fahrtenbuch**.

- **Fahrt:** **Datum**, **Fahrzeug** (Fahrzeugtyp mit seinem Kilometersatz),
  **Fuhrpark-Fahrzeug (optional)**, **Fahrtart** sowie **Von (Adresse)** und
  **Nach (Adresse)**.
- **Distanz & Satz:** **Distanz (km, einfach)** ist Pflicht. Bleibt **Satz €/km
  (optional)** leer, gilt der Satz des Fuhrpark-Fahrzeugs, sonst der des
  Fahrzeugtyps. Dazu kommen **Tachostand Beginn (km)**, **Tachostand Ende
  (km)**, **Start (Uhrzeit)** und **Ende (Uhrzeit)**; endet die Fahrt nach
  Mitternacht, tragen Sie einfach die kleinere Uhrzeit ein.
- **Zuordnung:** **Projekt (optional)**, **Kunde (optional)** und **Zweck**.
- **Optionen & Notizen:** **Hin- und Rückfahrt (verdoppelt km)**,
  **Erstattungsfähig** (vorbelegt) und **Notizen**.

**Erfassen** speichert die Fahrt auf Ihren Namen und führt zurück zur Liste.
Sind Start und Ende eingetragen, legt WorkDiary standardmäßig einen nicht
abrechenbaren Zeiteintrag für die Reisezeit an. Ein höherer Tachostand Ende
wird ins Fuhrpark-Fahrzeug übernommen. Steht das Fuhrpark-Fahrzeug im
**Fahrtenbuch-Modus**, sind die km-Stände Pflicht, und die Fahrt wird nach
Tagesende festgeschrieben. Fahrten erfassen kann jede angemeldete Person,
sofern Ihre Organisation das Modul nutzt; eine Fahrt gehört immer der Person,
die sie erfasst hat.

## Auslage als Beleg in die Buchhaltung

Eine **genehmigte** Auslage lässt sich im Beleg-Dialog direkt als
Einkaufsbeleg an das führende Buchhaltungssystem übergeben — statt sie dort
ein zweites Mal zu erfassen. Die externe Beleg-ID kommt beim Anlegen zurück;
die Dublette kann gar nicht erst entstehen.

Drei Regeln:

- **Nur genehmigte Auslagen.** Der Push ist unwiderruflich — das Zielsystem
  kennt für Belege weder Ändern noch Löschen. Korrekturen laufen dort als
  Gegenbeleg.
- **Ohne Buchungskategorie kein Push.** Die Zuordnung wird je
  Auslagenkategorie gepflegt (Verwaltung → Auslagenkategorien); eine geratene
  Kategorie wäre schlimmer als die Fehlermeldung.
- **Ab der Übergabe führt der Beleg.** Die Verknüpfung lässt sich nicht mehr
  lösen — der Beleg existiert, ob verknüpft oder nicht.

Die Belegdateien der Auslage werden mit übergeben — ohne Datei ist der Beleg
für die Buchhaltung wertlos.

### Korrektur per Gegenbeleg

Stimmt an einer übergebenen Auslage etwas nicht, korrigieren Sie sie im
Beleg-Dialog **per Gegenbeleg** – mit Pflichtgrund. Übergeben wird eine
Einkaufsgutschrift über denselben Betrag, die den ursprünglichen Beleg in der
Buchhaltung aufhebt. Gleichzeitig entsteht eine neue Auslage als **Entwurf**
mit Bezug auf die alte; sie durchläuft Genehmigung und Übergabe wie jede andere.

War die ursprüngliche Auslage genehmigt, aber noch nicht erstattet, wird sie
storniert – sonst würden beide ausgezahlt. War sie schon erstattet, weist der
Entwurf darauf hin, dass nur die Differenz zu erstatten ist.

## Beleg scannen statt abtippen

Statt Betrag, Datum und Händler von Hand einzugeben, können Sie den Beleg
**fotografieren oder als PDF hochladen**. Die Erkennung liest die üblichen
Felder aus und füllt das Formular vor.

Das Ergebnis ist ein **Vorschlag**, keine fertige Buchung: Prüfen Sie Betrag,
Datum, Steuersatz und Händler, bevor Sie speichern. Schlecht belichtete
Fotos, Thermopapier und handschriftliche Belege werden erfahrungsgemäß am
häufigsten falsch gelesen.

Der Originalbeleg bleibt unverändert am Vorgang hängen — die Erkennung
ersetzt ihn nicht, sie erspart nur das Abtippen.

## Fahrtenbuch: Unterschrift, Korrektur und 1-%-Vergleich

Im Fahrtenbuch-Modus schließt die fahrende Person eine Fahrt **mit ihrer
Unterschrift** ab; die Fahrt ist danach festgeschrieben. Auch eine Fahrt, die
schon zum Tagesende festgeschrieben wurde, lässt sich noch unterschreiben.
Wird eine Fahrt mitten in der Kette per Stornofahrt korrigiert und ändert sich
dabei der End-km-Stand, beginnt die nächste Fahrt automatisch dort — als
Folgekorrektur, das Original bleibt stehen.

Der **1-%-Vergleich** unter dem Fahrtenbuch-Nachweis stellt je Fahrzeug und Jahr
die Fahrtenbuchmethode der 1-%-Regel gegenüber. Dafür braucht das Fahrzeug den
Bruttolistenpreis und die Entfernung Wohnung–Arbeit; sonstige Jahreskosten
(Leasing, Versicherung, Steuer) tragen Sie dort ein, Energie kommt aus den
Tank- und Ladebelegen. Optional sperrt eine Einstellung neue Fahrten, solange
die Pflichtprüfung eines Fahrzeugs überfällig ist.

**Lenk- und Ruhezeiten.** Wendet Ihre Organisation die Lenkzeitregeln an,
tragen Sie an der Fahrt einen zweiten Fahrer ein (Mehrfahrerbetrieb) oder
markieren Überfahrten, bei denen das Fahrzeug auf Fähre oder Zug mitfährt.
Der zweite Fahrer bekommt dadurch keine Lenkzeit, seine Zeit im Fahrzeug gilt
aber nicht als Ruhezeit; im Mehrfahrerbetrieb genügen 9 Stunden Ruhe binnen
30 Stunden. Eine Überfahrt mit Fähre oder Zug unterbricht die Ruhezeit nicht.
