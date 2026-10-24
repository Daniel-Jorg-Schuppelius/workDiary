---
title: "Aufgabenimporte aus Open-Source-Werkzeugen"
topic: admin.oss-task-imports
version: 1
keywords:
    - Vikunja
    - Kanboard
    - Plane
    - Taiga
    - Leantime
    - FreeScout
    - GLPI
    - Aufgabenimport
    - Tickets importieren
    - selbst gehostet
    - API-Token
    - Klärfall
audience:
    - admin
related:
    - admin.plugins
    - admin.integration-inbox
    - admin.data-ownership
    - projects.manage
    - admin.scheduler
---

Sieben Plugins übernehmen Aufgaben aus verbreiteten Open-Source-Werkzeugen als
Aufgaben in WorkDiary-Projekte: **Vikunja**, **Kanboard**, **Plane**, **Taiga**
und **Leantime** für Projektplanung, **FreeScout** und **GLPI** für Support und
Tickets. So buchen Sie Zeiten auf die Arbeit, die dort geplant oder angefragt
wird. Jeder Import liest nur: in das Quellsystem schreibt WorkDiary nichts. Sie
finden die Seiten im Systemmenü (Zahnrad **System**) unter **Plugins**, sobald
das jeweilige Plugin aktiv ist. Alle sieben arbeiten gleich; dieser Text nennt
die Unterschiede je System.

## Zugang einrichten

1. Legen Sie im Quellsystem einen Zugang mit Lesezugriff an (siehe Tabelle).
2. Öffnen Sie auf der Seite des Plugins im Menü ⋯ **Zugangsdaten** und tragen Sie
   die **Adresse** und den Zugang ein. Läuft die Instanz im eigenen Netz, setzen
   Sie zusätzlich **Private Adressen erlauben** – das wirkt nur, wenn der
   Betreiber es zulässt.
3. Klicken Sie auf **Verbindung prüfen**. WorkDiary meldet sich an und zeigt
   Instanz, Adresse und – soweit das System es nennt – das Konto.

| System | Adresse | Zugang |
| --- | --- | --- |
| Vikunja | Adresse der Instanz | API-Token aus Einstellungen → API-Tokens |
| Kanboard | Adresse der Instanz | Anwendungstoken aus Einstellungen → API (Benutzername leer) oder Benutzername und persönliches Zugriffstoken |
| Plane | https://api.plane.so oder eigene Instanz | Workspace-Kürzel und persönliches Zugriffstoken |
| Taiga | https://api.taiga.io oder eigene Instanz | Benutzername und Passwort |
| Leantime | Adresse der Instanz | API-Schlüssel aus den Firmeneinstellungen |
| FreeScout | Adresse der Instanz | API-Schlüssel des Moduls „API & Webhooks“ |
| GLPI | Adresse der Instanz | Benutzer-Token („Fernzugriffsschlüssel“), bei Bedarf App-Token |

Solange Zuordnungen bestehen, lässt sich keine andere Instanz eintragen; die
übernommenen Aufgaben bleiben so eindeutig ihrer Instanz zugeordnet. Bei Plane
zählt der Workspace als Instanz.

## Voraussetzungen je System

- **Vikunja** ab Version 1.0. Das API-Token braucht Leserechte für Projekte und
  Aufgaben; mit dem Recht „other → user“ zeigt die Prüfung auch den Kontonamen.
- **Kanboard** bietet nur aktive Projekte an; inaktive erscheinen nicht.
- **Plane** ab Version 0.28. Angeboten werden nur Projekte, in denen das Konto
  des Tokens Mitglied ist. Plane erlaubt 60 Anfragen je Minute; der erste
  Abgleich großer Projekte dauert deshalb einige Minuten.
- **Taiga**: Das Konto braucht im Projekt das Recht, Userstories zu sehen.
  Fehlt es, liefert Taiga eine leere Liste; WorkDiary meldet den Lauf dann als
  unvollständig, statt Aufgaben als gelöscht zu werten.
- **Leantime** ab Version 3.8.
- **FreeScout**: Übernommen werden nur veröffentlichte Konversationen, keine
  Entwürfe und keine gelöschten.
- **GLPI**: Die REST-API muss eingeschaltet sein, und das Konto braucht
  Leserechte auf die Tickets der Entität. WorkDiary beendet die Sitzung nach
  jedem Lauf.

## Quellen zuordnen und Vorschau prüfen

Quelle ist je System ein Projekt, bei FreeScout ein **Postfach** und bei GLPI
eine **Entität** (ohne Unterentitäten). Im Dialog erscheinen Postfächer und
Entitäten in der Liste der Projekte.

1. **Projekt zuordnen** öffnet einen Dialog mit den Quellen, die der Zugang
   sieht, und den WorkDiary-Projekten. Je Quelle gibt es genau ein Ziel.
2. Die **Vorschau** zeigt die offenen Einträge und was der erste Abgleich mit
   ihnen täte. Sie speichert nichts.
3. Bestehende Aufgaben des Zielprojekts verknüpfen Sie über **Gleichnamige
   verknüpfen** oder **Bestehende Aufgabe verknüpfen**; abweichende Felder
   kommen zur Prüfung in die Inbox.
4. **Import bestätigen** gibt die Zuordnung frei. Erst dann legt der Abgleich
   die offenen Einträge als Aufgaben an; bereits erledigte nie.

## Was übernommen wird

Übernommen werden **Titel**, **Beschreibung** als Text und, wo vorhanden, die
**Fälligkeit**. Der Status kommt immer aus einem festen Feld des Systems, nie aus
frei benennbaren Spalten- oder Statusnamen:

| System | Offen | In Arbeit | Erledigt | Fälligkeit |
| --- | --- | --- | --- | --- |
| Vikunja | nicht erledigt | – | erledigt | Fälligkeitsdatum |
| Kanboard | aktiv | – | geschlossen | Fälligkeitsdatum |
| Plane | Gruppe Backlog, Unstarted, Triage | Gruppe Started | Gruppe Completed, Cancelled | Zieldatum |
| Taiga | offen | – | geschlossen | Fälligkeitsdatum |
| Leantime | Statusart „Neu“ | Statusart „In Bearbeitung“ | Statusart „Erledigt“ | Fälligkeitsdatum |
| FreeScout | aktiv | wartend | geschlossen, Spam | – |
| GLPI | neu, Genehmigung | zugewiesen, geplant, wartend | gelöst, geschlossen | Lösungsfrist |

- Steht eine Aufgabe in WorkDiary auf **In Arbeit**, bleibt das so, solange die
  Quelle sie nicht schließt.
- Bei FreeScout ist die Beschreibung die erste Nachricht der Konversation;
  interne Notizen bleiben außen vor. Bei Taiga liest WorkDiary die
  Beschreibung nur für neue oder geänderte Userstories.
- Schließt Taiga eine Userstory, weil alle ihre Tasks erledigt sind, kommt das
  mit dem nächsten Abgleich an. Öffnet ein neuer Task sie wieder, sieht
  WorkDiary das erst beim täglichen Abgleich aller Einträge.
- Meilensteine aus Leantime werden nicht übernommen.
- Am Aufgabendialog führt **In … öffnen** zum Original.

## Wann abgeglichen wird

Alle 15 Minuten holt WorkDiary die seit dem letzten Lauf geänderten Einträge,
einmal täglich alle. Den Takt ändern Sie unter **Geplante Jobs**; **Jetzt
abgleichen** im Menü ⋯ startet sofort. Ein abgebrochener Lauf gilt als
**unvollständig**, der nächste setzt am letzten vollständigen Stand an.

## Konflikte und Klärfälle

Unterschiedliche Änderungen auf beiden Seiten ergeben einen Konflikt in der
Inbox; nichts wird in das Quellsystem zurückgeschrieben. Ist ein verknüpfter
Eintrag nicht mehr sichtbar, bleibt Ihre Aufgabe unverändert, und die Inbox
meldet einen Klärfall. Führt laut **Datenführerschaft** ein anderes System die
Aufgaben, übernimmt WorkDiary nichts. **Klärfälle** im Menü ⋯ öffnet die Inbox
gefiltert auf das Plugin.

## Pausieren, entfernen, trennen

**Pausieren** und **Fortsetzen** halten den Abgleich einer Zuordnung an;
**Zuordnung entfernen** lässt übernommene Aufgaben und Zeiten bestehen.
**Trennen** beendet den Abgleich und pausiert alle Zuordnungen. Den Zugang
entziehen Sie im Quellsystem oder löschen ihn unter **Zugangsdaten**; eine
erneute Prüfung mit derselben Instanz setzt die Zuordnungen fort.

## Typische Fehler

- Die Prüfung meldet eine interne Adresse: Die Adresse zeigt ins eigene Netz,
  aber **Private Adressen erlauben** ist aus oder vom Betreiber nicht zugelassen.
- Die Prüfung scheitert mit 401: Token, Schlüssel oder Passwort sind falsch,
  abgelaufen oder ohne Lesezugriff.
- Die Prüfung meldet „…VersionUnsupportedException“: Die Instanz ist älter als
  die oben genannte Mindestversion.
- Plane zeigt ein Projekt nicht: Das Konto des Tokens ist dort kein Mitglied,
  auch wenn das Projekt öffentlich ist.
- Kanboard zeigt nur einen Teil der Projekte: Mit Benutzername sieht der Zugang
  nur dessen Projekte; für alle Projekte das Anwendungstoken ohne Benutzername
  verwenden.
- Taiga lehnt die Anmeldung ab: Konten, die sich über GitHub oder GitLab
  anmelden, brauchen ein eigenes Passwort.
- Leantime oder GLPI zeigt keine Quelle: Der API-Schlüssel ist keinem Projekt
  zugeordnet, oder die REST-API ist in GLPI nicht eingeschaltet.
- FreeScout antwortet mit 404: Das Modul „API & Webhooks“ ist nicht aktiv.
