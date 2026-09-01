---
title: "JTL-Wawi anbinden"
topic: admin.jtl-wawi
version: 1
audience:
    - admin
modules:
    - module.lager
related:
    - admin.integrations
    - admin.plugins
---

WorkDiary bindet JTL-Wawi als **führende Warenwirtschaft** an: Artikel
(Vater-/Kindartikel), Lager und Bestände kommen aus JTL; WorkDiary
liest sie und übergibt eigene Bestandsbewegungen kontrolliert zurück.

**Betriebsarten:** Eine *OnPremise*-Wawi wird über ihre lokale
API-Instanz angebunden (im JTL-Administrator anlegen, Standard-Port
5883). Steht die Wawi im eigenen Netz, muss die Freigabe privater
Adressen ausdrücklich aktiviert werden — diese Freigabe wird
auditiert. Das *Cloud-Gateway* nutzt Client-ID/Secret und Tenant-ID
aus dem JTL-Partnerportal.

**App-Registrierung (OnPremise):** Zuerst in JTL-Wawi „Admin >
App-Registrierung“ öffnen, dann hier die Registrierung starten und die
App in der Wawi freigeben. Der API-Schlüssel wird **nur einmal**
ausgegeben und verschlüsselt gespeichert — er erscheint nie in Logs
oder Diagnosen.

**Zuordnungen:** Nach der ersten Synchronisation ordnest du die
JTL-Lager den WorkDiary-Lagern zu (für Buchungen 1:1). Artikel werden
über SKU und GTIN automatisch zugeordnet; unklare Fälle landen in der
Integrations-Inbox und werden dort entschieden — WorkDiary legt nie
automatisch Artikel an.

**Bestandsführung:** Unter „Bestandsführung“ wählst du, wer die
Bestände führt: *lokal* (WorkDiary), *extern* (JTL führt, WorkDiary
bucht über die Outbox zurück) oder *nur lesen*. Der Wechsel zurück auf
„lokal“ übernimmt die JTL-Bestände als Eröffnungs-Inventur.

**Beta-Hinweis:** Die JTL-Wawi-API läuft derzeit als Beta-/
Pilotprogramm. Nach dem offiziellen Release kann sie editionsabhängig
und kostenpflichtig werden; eine entfallene Lizenz führt zu einem
sichtbaren Blockiert-Zustand, nie zu stillen Fehlbuchungen.
