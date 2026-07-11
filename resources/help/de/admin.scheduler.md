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
Kommandos kannst du über diese Seite bewusst nicht einplanen.

**Übersicht:** Je Job siehst du den effektiven Plan samt **Herkunft**
(Standard, Einstellung oder manuelle Umplanung), den letzten Lauf mit
Ergebnis, einen Fehlerzähler und die nächste Fälligkeit. So erkennst
du auf einen Blick, ob ein Job hängt oder dauerhaft fehlschlägt.

**Umplanen mit Leitplanken:** Jeder Job definiert, welche Kadenzen
für ihn erlaubt sind (z. B. stündlich oder täglich zu einer Uhrzeit).
Umplanen ist nur innerhalb dieser erlaubten Kadenzen möglich – so
kann ein kritischer Job nicht versehentlich auf einen unpassenden
Rhythmus gestellt werden. Freie Cron-Ausdrücke bleiben dem Betreiber
vorbehalten. Über **Zurücksetzen** kehrt ein Job jederzeit zu seinem
Standard-Plan zurück.

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

**Empfehlung:** Ändere Pläne zurückhaltend und beobachte nach jeder
Umplanung die nächsten Läufe. Ein dauerhaft erhöhter Fehlerzähler ist
ein Fall für die Diagnose, nicht fürs Pausieren.
