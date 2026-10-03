---
title: "Projekte verwalten"
topic: projects.manage
version: 3
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - contacts.manage
    - time-entries.start
    - timesheets.manage
    - finance.transfers
---

## Zweck und Hintergrund

Projekte bündeln alles, was zu einem Vorhaben gehört: Kunde, Laufzeit,
Verantwortliche, Aufgaben, Meilensteine, gebuchte Zeiten und die
Abrechnungsregeln. Sie sind die Klammer zwischen Zeiterfassung und
Faktura — was am Projekt richtig eingestellt ist, muss später niemand
je Buchung korrigieren.

## Voraussetzungen

- Ein angelegter Kunde (siehe Kunden & Lieferanten).
- Das Recht, Projekte zu verwalten.
- Für die Abrechnung: geklärte Abrechnungsregeln (Stundensatz,
  Pauschalen, abrechenbar ja/nein).

## Empfohlener Ablauf

1. Projekt mit **Kunde und Zeitraum** anlegen.
2. **Verantwortlichkeiten und Status** setzen.
3. **Aufgaben oder Wiederholungen** planen.
4. Leistungen buchen (lassen) und den Fortschritt in der Detailansicht
   prüfen.
5. Vor dem Abschluss offene Aufgaben, Zeiten, Stundenzettel und
   abrechenbare Positionen kontrollieren — erst dann schließen.

![Projektliste mit Kunde, Status und Laufzeit](media/kunden/projektliste.png)
*Die Projektliste: jedes Projekt mit Kunde, Status und Laufzeit.*

Der Reiter **Zeiten** über der Projektliste zeigt die Zeiteinträge aller
Projekte im oben gewählten Zeitraum, ohne dass Sie jedes Projekt einzeln
öffnen müssen. Die Einträge sind nach Projekt gruppiert; über „Gruppieren
nach“ wechseln Sie auf Datum oder Person. Jede Gruppe nennt Anzahl und
Summe für den ganzen Zeitraum und lässt sich einklappen. Filtern können Sie
nach Suchbegriff (Projekt, Aufgabe, Beschreibung), Kunde, Projekt,
Mitarbeitenden, Tag und Abrechenbarkeit. Zeiten anderer Personen sehen nur
Administration, Buchhaltung und wer alle Zeiten einsehen darf; alle anderen
sehen ihre eigenen. Zeiten ohne Projekt erscheinen hier nicht.

Dieselbe Sichtregel gilt am einzelnen Projekt (Zeiterfassung,
Stundenzettel, Gesamtstunden), in der Fallakte eines Auftrags und bei den
Zeitwerten am Kunden: Ohne die Sicht auf alle Zeiten zählen Listen und
Summen nur die eigenen Einträge und tragen den Hinweis „nur eigene Zeiten“.

Falsch zugeordnete Zeiten, etwa nach einem Import mit falschem Benutzer,
korrigieren Sie im Reiter **Zeiten**: Einträge oder ganze Gruppen ankreuzen
und über „Benutzer zuordnen“ einer anderen Person zuweisen. Das geht mit
Administrationsrechten oder dem Recht „Zeiteinträge anderen Benutzern
zuordnen“ und nur für Zeiten, die Sie sehen dürfen. Abgerechnete und
signierte Zeiten bleiben gesperrt; eine Auswahl wird nie teilweise
gespeichert.

## Beispiel aus der Praxis

Für einen Serverumzug entsteht das Projekt „Migration RZ" mit Laufzeit,
Stundensatz und zwei Verantwortlichen. Die Techniker buchen ihre Zeiten
direkt auf das Projekt; am Monatsende zeigt die Detailansicht auf einen
Blick, was abrechenbar offen ist.

## Typische Fehler

- **Zu früh schließen:** Ein geschlossenes Projekt nimmt keine
  Buchungen mehr an — offene Zeiten und Positionen vorher prüfen.
- **Abrechnungsregeln nachträglich ändern** und erwarten, dass alte
  Buchungen folgen: Regeln wirken auf künftige Vorgänge.
- **Alles ohne Projekt buchen:** Ohne Projektbezug fehlen später
  Auswertung und saubere Faktura-Übergabe.

## Auswirkungen und nächste Schritte

Abrechnungsregeln und Projektstatus bestimmen, welche Zeiten und
Materialien in die Übergabe wandern. Als Nächstes: Zeiterfassung auf
das Projekt einrichten und am Periodenende die Übergabe an die Faktura
prüfen.
