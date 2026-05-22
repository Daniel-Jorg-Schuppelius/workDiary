# Status- und Aktionsglossar

Status: Aktiv (MVP-009, Issue #9) • Quelle:
[Feature 037 — Einheitliche Bedienung und UX-Konventionen](features/037-einheitliche-bedienung-ux-konventionen.md)
• Begleitend:
[UX-Pattern-Katalog](ux-pattern-katalog.md),
[Accessibility-Checkliste](accessibility-checkliste.md).

## Zweck

Dieses Glossar legt **verbindlich** fest, welche **Status-** und
**Aktionsnamen** in WorkDiary verwendet werden. Es ergänzt das generische
Aktions-Glossar (Pattern-Katalog §6) und die generischen Status-Tones (§7) um
**fachliche** Status pro Domäne (Auftrag, Zeit, Protokoll, Prozedur, Asset,
Klassifikation, Mandant) und fachliche Aktionsverben (Annehmen, Beginnen,
Abnehmen, Einreichen, Genehmigen, …).

**Regeln:**

1. **Eine Bedeutung — ein Name — ein Tone — ein Icon.** Synonyme sind
   verboten ("Erledigt" oder "Fertig" → immer "Abgeschlossen").
2. **Status sind Substantive** (Adjektive im Zustand: "Abgeschlossen",
   "Geplant"), **Aktionen sind Verben im Imperativ** ("Annehmen",
   "Abschließen", nicht "Annahme").
3. **Übersetzung erfolgt ausschließlich über `lang/de.json` bzw.
   `lang/de/…`.** Die Schlüssel folgen dem in den Tabellen genannten
   Stringwert; PHP-Enums benennen ihre Cases auf Englisch (z. B.
   `OrderStatus::InProgress`), die `label()`-Methode liefert den deutschen
   Wert.
4. Status werden grundsätzlich über `<x-status-badge>` mit `:tone` und
   `:label` dargestellt — nie über reine Farbflächen.

## 1. Generische Status-Tones

Aus dem UX-Pattern-Katalog (§7) übernommen, hier als kanonische Quelle für
Übersetzungen und Wiederverwendung:

| Tone        | Wann verwenden                          | Beispiel-Status                           |
| ----------- | --------------------------------------- | ----------------------------------------- |
| `primary`   | aktive Hauptaktion, "im Fluss"          | "Offen", "In Bearbeitung"                 |
| `secondary` | neutrale Sekundär-Aktion                | "Export", "Drucken"                       |
| `accent`    | hervorgehobene Spezialfunktion          | "Live", "Empfohlen"                       |
| `success`   | erfolgreich abgeschlossen / freigegeben | "Abgeschlossen", "Freigegeben", "Bezahlt" |
| `warning`   | benötigt Aufmerksamkeit, nicht kritisch | "Wartet auf Prüfung", "Bald fällig"       |
| `error`     | Fehler, gesperrt, abgelehnt             | "Abgelehnt", "Gescheitert", "Defekt"      |
| `info`      | informativ, neutraler Status            | "Geplant", "Pausiert"                     |
| `ghost`     | passiver / sehr neutraler Zustand       | "Entwurf", "Inaktiv"                      |
| `neutral`   | rein dekorativ                          | Zähler-Badges                             |

## 2. Generisches Aktions-Glossar

Verbindliche Standardaktionen (siehe Pattern-Katalog §6 für die volle
Tabelle mit Icons). Hier nur die kanonischen Labels:

| Aktion-Schlüssel    | Label              | Tone        |
| ------------------- | ------------------ | ----------- |
| `action.create`     | "Neu"              | `primary`   |
| `action.save`       | "Speichern"        | `primary`   |
| `action.cancel`     | "Abbrechen"        | `ghost`     |
| `action.edit`       | "Bearbeiten"       | `ghost`     |
| `action.show`       | "Anzeigen"         | `ghost`     |
| `action.delete`     | "Löschen"          | `error`     |
| `action.archive`    | "Archivieren"      | `warning`   |
| `action.restore`    | "Wiederherstellen" | `info`      |
| `action.approve`    | "Freigeben"        | `success`   |
| `action.lock`       | "Sperren"          | `warning`   |
| `action.unlock`     | "Entsperren"       | `info`      |
| `action.export`     | "Exportieren"      | `secondary` |
| `action.import`     | "Importieren"      | `secondary` |
| `action.attach`     | "Anhang"           | `ghost`     |
| `action.comment`    | "Kommentieren"     | `ghost`     |
| `action.search`     | "Suche"            | `ghost`     |
| `action.reset`      | "Zurücksetzen"     | `ghost`     |
| `action.impersonate`| "Vertreten"        | `warning`   |

**Verbotene Synonyme:** "Erstellen" (statt "Neu"), "Hinzufügen" (statt "Neu"
für Records — ok für Anhänge/Kommentare), "Übernehmen" (statt "Speichern"),
"Entfernen" (statt "Löschen"), "Abschicken"/"Senden" (statt "Speichern"),
"OK" (statt "Speichern" oder "Schließen"), "Zurück" (statt "Abbrechen").

## 3. Domänen-Status

### 3.1 Auftrag (`OrderStatus` / `DiaryEntry::status`)

| Enum-Case          | Label                       | Tone       | Bedeutung                                                   |
| ------------------ | --------------------------- | ---------- | ----------------------------------------------------------- |
| `Planned`          | "Geplant"                   | `info`     | Auftrag angelegt, noch nicht begonnen.                      |
| `Accepted`         | "Angenommen"                | `primary`  | Annahme bestätigt (Annahmedatum gesetzt).                   |
| `InProgress`       | "In Bearbeitung"            | `primary`  | Mindestens eine Zeit gebucht, noch nicht abgeschlossen.     |
| `WaitingCustomer`  | "Wartet auf Rückmeldung"    | `warning`  | Rückfrage an Kunden offen, Zeit pausiert.                   |
| `WaitingMaterial`  | "Wartet auf Material"       | `warning`  | Bestellung läuft, Bearbeitung pausiert.                     |
| `Completed`        | "Abgeschlossen"             | `success`  | Arbeiten beendet, Abnahme/Abrechnung noch offen.            |
| `Accepted_Final`   | "Abgenommen"                | `success`  | Vom Kunden abgenommen (Protokoll, Unterschrift).            |
| `Invoiced`         | "Berechnet"                 | `success`  | Abgerechnet (Rechnung gestellt).                            |
| `Cancelled`        | "Storniert"                 | `ghost`    | Auftrag zurückgezogen, keine weitere Arbeit.                |

### 3.2 Zeiteintrag (`TimesheetStatus`)

| Enum-Case   | Label              | Tone      | Bedeutung                                                 |
| ----------- | ------------------ | --------- | --------------------------------------------------------- |
| `Open`      | "Offen"            | `info`    | Erfasst, noch nicht eingereicht.                          |
| `Submitted` | "Eingereicht"      | `primary` | Vom Mitarbeitenden zur Prüfung freigegeben.               |
| `Approved`  | "Genehmigt"        | `success` | Von Teamleitung/Admin geprüft und freigegeben.            |
| `Rejected`  | "Abgelehnt"        | `error`   | Zurückgewiesen mit Begründung — Korrekturantrag möglich.  |
| `Locked`    | "Gesperrt"         | `ghost`   | Im Monatsabschluss eingefroren, nicht mehr änderbar.      |
| `Exported`  | "Exportiert"       | `ghost`   | Zusätzlich in externem System verbucht (Lohn, ERP).       |

### 3.3 Korrekturantrag (`TimeCorrectionStatus`)

| Enum-Case  | Label         | Tone      | Bedeutung                                            |
| ---------- | ------------- | --------- | ---------------------------------------------------- |
| `Pending`  | "Offen"       | `warning` | Eingereicht, Prüfung steht aus.                      |
| `Approved` | "Genehmigt"   | `success` | Übernommen, Zeitdaten angepasst.                     |
| `Rejected` | "Abgelehnt"   | `error`   | Nicht übernommen, Begründung Pflicht.                |
| `Withdrawn`| "Zurückgezogen"| `ghost`  | Vom Antragsteller zurückgenommen.                    |

### 3.4 Abnahmeprotokoll (`ProtocolStatus`)

| Enum-Case        | Label              | Tone      | Bedeutung                                                |
| ---------------- | ------------------ | --------- | -------------------------------------------------------- |
| `Draft`          | "Entwurf"          | `ghost`   | In Vorbereitung, nicht vorgelegt.                        |
| `InProgress`     | "In Bearbeitung"   | `primary` | Punkte werden erfasst (vor Ort).                         |
| `Submitted`      | "Vorgelegt"        | `info`    | Kunden zur Abnahme vorgelegt.                            |
| `AcceptedClean`  | "Abgenommen"       | `success` | Ohne offene Punkte abgenommen + Unterschrift.            |
| `AcceptedDefects`| "Abgenommen mit Mängeln" | `warning` | Abgenommen, offene Punkte mit Frist.                |
| `Rejected`       | "Abgelehnt"        | `error`   | Nicht abgenommen, Nacharbeit nötig.                      |

### 3.5 Protokollpunkt (`ProtocolItemStatus`)

| Enum-Case  | Label        | Tone      |
| ---------- | ------------ | --------- |
| `Pending`  | "Offen"      | `info`    |
| `Done`     | "Erledigt"   | `success` |
| `Defect`   | "Mangel"     | `warning` |
| `Critical` | "Kritisch"   | `error`   |
| `Skipped`  | "Übersprungen" | `ghost` |

### 3.6 Offene Punkte (`OpenIssueStatus`)

| Enum-Case  | Label       | Tone      |
| ---------- | ----------- | --------- |
| `Open`     | "Offen"     | `warning` |
| `InProgress` | "In Bearbeitung" | `primary` |
| `Resolved` | "Erledigt"  | `success` |
| `Overdue`  | "Überfällig"| `error`   |
| `Waived`   | "Verzichtet"| `ghost`   |

### 3.7 Prozedur-Ausführung (`ProcedureRunStatus`)

| Enum-Case  | Label              | Tone      | Bedeutung                                            |
| ---------- | ------------------ | --------- | ---------------------------------------------------- |
| `Planned`  | "Geplant"          | `info`    | Vorlage instanziiert, nicht begonnen.                |
| `Running`  | "In Bearbeitung"   | `primary` | Mindestens ein Schritt bearbeitet.                   |
| `Paused`   | "Unterbrochen"     | `warning` | Bewusst pausiert (Begründung Pflicht).               |
| `Completed`| "Abgeschlossen"    | `success` | Alle Pflichtschritte erledigt + Freigabe.            |
| `Aborted`  | "Abgebrochen"      | `error`   | Vor Abschluss beendet (Begründung + Audit).          |

### 3.8 Asset / Objekt (`AssetStatus`)

| Enum-Case  | Label         | Tone      |
| ---------- | ------------- | --------- |
| `Active`   | "Aktiv"       | `success` |
| `Reserved` | "Reserviert"  | `info`    |
| `Defect`   | "Defekt"      | `error`   |
| `Locked`   | "Gesperrt"    | `warning` |
| `Retired`  | "Ausgemustert"| `ghost`   |

### 3.9 Klassifikation (`ClassificationStatus`)

| Enum-Case  | Label        | Tone      |
| ---------- | ------------ | --------- |
| `Active`   | "Aktiv"      | `success` |
| `Deprecated`| "Veraltet"  | `warning` |
| `Disabled` | "Deaktiviert"| `ghost`   |

### 3.10 Mandant / Organisation (`OrganizationStatus`)

| Enum-Case       | Label                | Tone      |
| --------------- | -------------------- | --------- |
| `Trial`         | "Test"               | `info`    |
| `Active`        | "Aktiv"              | `success` |
| `Suspended`     | "Gesperrt"           | `warning` |
| `PendingDelete` | "Löschung vorgemerkt"| `error`   |
| `Archived`      | "Archiviert"         | `ghost`   |

### 3.11 Mitgliedschaft (`MembershipStatus`)

| Enum-Case  | Label        | Tone      |
| ---------- | ------------ | --------- |
| `Active`   | "Aktiv"      | `success` |
| `Invited`  | "Eingeladen" | `info`    |
| `Suspended`| "Gesperrt"   | `warning` |
| `Removed`  | "Entfernt"   | `ghost`   |

## 4. Fachliche Aktionen (Domäne)

### 4.1 Auftrag

| Aktion-Schlüssel        | Label                     | Tone      | Vorbedingung                                      |
| ----------------------- | ------------------------- | --------- | ------------------------------------------------- |
| `order.accept`          | "Annehmen"                | `primary` | Status `Planned`                                   |
| `order.start`           | "Beginnen"                | `primary` | Status `Accepted`                                  |
| `order.pause`           | "Pausieren"               | `warning` | Status `InProgress`                                |
| `order.resume`          | "Fortsetzen"              | `primary` | Status `WaitingCustomer` / `WaitingMaterial`       |
| `order.complete`        | "Abschließen"             | `success` | Status `InProgress`                                |
| `order.handover`        | "Abnahme starten"         | `primary` | Status `Completed`                                 |
| `order.markInvoiced`    | "Als berechnet markieren" | `success` | Status `Accepted_Final`                            |
| `order.cancel`          | "Stornieren"              | `error`   | Status ∈ {`Planned`, `Accepted`, `InProgress`}     |

### 4.2 Zeiteintrag

| Aktion-Schlüssel        | Label              | Tone      | Vorbedingung                       |
| ----------------------- | ------------------ | --------- | ---------------------------------- |
| `timesheet.submit`      | "Einreichen"       | `primary` | Status `Open`                      |
| `timesheet.approve`     | "Genehmigen"       | `success` | Status `Submitted`                 |
| `timesheet.reject`      | "Ablehnen"         | `error`   | Status `Submitted`                 |
| `timesheet.withdraw`    | "Zurückziehen"     | `ghost`   | Status `Submitted` (Eigentümer)    |
| `timesheet.lock`        | "Sperren"          | `warning` | Status `Approved`                  |
| `timesheet.unlock`      | "Entsperren"       | `info`    | Status `Locked` (nur Admin)        |
| `timesheet.requestCorrection` | "Korrektur beantragen" | `warning` | Status ∈ {`Approved`, `Locked`} |

### 4.3 Protokoll

| Aktion-Schlüssel        | Label                     | Tone      |
| ----------------------- | ------------------------- | --------- |
| `protocol.start`        | "Erfassung starten"       | `primary` |
| `protocol.submit`       | "Vorlegen"                | `primary` |
| `protocol.accept`       | "Abnehmen"                | `success` |
| `protocol.acceptDefects`| "Mit Mängeln abnehmen"    | `warning` |
| `protocol.reject`       | "Ablehnen"                | `error`   |
| `protocol.sign`         | "Unterschreiben"          | `primary` |
| `protocol.exportPdf`    | "Als PDF exportieren"     | `secondary` |

### 4.4 Prozedur

| Aktion-Schlüssel        | Label                     | Tone      |
| ----------------------- | ------------------------- | --------- |
| `procedure.start`       | "Beginnen"                | `primary` |
| `procedure.completeStep`| "Schritt abschließen"     | `success` |
| `procedure.skipStep`    | "Schritt überspringen"    | `warning` |
| `procedure.pause`       | "Unterbrechen"            | `warning` |
| `procedure.resume`      | "Fortsetzen"              | `primary` |
| `procedure.abort`       | "Abbrechen"               | `error`   |
| `procedure.complete`    | "Abschließen"             | `success` |

### 4.5 Asset / Objekt

| Aktion-Schlüssel | Label         | Tone      |
| ---------------- | ------------- | --------- |
| `asset.markDefect`| "Als defekt melden" | `error` |
| `asset.lock`     | "Sperren"     | `warning` |
| `asset.unlock`   | "Entsperren"  | `info`    |
| `asset.retire`   | "Ausmustern"  | `ghost`   |
| `asset.reactivate`| "Reaktivieren"| `success`|

### 4.6 Korrekturantrag

| Aktion-Schlüssel              | Label         | Tone      |
| ----------------------------- | ------------- | --------- |
| `timeCorrection.submit`       | "Einreichen"  | `primary` |
| `timeCorrection.approve`      | "Genehmigen"  | `success` |
| `timeCorrection.reject`       | "Ablehnen"    | `error`   |
| `timeCorrection.withdraw`     | "Zurückziehen"| `ghost`   |

### 4.7 Mitgliedschaft / Mandant

| Aktion-Schlüssel        | Label              | Tone      |
| ----------------------- | ------------------ | --------- |
| `member.invite`         | "Einladen"         | `primary` |
| `member.resendInvite`   | "Einladung erneut senden" | `info` |
| `member.suspend`        | "Sperren"          | `warning` |
| `member.reactivate`     | "Reaktivieren"     | `success` |
| `member.remove`         | "Entfernen"        | `error`   |
| `organization.suspend`  | "Sperren"          | `warning` |
| `organization.archive`  | "Archivieren"      | `ghost`   |
| `organization.requestDelete` | "Löschung vorbereiten" | `error` |

## 5. Übergangsmatrix (Beispiel: Auftrag)

```
Planned ──accept──► Accepted ──start──► InProgress ──complete──► Completed ──handover──► Accepted_Final ──markInvoiced──► Invoiced
                                          │   ▲                                                              
                                          │   │                                                              
                                          ▼   │                                                              
                                       WaitingCustomer / WaitingMaterial ──resume──┘                          
                                                                                                              
Planned / Accepted / InProgress ──cancel──► Cancelled
```

Vorbedingungen werden je Aktion in Policy + Service-Layer geprüft. Bei
Verletzung gibt es eine deutsche Fehlermeldung im Standard-Format
("Diese Aktion ist im Status … nicht möglich.").

## 6. Übersetzung und i18n

- Schlüssel-Konvention: `status.<domain>.<case>` und `action.<domain>.<verb>`.
- Werte in `lang/de.json` bzw. domänenspezifischen Dateien unter `lang/de/`.
- Englische Originale liegen in `lang/en.json` und folgen den deutschen
  Labels 1:1 (für Code-Reviewer).
- `Enum::label()` liest aus der Übersetzung (`__('status.order.completed')`),
  damit Statusnamen an einer einzigen Stelle gepflegt werden.

## 7. Verstöße erkennen

Beim Code-Review **rot kennzeichnen**:

- Hardcodierte Statustexte in Blade (`"Abgeschlossen"`) statt
  `$entry->status->label()`.
- Eigene Tone-Mischungen (`tone="lime"`, `class="bg-red-700"`).
- Buttons mit Synonymen ("Senden", "OK", "Erstellen") statt der hier
  definierten Standardlabels.
- Aktion ohne `policy()`-Check, die einen Statuswechsel auslöst.

Ein Linter-Regelvorschlag (Folge-MVP): `scripts/check-status-labels.php`
greppt nach hardkodierten Statustexten in `resources/views/` und meldet
Treffer.

## 8. Out-of-scope (MVP-009)

- Englische Übersetzung der Domänen-Labels (nur Schlüssel-Konvention
  vorgegeben).
- Workflow-Engine mit grafisch konfigurierbaren Übergängen.
- Versionierung der Status-Tabelle (geht später in `protocols` /
  `procedures`).
- Branchen-spezifische Erweiterungen (Handwerk vs. IT-Service) — kommen
  über MVP-033/MVP-034.

## 9. Änderungsverfahren

1. Neue Status oder Aktionen werden in **diesem Dokument** zuerst ergänzt.
2. Erst danach Enum-Case + `label()`-Übersetzung + Policy + UI.
3. Renaming bestehender Labels erzeugt eine Migration der gespeicherten
   Werte (Enum-Cases bleiben stabil, nur das deutsche Label ändert sich).
4. Bei Konflikt mit dem UX-Pattern-Katalog §6/§7 hat **diese Datei** für
   Domänen-Status Vorrang; für generische Aktionen gilt der Pattern-Katalog.
