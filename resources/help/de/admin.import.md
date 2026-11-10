---
title: "CSV-Import"
topic: admin.import
version: 6
keywords:
    - Datenimport
    - Stammdaten importieren
    - Kunden importieren
    - Import-Assistent
    - Spaltenzuordnung
    - Massenimport
    - Altdaten übernehmen
    - Datenübernahme
    - Fehlerbericht
    - Zählerstände importieren
    - iCal-Import
    - Kalender importieren
audience:
    - admin
    - geschaeftsfuehrung
    - personalverwaltung
    - teamleitung
    - buchhaltung
    - user
    - aussendienst
schema: process
related:
    - admin.handbook
    - admin.tenants
    - contacts.manage
---

## Zweck und Hintergrund

Der Import-Wizard bringt Stammdaten per CSV nach WorkDiary — mit
Analyse **vor** dem Schreiben und vollständigem Fehlerbericht. Er ist
der schnellste Weg, einen Altbestand (Kunden, Benutzer, Projekte,
Teams, Lieferanten, Materialien) strukturiert zu übernehmen, ohne die
Datenqualität dem Zufall zu überlassen.

## Voraussetzungen

- Administrationsrechte.
- Eine CSV-Datei je Entität; Spaltenzuordnung erfolgt im Wizard.
- Bei abhängigen Daten: die richtige **Reihenfolge** (erst
  Kunden/Teams, dann Projekte & Co.).

## Empfohlener Ablauf

1. **Entität wählen** (z. B. Kunden, Benutzer, Projekte, Teams,
   Lieferanten, Materialien).
2. **CSV hochladen** — die **Preflight-Analyse** prüft Struktur und
   Inhalte, ohne etwas zu schreiben.
3. **Vorschau prüfen:** erkannte Zeilen, Warnungen, Fehler.
4. **Bestätigen** — der Import läuft als Hintergrund-Job.
5. **Fehler-CSV herunterladen:** alle abgewiesenen Zeilen mit
   Begründung; korrigieren und erneut importieren.

![Import-Assistent mit Entitätswahl, Mustervorlage und Vorprüfung](media/administration/import-assistent.png)
*Der Import-Assistent: Entität wählen, Mustervorlage laden, Datei hochladen — die Vorprüfung schreibt nichts.*

## Eigene Spaltennamen zuordnen

Kennt der Import eine Spaltenüberschrift nicht, zeigt der Lauf die Karte
**Spaltenzuordnung**. Wählen Sie je Überschrift die Zielspalte und speichern
Sie — die Datei wird sofort neu geprüft. Die Zuordnung gilt ab dann für jede
weitere Datei derselben Importart, auch für den Kundenimport in der
Kundenliste. Ein KI-Vorschlag belegt die Auswahl nur vor. Gespeicherte
Zuordnungen finden Sie in der Importliste im Menü unter **Gespeicherte
Spaltenzuordnungen**; dort lassen sie sich einzeln löschen.

## Beispiel aus der Praxis

Beim Umstieg importiert ein Betrieb zuerst eine Testdatei mit zehn
Kunden, prüft Vorschau und Feldzuordnung, und lädt dann den
Vollbestand mit 800 Zeilen. Zwölf Zeilen landen mit Begründung im
Fehlerbericht, werden korrigiert und im zweiten Lauf übernommen.

## Typische Fehler

- **Ohne Testdatei direkt den Vollbestand laden** — Zuordnungsfehler
  multiplizieren sich unnötig.
- **Reihenfolge missachten:** Projekte vor ihren Kunden scheitern an
  fehlenden Bezügen.
- **Fehlerbericht ignorieren:** Fehlerhafte Zeilen brechen den Lauf
  nicht ab — sie fehlen aber im Bestand, bis sie nachimportiert sind.

## Auswirkungen und nächste Schritte

Vor der Bestätigung wird **nichts** geschrieben — Preflight und
Vorschau sind gefahrlos. Die Import-Historie zeigt alle Läufe mit
Status und lässt sich nach Entität und Zustand filtern. Als Nächstes:
importierte Stammdaten stichprobenartig prüfen und Dubletten über die
Zusammenführung bereinigen.

## Bewegungs- und Messdaten

Neben Stammdaten übernimmt der Import auch laufende Daten: Zählerstände,
ESG-Verbräuche (Aktivität, Menge, Zeitraum und Datenqualität), Prüfmesswerte
zu vorhandenen Prüfungen und Gerätepositionen aus Telematik-Exporten. Bei
Positionen meldet WorkDiary, wenn ein verliehenes Gerät den Einsatzort
verlässt.

## Stempelungen und Projektzeiten aus dem Kalender

Für Stempelungen und Projektzeiten können Sie statt einer Datei eine
verbundene Kalenderquelle wählen (CalDAV, Google Kalender, Microsoft 365).
Die Termine des gewählten Zeitraums werden abgerufen und wie eine iCal-Datei
geprüft — mit Vorschau, Kategorie-Filter und Auflösung von Serien.

Der Import von Anwesenheiten kennt die optionale Spalte **erfasst am**
(Datum und Uhrzeit der ursprünglichen Aufzeichnung). Sie dient der
MiLoG-Aufzeichnungsfrist; ohne sie bleibt die Frist für diese Zeilen ungeprüft.

## Kalender-Abos: laufend aus einem Kalender

Unter **Kalender-Abos** (Menü der Importübersicht) abonnieren Sie einen
iCal-Kalender über seine Abo-Adresse (`https://` oder `webcal://`) oder wählen
als **Quelle** eine Kalenderverbindung der Organisation (CalDAV, Google
Kalender, Microsoft 365) – dieselben, die der Import aus dem Kalender anbietet.
WorkDiary liest ihn stündlich und übernimmt die Termine wie den Datei-Import:

- **Person:** Gehört der Kalender einer Person, gehören ihr alle Termine.
  Ohne Person entscheidet die E-Mail-Adresse von Organisator oder Teilnehmer.
- **Zeitraum:** Gelesen werden die vergangenen 30 Tage; ein Termin zählt erst,
  wenn er vorbei ist. Serientermine werden in diesem Zeitraum aufgelöst.
- **Nur Änderungen:** Unveränderte Termine (gleiche SEQUENCE und
  LAST-MODIFIED) überspringt WorkDiary; ein geänderter Termin ändert die
  Buchung. Antwortet der Server „unverändert“, entfällt der ganze Lauf.
- **Keine Dubletten:** Ein Termin, den ein Datei-Import schon gebucht hat
  (gleiche UID), wird übernommen und nicht noch einmal gebucht.
- **Löschungen:** Verschwindet ein gebuchter Termin aus dem Kalender, bleibt
  die Zeit stehen. In der Zuordnungs-Inbox erscheint ein Hinweis
  „Kalender-Abo“; löschen oder korrigieren Sie die Zeit dort bewusst.
- **Zuordnung:** Termine ohne passendes Projekt landen wie beim Datei-Import in
  der Zuordnungs-Inbox; der nächste Lauf versucht es erneut.

Die Adresse liegt verschlüsselt; angezeigt wird nur der Server. Adressen im
eigenen Netz brauchen **Kalender im eigenen Netz erlauben** und die Freigabe
durch den Betreiber. Mit **Jetzt abrufen** starten Sie einen Lauf sofort.

Eine Kalenderverbindung liefert bei jedem Lauf die Termine der vergangenen 30
Tage; die Regeln oben gelten unverändert. Wird die Verbindung getrennt oder
abgeschaltet, meldet das Abo einen Fehler, bis Sie eine andere Quelle wählen.
Zur Auswahl stehen nur die Verbindungen Ihrer Organisation.
