---
title: "Tagesabschluss"
topic: time-entries.day-close
version: 6
keywords:
    - Tag abschließen
    - Feierabend
    - Arbeitstag beenden
    - Pause nachtragen
    - Tagesbilanz
    - Tagessaldo
    - Zeitlücken
    - Pflichtpause
    - Stempeluhr offen
    - Korrektur anfordern
    - Zeit nachbuchen
audience: []
related:
    - time-entries.start
    - attendance.manage
    - time-accounts.flex
---

Der **Tagesabschluss** bündelt alles, was zu einem Arbeitstag gehört, auf
der Seite **Heute** (Menü **Tagesgeschäft** → **Erfassung** → **Heute**):
**Stempelungen**, Pausen, **Zeiteinträge**, die Prüfungen unter **Lücken &
Warnungen** und die **Bilanz** (unter anderem **Anwesenheit (brutto)**,
**Pflichtpause**, **Saldo Tag** und **Saldo lfd. Monat**).

So gehen Sie vor:

1. **Prüfen**: Öffnen Sie die Seite am Ende des Tages; mit **Vortag** und
   **Folgetag** blättern Sie zu anderen Tagen. Lücken und Inkonsistenzen
   erscheinen im Abschnitt **Lücken & Warnungen**.
2. **Nachtragen**: Fehlende Zeiten erfassen Sie in der Eingabeleiste oben
   (Projekt wählen, **Dauer** oder **Von / Bis** angeben, **Erfassen**).
   Noch nicht verbuchte Anwesenheitsblöcke ordnen Sie unter
   **Quick-Buchung** mit **Buchen** einem Projekt zu; dort bucht `Strg` +
   `Enter` den Block und springt zum nächsten. Stempelungen selbst sind nur
   über einen Korrekturantrag änderbar.
3. **Abschließen**: Sind keine ⛔-Warnungen mehr offen, schließen Sie den Tag
   mit **Tag abschließen** ab. **Speichern** sichert den Stand, ohne den Tag
   abzuschließen.

⛔-Warnungen blockieren den Abschluss: offene Stempeluhr, nicht verbuchte
Anwesenheit (mehr als 5 Minuten) oder eine unterschrittene Pflichtpause.
⚠-Hinweise blockieren nicht, etwa ein Tagessaldo über ±2 Stunden, mehr als
10 Stunden Netto-Arbeitszeit, eine Anwesenheitslücke ohne Pause oder
abrechnungsrelevante Buchungen ohne Kommentar.

⚠ **Unter Soll in einem Projekt** ist eine weiche Warnung aus den Projekt-Sollzeiten (Soll, gebucht, fehlend je Projekt); sie blockiert den Abschluss nicht. Den Befund führt der nächtliche Abgleich am Projekt, eine Nachbuchung erledigt ihn.

Nach dem Abschluss ist der Tag für Sie gesperrt. Brauchen Sie eine
Änderung, beantragen Sie über **Korrektur anfordern** (Begründung, mind.
20 Zeichen) eine Freigabe. Darüber entscheiden Personen mit dem Recht
**Tagesabschluss-Korrekturen genehmigen** (standardmäßig die Teamleitung)
oder Administratoren mit **Freigeben** oder **Ablehnen**. Nach der Freigabe
sind nur Buchungen änderbar, Stempel bleiben gesperrt. Wer das Recht
**Tagesabschluss wieder öffnen** hat, kann einen abgeschlossenen Tag auch
ohne Antrag mit **Tag wieder öffnen** entsperren; die Begründung wird im
Audit-Protokoll festgehalten.

Die Sperre gilt auch für die **Zeiteinträge** des Tages: Solange der Tag
abgeschlossen oder in Korrektur ist, lassen sich Projekt- und
Verwaltungszeiten dieses Tages weder anlegen noch ändern oder löschen, auch
nicht über Stoppuhr, Schnellbuchung, Import oder Schnittstelle. Die
Eingabeleiste ist dann ausgeblendet; die Anwendung nennt den Korrekturantrag
als Weg.

Tage in einem bereits freigegebenen Monat sind komplett gesperrt; dort
läuft jede Änderung über die Monatsfreigabe.

**Eigener Tag und Team:** Über die Korrektur des eigenen Tages entscheidet
niemand selbst, außer es gibt in der Organisation sonst niemanden mit diesem
Recht; das Audit-Protokoll vermerkt dann die Selbstfreigabe. Wer
Tagesabschlüsse nur für das Team sehen darf, sieht unter „Team heute“ und in
den Tagesansichten die Mitglieder der eigenen Teams.

Im Kopf des Tages steht **Plan/Ist**: das Soll des Arbeitszeitmodells mit
Kernzeitbeginn neben der ersten und letzten Stempelung und der Anwesenheit.
Warnmarken erscheinen bei spätem Beginn und bei abweichenden Stunden; die
Schwellen legt die Organisation unter **Einstellungen** → **Plan/Ist der
Anwesenheit** fest (Vorgabe 15 Minuten und 10 %). Die Stempelungen zeigen
außerdem die Minuten im **Homeoffice** und im **Dienstgang**.
