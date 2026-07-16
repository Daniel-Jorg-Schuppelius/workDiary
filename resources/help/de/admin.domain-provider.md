---
title: "DomainReselling anbinden"
topic: admin.domain-provider
version: 1
audience:
    - admin
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
erscheinen nie in URLs, Logs oder Diagnosen. Optional trägst du einen
Standard-Benutzer (s_user) ein — den Kontext, unter dem Befehle eines
berechtigten Subusers laufen.

**Prüfen & Abgleichen:** „Verbindung prüfen“ testet die Zugangsdaten gegen
die API, ohne etwas zu verändern. „Abgleichen“ holt das aktuelle Portfolio
(Domains, Laufzeiten, Renewal-Modi, Reseller/Subuser) in die lokalen
Projektionen. Der Abgleich ist lesend und idempotent.

**Pilot bestätigen:** Nach einem erfolgreichen realen Test bestätigst du
den Piloten; erst danach kann die Verbindung produktiv geschaltet werden.
Solange der Pilot offen ist, meldet der Healthcheck „Pilot offen“.

**Zugangsdaten rotieren & Trennen:** Login/Passwort lassen sich jederzeit
neu setzen (Rotation), ohne die Verbindung neu anzulegen. Das Trennen
entfernt die Verbindung; die bereits gelesenen Projektionsdaten bleiben als
Nachweis erhalten.

**Zustände:** Eine Verbindung ist *Entwurf*, *aktiv* oder *gesperrt*.
Gesperrte Verbindungen führen zu einem sichtbaren Blockiert-Zustand im
Healthcheck — nie zu stillen Fehlaktionen.
