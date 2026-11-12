---
title: "Domainverwaltung"
topic: domains.overview
version: 5
keywords:
    - Domain registrieren
    - Domain verlängern
    - DNS bearbeiten
    - DNS-Zone
    - Nameserver ändern
    - MX-Eintrag
    - Domaintransfer
    - Transfersperre
    - Inhaberwechsel
    - Domain kündigen
    - Domainablauf
    - Domain-Reseller
audience: []
modules:
    - module.domain
related:
    - admin.domain-provider
    - contacts.manage
---

Das Modul verwaltet die Domains eines verbundenen DomainReselling-Kontos
als nachvollziehbares Portfolio — von Kundenzuordnung und Laufzeit über
Nameserver/DNS bis zu Renewal, Transfer und Buchungen. Die Verbindung
selbst wird in der Administration unter „DomainReselling“ eingerichtet.

**Portfolio:** Die Übersicht listet alle Domains mit Kunde, Ablauf,
Renewal-Modus, Registrar, Transfersperre und Aktualität. Die Kennzahlen
oben zeigen Ablauf in 90 Tagen, riskante Modi (Autoexpire/Autodelete),
Domains ohne Kundenzuordnung sowie Sync-/Konfliktfälle. Gefiltert wird nach
Domainname, TLD, Aktualität, Renewal-Modus und Ablaufkorridor.

**Kundenzuordnung:** Jede Domain lässt sich einem Kunden zuordnen (intern
über die Sqid-Kennung). Nicht zugeordnete Domains bleiben in der Kennzahl
sichtbar, damit das Portfolio vollständig gepflegt wird.

**Detailansicht:** Die Domainseite bündelt Übersicht, Nameserver & DNS,
Rechnungen, Timeline und Aktionen. „Aktualisieren“ gleicht den
Providerzustand für genau diese Domain ab.

**DNS:** Die Zone wird auf Anforderung gelesen. In der Oberfläche lassen sich
einzelne Einträge hinzufügen und löschen. „Zone ersetzen“ gibt es erst, wenn die
Zone gelesen wurde: Der Dialog ist mit ihrem Stand vorbelegt und fragt vor dem
Absenden nach, weil ein Vollersatz jeden Eintrag löscht, der im Formular fehlt.
Einträge, deren Typ das Formular nicht bearbeiten kann, bleiben unverändert
erhalten; ein leeres Formular wird abgewiesen. Nach dem Schreiben erkennt das System Abweichungen (DNS-Konflikt) und
macht sie sichtbar, statt sie zu überschreiben. MX-/SRV-Records verlangen eine
Priorität.

**Registrieren:** Vor der Registrierung wird die Verfügbarkeit geprüft.
Eine Registrierung braucht einen Kunden, ein Owner-Contact-Handle,
mindestens zwei Nameserver und eine ausdrückliche Preisbestätigung.

**Laufzeit & Transfer:** Renewal-Modus setzen, manuell verlängern,
Transfersperre setzen/lösen und Transfer-In anstoßen laufen als
protokollierte Befehle mit Statusverlauf (Entwurf → gesendet → bestätigt).

**Hochrisikoaktionen:** Löschen, Push an einen anderen Benutzer, Trade
(Inhaberwechsel), Transfer-Out und Objektzuweisung sind gesperrt: Sie
verlangen die erneute Eingabe des Domainnamens und eine Vier-Augen-Freigabe.
Eingereichte Aktionen erscheinen zur Freigabe oder Ablehnung; der
Providerzustand wird nach der Ausführung abgeglichen (Konflikte werden
markiert).

**Buchungen & Berichte:** Die Buchungssicht ist ein read-only Journal —
keine steuerliche Rechnung. Die Berichte bündeln Ablaufkorridor,
Renewal-Kostenprognose, Kundenzuordnung, Risiko-Modi und Rechnungsabdeckung.

**Reseller/Subuser:** Die Reseller-Sicht zeigt die Subuser-Hierarchie mit
Portfolio, Salden und Ebene und erlaubt die Kundenzuordnung je Subuser. Je
Subuser lässt sich die Kundennummer beim Anbieter eintragen; damit heißen die
Monatsbelege aus dem Buchungsjournal im Reselling-Register wie die Rechnungen
des Portals („Kundennummer-MM-JJJJ“).

**Unklare Befehle:** Bleibt die Antwort des Anbieters unvollständig, steht der Befehl auf „unklar“ und wird nie wiederholt. Der nächste Sync-Lauf liest den Zustand der Domain beim Anbieter: Registrierung, Renewal-Modus, Transfersperre und Verlängerung werden so bestätigt, als fehlgeschlagen erkannt oder als Konflikt markiert. Für Transfers, DNS und Hochrisikoaktionen gibt es keinen Statusabruf — prüfen Sie diese beim Anbieter und schließen Sie sie im Befehlsjournal mit Begründung von Hand ab.

**Kosten und Guthaben:** In den Organisationseinstellungen legen Sie eine Kostenschwelle für Verlängerungen und ein Mindestguthaben fest. Die Domainverwaltung meldet anstehende Verlängerungen ab der Schwelle und Anbieterkonten, deren Guthaben samt Kreditrahmen die Verlängerungen der nächsten Tage nicht deckt — ohne Deckung löscht der Anbieter die Domains zum Ausfalldatum.

**Renewal-Modus:** Wählbar sind die drei Modi, die der Anbieter dokumentiert:
Auto-Renew, Auto-Expire und Auto-Delete. Ein früher gespeicherter Wert „Einmal
verlängern“ gilt nach dem Update als unbekannt; der nächste Sync liest den
tatsächlichen Modus beim Anbieter.

## Abgleichsstand einer Domain

Läuft ein Befehl an den Anbieter, steht die Domain auf **Ausstehend**. Bleibt sein Ausgang unklar oder weicht der Anbieter nach dem Schreiben ab, steht sie auf **Unklar** bzw. **Konflikt** und erscheint in der Kachel **Abgleichsprobleme**; ein Abgleich überschreibt das nicht, solange der Befehl offen ist. Nach dem Abschluss – automatisch oder von Hand – steht die Domain auf **Veraltet**, bis der nächste Abgleich sie neu liest.
