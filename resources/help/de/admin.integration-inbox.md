---
title: "Zuordnungs-Inbox"
topic: admin.integration-inbox
version: 1
keywords:
    - Integrations-Inbox
    - Importkonflikte
    - Datenabgleich
    - Abgleich
    - Datensätze zuordnen
    - Feldkonflikt
    - Sync-Konflikt
    - unbekannte Rufnummer
    - unbekannte Geräte
    - Klärungsfälle
    - Mapping
audience: []
related:
    - admin.integrations
    - admin.import
    - contacts.manage
    - finance.open-times
---

Die Zuordnungs-Inbox sammelt **eingegangene Importe, die sich nicht
automatisch zuordnen ließen** – aus angebundenen Systemen, dem CSV-Import und
dem E-Mail-Eingang. Nichts wird blind angelegt: Je Eintrag entscheiden Sie.

**Drei Fälle:**

- **Nicht zugeordnet** – zum eingehenden Datensatz gibt es keinen passenden
  Bestand.
- **Mehrdeutig** – mehrere Datensätze kommen in Frage.
- **Feld-Konflikt** – der Datensatz ist bekannt, aber der lokale und der
  entfernte Stand widersprechen sich. Beide Stände stehen nebeneinander.

**Entscheiden:** Einen Eintrag ordnen Sie einem bestehenden Datensatz zu,
legen ihn neu an oder verwerfen ihn. Bei einem Feld-Konflikt wählen Sie, ob
der lokale Stand bleibt oder der entfernte übernommen wird. Die Entscheidung
bleibt am Eintrag sichtbar (Zugeordnet, Neu angelegt, Lokal behalten, Remote
übernommen, Verworfen); über den Statusfilter rufen Sie erledigte Einträge
wieder auf.

**Gruppen:** Zusammengehörige Einträge stehen oben als Gruppe und werden in
einem Schritt entschieden:

- **Importierte Zeiten** eines fremden Projekts: Kunde, optional Endkunde und
  Projekt wählen oder neu benennen, dann die Gruppe buchen.
- **Unbekannte Geräte** aus der Fernwartung: an ein Gerät binden und buchen.
- **Unbekannte Rufnummern:** einem Kunden zuordnen; „Nummer dauerhaft merken“
  gilt für künftige Anrufe, eine geteilte Nummer nur für diesen einen.
- **Unbekannte Benutzer** eines Zeit-Imports: einem Benutzer zuordnen.
- **Serientermine** aus Kalendern: alle als Termine anlegen.
- **Bestellungen** aus dem B2B-Katalog: als Auftrag buchen.

„Einträge anzeigen“ klappt auf, was hinter einer Gruppe steckt. Eine Gruppe
lässt sich auch als Ganzes verwerfen.

**Filter:** Die Reiter trennen nach Quelle; die Zahl nennt die offenen
Einträge. Daneben filtern Sie nach Status, Fall und Entität. Sehr lange
Auswahllisten sind auf 1000 Einträge gekürzt – das Suchfeld oben rechts grenzt
die Zuordnungs-Auswahl ein.

**Zuordnungen verwalten:** WorkDiary merkt sich eine getroffene Zuordnung;
künftige Importe desselben Datensatzes laufen dann ohne Rückfrage durch. Unter
„Zuordnungen verwalten“ sehen und lösen Sie diese Verknüpfungen.

Die Seite steht Personen offen, die die Abrechnung verwalten dürfen.
