---
title: "Toggl-Import"
topic: admin.toggl
version: 4
keywords:
    - Toggl Track
    - Zeiten importieren
    - Zeiteinträge übernehmen
    - Toggl CSV
    - Toggl API
    - Workspace-Export
    - Detailbericht hochladen
    - Mapping
    - Wechsel von Toggl
    - Benutzerzuordnung
    - Korrekturen zurückschreiben
    - neues Toggl
    - Toggl 2.0
    - Toggl Focus
    - Live-Synchronisation
audience:
    - admin
related:
    - admin.plugins
    - admin.import
    - admin.openproject
---

Der Toggl-Import übernimmt Zeiteinträge aus Toggl Track oder dem
neuen Toggl in WorkDiary. Standardmäßig liest der Import nur; optional lassen sich
Korrekturen zurückschreiben („Korrekturen zurückschreiben") und lokal
erfasste Zeiten übertragen („Zeit-Übertragung aktivieren").

Toggl Track oder das neue Toggl:

- Unter **Toggl-Produkt** in den Plugin-Einstellungen wählen Sie, woher die
  Zeiten kommen: **Toggl Track** oder das **neue Toggl** (vormals Toggl 2.0
  bzw. Toggl Focus). Gelesen, zurückgeschrieben und übertragen wird nur im
  gewählten Produkt.
- Für das neue Toggl hinterlegen Sie dessen API-Schlüssel (beginnt mit
  `toggl_sk_`). Organisations- und Workspace-ID sind optional; ohne sie
  ermittelt das Plugin die Workspaces über Ihr Toggl-Konto. Toggl erlaubt je
  Benutzer nur einen aktiven Schlüssel – ein neu erzeugter macht den
  bisherigen ungültig.
- Nutzen Sie die Live-Synchronisation zwischen Track und dem neuen Toggl,
  wählen Sie trotzdem nur eine Quelle: Den Abgleich zwischen beiden Produkten
  übernimmt Toggl selbst.
- Nach einem Wechsel ordnet der nächste Import bereits übernommene Einträge
  der neuen Quelle zu (gleicher Benutzer, Start und Ende), statt sie neu
  anzulegen; offene Fälle der Zuordnungs-Inbox ziehen mit. Das gilt für den
  Zeitraum des Abrufs. Ältere Einträge behalten ihre bisherige Verknüpfung,
  Korrekturen an ihnen gehen nicht mehr nach Toggl. Bei Bedarf einmal mit
  größerem Zeitraum importieren (`toggl:import --days=365`).
- Die Löschungserkennung betrachtet nur Einträge der gewählten Quelle.

Zwei Importwege:

- **API-Import**: zieht Zeiteinträge direkt über die Toggl-API. Sie
  hinterlegen ein API-Token und wählen einen Zeitraum.
- **Datei-/Export-Import**: lädt einen Toggl-Detailbericht (CSV) oder
  ein vollständiges Workspace-Export-Archiv hoch und importiert
  daraus.

Posteingang (unzugeordnete Einträge):

- Toggl-Kunden/-Projekte, die sich nicht automatisch einem
  WorkDiary-Kunden/-Projekt zuordnen ließen, sammeln sich hier –
  gruppiert mit Anzahl, Dauer und Zeitraum.
- Sie ordnen jede Gruppe einem bestehenden Kunden/Projekt zu, legen
  neue an oder verwerfen Einträge.
- Der erste Import erfolgt manuell; künftige Importe ordnen anhand
  gespeicherter Zuordnungen automatisch zu.

Zuordnungen (Mappings):

- Gespeicherte Verknüpfungen merken sich, welcher Toggl-Kunde bzw.
  welches Toggl-Projekt zu welchem WorkDiary-Kunden/-Projekt gehört.
- Zuordnungen lassen sich ändern (neu verknüpfen) oder löschen
  (zurück zur manuellen Zuordnung).

Risiken: Wiederholte Importe desselben Zeitraums können zu Dubletten
führen, wenn Quelldaten geändert wurden – prüfen Sie Zeitraum und
Posteingang vor dem Übernehmen. Das Verwerfen von Einträgen ist
endgültig.

Benutzerzuordnung (MVP-509):

- Jeder Toggl-Eintrag wird über die Benutzer-E-Mail des Workspaces dem
  passenden WorkDiary-Benutzer zugeordnet: zuerst gespeicherte
  Zuordnungen („Zuordnungen verwalten"), dann E-Mail-Gleichheit.
- Unbekannte oder nicht abrufbare Toggl-Benutzer werden nicht still auf
  den Hauptbenutzer gebucht — sie landen als offener Fall in der
  Zuordnungs-Inbox. Dort wählen Sie den Benutzer; die Wahl wird gemerkt
  und künftige Importe buchen automatisch richtig.
- Nur im ausdrücklich aktivierten Einbenutzer-Modus (Plugin-Einstellung)
  bucht der Import Einträge ohne Benutzersignal auf den
  Standard-Benutzer.
- Bereits falsch zugeordnete Alt-Importe repariert der Befehl
  `toggl:repair-entry-users` (erst Dry-Run, mit `--apply` schreiben);
  abgerechnete oder signierte Zeiten werden nie automatisch verändert.
- Beim einmaligen **Workspace-Import** (Ordner/ZIP oder API) wählen Sie
  die Benutzer-Zuordnung ausdrücklich: nur bestehenden Benutzern
  zuordnen (unbekannte Einträge bleiben sichtbar ungebucht und werden
  je E-Mail ausgewiesen), fehlende Benutzer pro E-Mail neu anlegen,
  oder Einbenutzer-Modus (alles auf den konfigurierten
  Standard-Benutzer — deutlich in Vorschau und Ergebnis benannt).
  Einzelne Toggl-Adressen lassen sich zusätzlich fest zuordnen; der
  Import ist idempotent und kann nach gepflegter Zuordnung einfach
  erneut ausgeführt werden.

Webhook (optional):

- Damit neue Einträge schneller ankommen, nennt die Importseite im
  Abschnitt **Webhook (optional)** die Adresse für eine
  Toggl-Webhook-Subscription. Legen Sie die Subscription in Toggl für den
  Workspace an und tragen Sie das dabei vergebene Geheimnis in den
  Plugin-Einstellungen unter **Webhook-Secret** ein; dort muss auch die
  **Workspace-ID** stehen.
- Der Webhook stößt nur denselben Import an, den der stündliche Abruf
  ausführt. Fällt er aus, holt der Abruf nach.
- Das neue Toggl bietet keine Webhooks. Bei aktiver Live-Synchronisation
  kann eine Subscription in Toggl Track den Abruf trotzdem früher anstoßen;
  gelesen wird weiterhin aus dem neuen Toggl.
- Den Standard-Benutzer des Einbenutzer-Modus wählen Sie in den
  Plugin-Einstellungen unter **Zeiten buchen für Benutzer** aus der Liste
  der Benutzer.
