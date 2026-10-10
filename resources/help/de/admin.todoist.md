---
title: "Todoist-Anbindung"
topic: admin.todoist
version: 2
keywords:
    - Todoist
    - Aufgaben synchronisieren
    - Aufgabenabgleich
    - Todoist verbinden
    - Projektzuordnung
    - Preflight
    - Abschnitte
    - Bearbeiter zuordnen
    - Kanban
    - Aufgabenkonflikt
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - work.overview
    - projects.manage
    - admin.scheduler
---

Die Seite **Todoist** gleicht Aufgaben zwischen WorkDiary und Todoist ab. Es
werden nur Todoist-Projekte synchronisiert, die Sie ausdrücklich einem
WorkDiary-Projekt oder dem globalen Kanban zuordnen; Konflikte landen in der
Integrations-Inbox, nichts wird still überschrieben oder gelöscht. Sie finden
die Seite im Systemmenü (Zahnrad **System** in der Kopfzeile) unter
**Plugins** → **Todoist**, sobald das Plugin aktiv ist.

## Voraussetzungen

- Das Plugin ist für Ihre Organisation aktiviert: **System** → **Plugins** →
  **Plugins**, beim Eintrag Todoist **Aktivieren**.
- Die Seite steht Administratoren offen.
- Es braucht eine registrierte Todoist-App. Entweder hat der Betreiber Ihrer
  Installation eine hinterlegt, oder Sie tragen eine eigene ein: auf der Seite
  **Plugins** über **Konfigurieren** beim Eintrag Todoist die Felder
  **Client-ID (eigene Todoist-App)** und **Client-Secret**. Leer gilt die App
  der Installation. Eine eigene App muss in Todoist als Redirect-URI die
  Adresse Ihrer WorkDiary-Installation mit dem Pfad
  `/admin/todoist/oauth/callback` eintragen.
- Je Organisation gibt es genau eine Todoist-Verbindung, also ein
  Todoist-Konto.

## Mit Todoist verbinden

1. Öffnen Sie die Seite **Todoist**. Der Abschnitt **Verbindung** nennt vorab,
   welche Daten übertragen werden: Titel, Beschreibungen, Status, Fälligkeiten
   und Zuständige der zugeordneten Aufgaben. Lösch-Rechte fordert WorkDiary
   nicht an.
2. Klicken Sie auf **Mit Todoist verbinden** und melden Sie sich bei Todoist
   an. Nach der Freigabe kehren Sie auf die Seite zurück.
3. Danach zeigt die Seite **Status**, **Konto**, **Verbunden seit** und
   **Letzter Abgleich**. Mit **Verbindung erneuern** melden Sie sich erneut
   an, **Trennen** beendet die Verbindung; Zuordnungen und Verknüpfungen
   bleiben erhalten.

## Projekte zuordnen

Bei aktiver Verbindung erscheint die Tabelle **Projektzuordnungen**. Unter
der Tabelle legen Sie eine neue Zuordnung an:

1. **Todoist-Projekt**: Auswahl aus Ihrem Todoist-Konto.
2. **Ziel**: **WorkDiary-Projekt** (dann das Projekt wählen; die Liste zeigt
   höchstens 500 Projekte) oder **Globales Kanban** für Aufgaben ohne Projekt.
3. **Richtung**: **Todoist → WorkDiary**, **WorkDiary → Todoist** oder
   **Bidirektional**.
4. **Zuordnen**. Jede neue Zuordnung beginnt als **Entwurf** und gleicht noch
   nichts ab.

In der Tabelle öffnen Sie je Zuordnung den **Preflight**, schalten sie mit
**Aktivieren** bzw. **Pausieren** und entfernen sie über das Papierkorb-Symbol
(Referenzen bleiben erhalten). Die Spalte **Letzter Lauf** zeigt Zeitpunkt
und Zähler: neu angelegt, aktualisiert, unverändert und Konflikte.

## Preflight: Bearbeiter und Abschnitte

Der **Preflight** zeigt vor der Aktivierung, was der Abgleich vorfindet:

- **Kennzahlen**: aktive Aufgaben, Unteraufgaben, wiederkehrende Aufgaben,
  Fälligkeiten mit Uhrzeit, nicht zuordenbare Bearbeiter und bereits
  verknüpfte Aufgaben. Wiederkehrende Aufgaben kommen als einzelne Aufgabe
  mit dem nächsten Fälligkeitstag an, die Wiederholung kennt nur Todoist; von
  Fälligkeiten mit Uhrzeit übernimmt WorkDiary nur das Datum.
- **Bearbeiter-Zuordnung**: Je Todoist-Kollaborator wählen Sie einen
  WorkDiary-Benutzer und klicken auf **Speichern**. Eine gleiche E-Mail-Adresse
  zeigt WorkDiary nur als **Vorschlag**; zugewiesen wird erst nach Ihrer Wahl.
  Ohne Zuordnung bleibt eine Aufgabe ohne Zuständigen.
- **Abschnitte → Status**: Je Todoist-Abschnitt wählen Sie **Offen** oder
  **In Arbeit**. Nicht zugeordnete Abschnitte lassen den Status unberührt.

Erst danach schalten Sie die Zuordnung mit **Aktivieren** scharf.

## Was abgeglichen wird

- **Todoist → WorkDiary**: Aus jeder aktiven Todoist-Aufgabe wird eine
  WorkDiary-Aufgabe im Zielprojekt bzw. im globalen Kanban. Abgeglichen
  werden Titel, Beschreibung, Priorität (Todoist p1 bis p4 entspricht
  Dringend, Hoch, Mittel, Niedrig), Fälligkeit, Dauer als Zeitbudget,
  Zuständige und Status. In Todoist erledigt heißt hier **Erledigt**.
  Unteraufgaben bleiben unter ihrer übergeordneten Aufgabe.
- **WorkDiary → Todoist**: Neue Aufgaben, die nach der Aktivierung im
  zugeordneten Projekt bzw. im globalen Kanban entstehen, legt WorkDiary in
  Todoist an. Änderungen an verknüpften Aufgaben gehen ebenfalls hinüber; ein
  Statuswechsel verschiebt die Aufgabe in den zugeordneten Abschnitt bzw.
  erledigt oder öffnet sie wieder. Aufgaben, die schon vor der Aktivierung
  bestanden, überträgt WorkDiary nicht.
- **Bidirektional** verbindet beide Richtungen.
- An verknüpften Aufgaben zeigt der Aufgabendialog den Link **In Todoist
  öffnen**.

## Wann abgeglichen wird

- Stündlich holt WorkDiary die Änderungen seit dem letzten Lauf aus Todoist.
  Der Takt lässt sich unter **Geplante Aufgaben** ändern.
- **Jetzt abgleichen** startet einen vollständigen Abgleich im Hintergrund.
  Nur er bemerkt auch Aufgaben, die ohne Löschung aus dem Todoist-Projekt
  verschwunden sind, etwa weil sie verschoben wurden.
- Änderungen aus WorkDiary gehen über eine Warteschlange gleich hinüber und
  werden bei Fehlern wiederholt.
- Nutzen Sie eine eigene Todoist-App, können Sie dort zusätzlich einen
  Webhook auf die Adresse Ihrer Installation mit dem Pfad
  `/api/webhooks/todoist` eintragen. Er stößt bei Änderungen einen gezielten
  Abgleich an; der stündliche Lauf bleibt die verlässliche Quelle.

## Konflikte und Löschungen

- WorkDiary vergleicht jedes Feld mit dem Stand des letzten Abgleichs. Wurde
  ein Feld auf beiden Seiten unterschiedlich geändert, entsteht ein Konflikt
  in der Inbox; dort entscheiden Sie, welcher Stand gilt. Bis dahin überträgt
  WorkDiary dieses Feld nicht.
- Löschungen gibt WorkDiary in keine Richtung weiter. Verschwindet eine
  Aufgabe in Todoist oder wird eine verknüpfte Aufgabe hier gelöscht, entsteht
  ein Fall in der Inbox.
- Eine Unteraufgabe, deren übergeordnete Aufgabe hier fehlt, landet ebenfalls
  in der Inbox.
- Wurde eine Aufgabe in WorkDiary erledigt, setzt ein Wiederöffnen in Todoist
  sie nicht zurück.

**Integrations-Inbox** auf der Seite öffnet die Zuordnungs-Inbox gefiltert auf
Todoist.

## Typische Fehler

- „Todoist ist nicht konfiguriert“: Es ist keine Todoist-App hinterlegt –
  weder vom Betreiber noch in den Plugin-Einstellungen.
- „Ungültiger oder abgelaufener OAuth-Status“: Die Anmeldung hat zu lange
  gedauert oder lief in einer anderen Sitzung. Verbinden Sie erneut.
- „Token-Austausch fehlgeschlagen“: Client-ID, Client-Secret oder
  Redirect-URI der eigenen App stimmen nicht.
- Die Liste der Todoist-Projekte ist leer: Die Verbindung erreicht Todoist
  nicht. Prüfen Sie den Zustand auf der Seite **Plugins** und erneuern Sie die
  Verbindung.
- Aufgaben kommen ohne Zuständigen an: Der Todoist-Kollaborator ist im
  Preflight noch keinem Benutzer zugeordnet.
- Nichts wird abgeglichen: Die Zuordnung steht noch auf **Entwurf** oder
  **Pausiert**.
- Die Verbindung steht auf **Pausiert**: Todoist hat den Zugang abgelehnt,
  etwa weil die App-Freigabe in Todoist widerrufen wurde. Der Abgleich ruht;
  mit **Verbindung erneuern** verbinden Sie neu. Scheitert ein Abgleich aus
  anderem Grund, nennt die Seite den letzten Fehler, bis ein Abgleich wieder
  gelingt; zusätzlich entsteht eine Betriebsaufgabe.
