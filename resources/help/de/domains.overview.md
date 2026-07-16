---
title: "Domainverwaltung"
topic: domains.overview
version: 1
audience: []
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

**DNS:** Die Zone wird auf Anforderung gelesen; Records lassen sich
ersetzen oder gezielt ändern. Nach dem Schreiben erkennt das System
Abweichungen (DNS-Konflikt) und macht sie sichtbar, statt sie zu
überschreiben. MX-/SRV-Records verlangen eine Priorität.

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
Portfolio, Salden und Ebene und erlaubt die Kundenzuordnung je Subuser.
