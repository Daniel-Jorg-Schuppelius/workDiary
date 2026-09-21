---
title: "Geplante Jobs"
topic: admin.scheduler
version: 1
audience:
    - admin
related:
    - admin.diagnostics
    - admin.operations
---

Diese Seite zeigt alle wiederkehrenden Hintergrund-Jobs der Plattform
– von Housekeeping über Integrations-Synchronisation bis zu
Fristen-Eskalationen.

**Registry statt Wildwuchs:** Alle planbaren Jobs stammen aus einer
zentralen Registry mit fest hinterlegtem **Standard-Plan**. Nur dort
registrierte Jobs erscheinen hier und lassen sich steuern – beliebige
Kommandos können Sie über diese Seite bewusst nicht einplanen.

**Übersicht:** Je Job sehen Sie den effektiven Plan samt **Herkunft**
(Standard, Einstellung oder manuelle Umplanung), den letzten Lauf mit
Ergebnis, einen Fehlerzähler und die nächste Fälligkeit. So erkennen
Sie auf einen Blick, ob ein Job hängt oder dauerhaft fehlschlägt.

**Filtern und Sortieren:** Über der Tabelle grenzen Sie die Liste nach
Name, Schlüssel oder Befehl, nach Einstufung, letztem Ergebnis und
Planquelle ein – etwa auf alle fehlgeschlagenen, noch nie gelaufenen
oder vom Betriebsfenster verschobenen Jobs. Der Schalter **Nur
pausierte** zeigt, was gerade ruht. Die Spalten Job, Letzter Lauf,
Nächste Fälligkeit und Fehler in Folge sortieren per Klick; Jobs ohne
Wert (noch nie gelaufen, pausiert) stehen dabei immer am Ende.

**Umplanen mit Leitplanken:** Jeder Job definiert, welche Kadenzen
für ihn erlaubt sind (z. B. stündlich oder täglich zu einer Uhrzeit).
Umplanen ist nur innerhalb dieser erlaubten Kadenzen möglich – so
kann ein kritischer Job nicht versehentlich auf einen unpassenden
Rhythmus gestellt werden. Freie Cron-Ausdrücke bleiben dem Betreiber
vorbehalten. Über **Zurücksetzen** kehrt ein Job jederzeit zu seinem
Standard-Plan zurück.

**Betriebsfenster:** Läuft der Server nicht rund um die Uhr – etwa
weil er nachts abschaltet –, hinterlegen Sie unter **Einstellungen**
Beginn und Ende des Betriebsfensters (Ende 00:00 steht für
Mitternacht). Jobs mit fester Uhrzeit außerhalb des Fensters laufen
dann gesammelt in den ersten beiden Betriebsstunden, in ihrer
ursprünglichen Reihenfolge und nie früher als geplant. Die Übersicht
zeigt die wirksame Zeit und vermerkt, aus welcher Uhrzeit ein Job
verschoben wurde; der Watchdog prüft gegen diese Zeit. Stündliche und
kürzere Takte bleiben unverändert.

**Pausieren und Testlauf:** Jobs lassen sich pausieren und später
fortsetzen – ein pausierter Job wird nicht mehr fällig, bleibt aber
in der Übersicht sichtbar. Ein **Testlauf** startet den Job sofort
außer der Reihe; zwischen zwei Testläufen gilt eine kurze Sperrfrist,
damit sich Läufe nicht überlappen.

**Laufnachweise:** Jeder Lauf wird mit Beginn, Dauer und Ergebnis
protokolliert. Die Nachweise werden für einen einstellbaren Zeitraum
aufbewahrt (standardmäßig 30 Tage) und danach automatisch bereinigt.

**Watchdog:** Ein eigener Überwachungsjob prüft den Scheduler selbst:
Bleiben fällige Läufe aus oder häufen sich Fehler, entstehen daraus
Betriebsaufgaben bzw. Warnungen. So fällt auch ein komplett stehender
Scheduler auf – nicht erst, wenn Auswertungen fehlen.

**Empfehlung:** Ändern Sie Pläne zurückhaltend und beobachten Sie nach jeder
Umplanung die nächsten Läufe. Ein dauerhaft erhöhter Fehlerzähler ist
ein Fall für die Diagnose, nicht fürs Pausieren.
