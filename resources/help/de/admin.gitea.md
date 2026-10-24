---
title: "Gitea-/Forgejo-Aufgabenimport"
topic: admin.gitea
version: 1
keywords:
    - Gitea
    - Forgejo
    - Issues importieren
    - Repository
    - selbst gehostet
    - Access Token
    - Aufgabenimport
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

Die Seite **Gitea/Forgejo** übernimmt Issues ausgewählter Repositories Ihrer
selbst gehosteten Gitea- oder Forgejo-Instanz als Aufgaben in WorkDiary-Projekte.
So buchen Sie Zeiten auf die Arbeit, die dort geplant wird. Der Import liest nur:
in die Instanz schreibt WorkDiary nichts. Sie finden die Seite im Systemmenü
(Zahnrad **System**) unter **Plugins** → **Gitea/Forgejo**, sobald das Plugin
aktiv ist.

## Zugang einrichten

1. Erzeugen Sie in Gitea oder Forgejo unter **Einstellungen** → **Anwendungen**
   einen Access Token mit den Bereichen **read:user**, **read:repository** und
   **read:issue**. Ein Token, der nur für ausgewählte Repositories gilt, sieht
   auch im Dialog nur diese.
2. Öffnen Sie auf der Seite im Menü ⋯ **Zugangsdaten** und tragen Sie die
   **Basis-URL** (z. B. https://git.example.com, ohne /api/v1) und den **Access
   Token** ein. Läuft die Instanz im eigenen Netz, setzen Sie zusätzlich
   **Private Adressen erlauben** – das wirkt nur, wenn der Betreiber es zulässt.
3. Klicken Sie auf **Verbindung prüfen**. WorkDiary meldet sich bei der Instanz
   an und zeigt Instanz, Adresse und Konto.

Solange Zuordnungen bestehen, lässt sich keine andere Instanz eintragen; die
übernommenen Aufgaben bleiben so eindeutig ihrer Instanz zugeordnet.

## Repositories zuordnen und Vorschau prüfen

1. **Projekt zuordnen** öffnet einen Dialog mit den Repositories, auf die der
   Token Zugriff hat, und den WorkDiary-Projekten. Je Repository gibt es genau
   ein Ziel.
2. Die **Vorschau** zeigt die offenen Issues und was der erste Abgleich mit ihnen
   täte. Sie speichert nichts.
3. Bestehende Aufgaben des Zielprojekts verknüpfen Sie über **Gleichnamige
   verknüpfen** oder **Bestehende Aufgabe verknüpfen**; abweichende Felder
   kommen zur Prüfung in die Inbox.
4. **Import bestätigen** gibt die Zuordnung frei. Erst dann legt der Abgleich
   die offenen Issues als Aufgaben an; geschlossene Altissues nie.

## Was übernommen wird

- **Titel**, **Beschreibung** als Text und **Fälligkeit** als Datum, wie es
  die Instanz anzeigt.
- **Status**: geschlossen → **Erledigt**, offen → **Offen**. Steht eine Aufgabe
  in WorkDiary auf **In Arbeit**, bleibt das so.
- Pull Requests werden nicht übernommen. Wird ein Repository umbenannt, läuft
  der Import weiter.
- Am Aufgabendialog führt **In Gitea/Forgejo öffnen** zum Issue.

## Wann abgeglichen wird

Alle 15 Minuten holt WorkDiary die seit dem letzten Lauf geänderten Issues,
einmal täglich alle. Den Takt ändern Sie unter **Geplante Jobs**; **Jetzt
abgleichen** im Menü ⋯ startet sofort. Ein abgebrochener Lauf gilt als
**unvollständig**, der nächste setzt am letzten vollständigen Stand an.

## Konflikte und Klärfälle

Unterschiedliche Änderungen auf beiden Seiten ergeben einen Konflikt in der
Inbox; nichts wird in die Instanz zurückgeschrieben. Ist ein verknüpftes Issue
nicht mehr sichtbar, bleibt Ihre Aufgabe unverändert, und die Inbox meldet einen
Klärfall. Führt laut **Datenführerschaft** ein anderes System die Aufgaben,
übernimmt WorkDiary nichts. **Klärfälle** im Menü ⋯ öffnet die Inbox gefiltert
auf Gitea/Forgejo.

## Pausieren, entfernen, trennen

**Pausieren** und **Fortsetzen** halten den Abgleich einer Zuordnung an;
**Zuordnung entfernen** lässt übernommene Aufgaben und Zeiten bestehen.
**Trennen** beendet den Abgleich und pausiert alle Zuordnungen. Den Token
entziehen Sie in Gitea/Forgejo oder löschen ihn unter **Zugangsdaten**; eine
erneute Prüfung mit derselben Instanz setzt die Zuordnungen fort.

## Typische Fehler

- Die Prüfung meldet eine interne Adresse: Die Basis-URL zeigt ins eigene Netz,
  aber **Private Adressen erlauben** ist aus oder vom Betreiber nicht zugelassen.
- Die Prüfung scheitert mit 401: Der Token ist falsch, abgelaufen oder ohne
  Lesezugriff.
- Ein Repository fehlt im Dialog: Der Token sieht es nicht, etwa weil er nur
  für ausgewählte Repositories gilt.
- Die Prüfung scheitert mit 403: Dem Token fehlt einer der Bereiche
  **read:user**, **read:repository** oder **read:issue**.
