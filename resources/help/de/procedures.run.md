---
title: "Prozedur ausführen"
topic: procedures.run
version: 4
keywords:
    - Checkliste abarbeiten
    - Arbeitsanweisung ausführen
    - Ablauf starten
    - Pflichtschritte
    - Abweichung erfassen
    - Gegenzeichnung
    - Vier-Augen-Prüfung
    - Backup-Nachweis
    - Schritt bestätigen
audience: []
related:
    - protocols.create
---

Eine Prozedur (Arbeitsanweisung mit Pflichtschritten) wird auf einem
**Auftrag** oder **Asset** gestartet. Während des Laufs gilt:

- **Pflichtschritte** müssen in der definierten Reihenfolge bearbeitet
  werden.
- **Backup-Schritte** verlangen den Nachweis (Hash + Größe oder externer
  Ablage-Link), bevor Sie weiterklicken können.
- **Vier-Augen-Schritte** brauchen die Gegenzeichnung einer **zweiten Person**
  mit passender Rolle (siehe unten).
- **Abweichungen** werden mit Begründung und Folgeaktion erfasst – ein
  Überspringen ohne Begründung ist nicht möglich.

Beim Abschluss der Prozedur wird die verwendete Versionsnummer der
Vorlage fixiert. Spätere Vorlagenänderungen wirken **nicht** rückwirkend.

## Vier-Augen-Schritte

Die Leiste **Vier-Augen-Freigabe** am Schritt zeigt den Stand: noch nicht
angefordert, angefordert, übernommen oder gegengezeichnet.

1. Wer den Schritt ausführt, klickt **Zweite Person anfordern**. Die
   Teamleitung erhält eine Benachrichtigung mit Link auf den Lauf.
2. Eine andere berechtigte Person öffnet den Lauf und klickt **Als zweite
   Person gegenzeichnen**. Wer angefordert hat, kann nicht selbst
   gegenzeichnen.
3. Erst danach lässt sich der Schritt erledigen.

Eine Gegenzeichnung lässt sich mit Begründung **widerrufen** (Recht
„Vier-Augen-Freigabe widerrufen“, in der Regel Leitung oder Administration). Der
Schritt fällt dann auf „offen“ zurück, die Anforderung bleibt bestehen und
eine andere Person kann gegenzeichnen. Anforderung, Gegenzeichnung und
Widerruf stehen mit Person und Zeitpunkt im Verlauf des Laufs.

## Backup-Nachweis

Ein Schritt vom Typ „Backup-Nachweis“ zeigt die Karte **Backup-Nachweis**.

1. **Backup-Nachweis erfassen** öffnet den Dialog: gesichertes System,
   Umfang, Zeitpunkt der Sicherung, Ablage (**Anhang** mit Datei oder
   **Externe Ablage** mit Ablageort und Größe) und Prüfweg. Was die Vorlage
   festlegt, steht nicht zur Wahl; Mindestgröße und Höchstalter prüft das
   System beim Speichern.
2. Beim Prüfweg **Checksum-Vergleich** geben Sie die erwartete Prüfsumme
   an. Zu einer hochgeladenen Datei rechnet das System die Prüfsumme nach
   und bestätigt den Nachweis selbst; bei externer Ablage trägt die prüfende
   Person die nachgerechnete Prüfsumme ein.
3. **Restore-Test** und **Bestätigung Geschäftsleitung** bestätigt eine
   **zweite Person** mit dem Recht „Backup-Nachweis verifizieren“ über
   **Nachweis bestätigen** – nicht die Person, die erfasst hat.
4. **Nachweis ablehnen** verlangt eine Begründung; danach erfassen Sie
   einen neuen Nachweis (**Nachweis ersetzen**).

Erst mit bestätigtem Nachweis lässt sich der Schritt erledigen. Verlangt
ein späterer Schritt ein vorheriges Backup, wird er damit frei. Ist kein
Backup möglich, halten Sie das als **Abweichung** fest. Den Ablageort einer
externen Sicherung sieht nur, wer das Recht „Externe Backup-Ablagen
einsehen“ hat.

## Qualifikation

Nennt ein Schritt eine **Qualifikation**, kann ihn nur erledigen, wer diese
Qualifikation heute gültig besitzt (Kürzel oder Name wie unter den
Qualifikationen der Mitarbeitenden). Dasselbe gilt für die zweite Person,
wenn die Vorlage für die Gegenzeichnung eine Qualifikation verlangt.

## Kritische Abweichung und Risikoakzeptanz

Eine Abweichung steht mit Art, Schweregrad, Begründung und Folgemaßnahme am
Schritt. Eine **kritische** Abweichung sperrt den Abschluss des Laufs,
solange weder ein offener Punkt noch ein Folgeauftrag daraus entstanden
ist. Wer das Recht „Kritische Abweichung akzeptieren“ hat, öffnet an der
Abweichung **Risiko akzeptieren** und gibt eine Begründung an. Person,
Zeitpunkt und Begründung stehen im Verlauf des Laufs; der Bericht
„Prozedur-Abweichungen“ zeigt die Entscheidung.

## Kritische Prozeduren

Ein Lauf einer Vorlage mit Risikostufe „Kritisch“ lässt sich nur
abschließen, wenn mindestens ein Vier-Augen-Schritt erledigt und
gegengezeichnet ist.

## Start am Asset

Vorlagen, die der Designer über Asset-Kategorie, Produkt oder SLA-Vertrag
zuordnet, erscheinen an der Asset-Akte in der Karte **Prozeduren** unter
„Für dieses Asset vorgesehene Prozeduren:“. Ist das Asset gesperrt, startet
nur eine Vorlage mit dem Schalter **Darf an gesperrten Assets laufen**.

**Erneut durchlaufen:** Wählen Sie diese Folgeaktion bei einer Abweichung, startet WorkDiary sofort einen Wiederholungslauf derselben Vorlage am selben Auftrag oder Asset. Die Abweichung verweist darauf, und eine kritische Abweichung sperrt den Abschluss dann nicht mehr. Ist das Asset gesperrt, entsteht kein Lauf; der Grund steht im Verlauf.
