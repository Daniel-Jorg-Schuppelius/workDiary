---
title: "DomainReselling anbinden"
topic: admin.domain-provider
version: 2
keywords:
    - Domains verwalten
    - Domainverwaltung
    - Domain-Reseller
    - DNS
    - DNS-Einträge
    - Domainregistrierung
    - Domain verlängern
    - Domain-Laufzeit
    - Registrar
    - Domainportfolio
    - Subuser
audience:
    - admin
    - geschaeftsfuehrung
modules:
    - module.domain
related:
    - admin.plugins
    - admin.integrations
---

WorkDiary bindet ein **DomainReselling-Konto** je Organisation an und
verwaltet die enthaltenen Domains kontrolliert: Portfolio lesen, Kunden
zuordnen, Laufzeiten und DNS pflegen, Hochrisikoaktionen nur mit Freigabe.
Diese Seite richtet die Verbindung ein — die eigentliche Domainarbeit
läuft anschließend im Modul „Domains“.

**Umgebung wählen:** Jede Verbindung läuft entweder in *OT&E*
(Test-/Pilotumgebung) oder *produktiv*. Neue Konten beginnen in OT&E;
produktiv wird erst nach einem bestandenen, real bestätigten Piloten
freigeschaltet. So landet keine echte Registrierung versehentlich in einem
Test.

**Zugangsdaten:** Login und Passwort werden verschlüsselt gespeichert und
erscheinen nie in URLs, Logs oder Diagnosen. Optional tragen Sie einen
Standard-Benutzer (s_user) ein — den Kontext, unter dem Befehle eines
berechtigten Subusers laufen.

**Prüfen & Abgleichen:** „Verbindung prüfen“ testet die Zugangsdaten gegen
die API, ohne etwas zu verändern. „Abgleichen“ holt das aktuelle Portfolio
(Domains, Laufzeiten, Renewal-Modi, Reseller/Subuser) in die lokalen
Projektionen. Der Abgleich ist lesend und idempotent.

**Pilot bestätigen:** Nach einem erfolgreichen realen Test bestätigen Sie
den Piloten; erst danach kann die Verbindung produktiv geschaltet werden.
Solange der Pilot offen ist, meldet der Healthcheck „Pilot offen“.

**Zugangsdaten erneuern:** Über das Schlüssel-Symbol öffnet sich der
Dialog „Zugangsdaten erneuern“. Sie tragen ein neues Passwort ein; Login
und Standard-Benutzer bleiben unverändert, wenn Sie die Felder leer lassen.
WorkDiary prüft die neuen Zugangsdaten sofort — die Verbindung bleibt dabei
bestehen, mit allen Domains, Zuordnungen und Nachweisen.

**Trennen:** „Trennen“ löscht nichts. Das Passwort wird entfernt, der
Status wechselt auf *getrennt*, und kein Abgleich, kein Ereignisabruf und
kein Befehl erreicht den Anbieter mehr. Domains, Kontakte, DNS-Zonen, das
Befehlsjournal (wer hat was beim Anbieter geändert), die Buchungen und die
Domain-Bezüge Ihrer Reselling-Abos und Einkaufszeilen bleiben als Nachweis
erhalten. Zum Wiederverbinden hinterlegen Sie über „Zugangsdaten erneuern“
neue Zugangsdaten; nach bestandener Prüfung ist die Verbindung wieder aktiv.

**Zustände:** Eine Verbindung ist *Entwurf*, *aktiv*, *gesperrt* oder
*getrennt*. Gesperrte Verbindungen führen zu einem sichtbaren
Blockiert-Zustand im Healthcheck — nie zu stillen Fehlaktionen. Eine
getrennte Verbindung meldet der Healthcheck nicht als Störung; sie ist
bewusst außer Betrieb.
