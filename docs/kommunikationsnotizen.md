# Kommunikationsnotizen

Status: Aktiv (MVP-012, Issue #12) • Quelle:
[Feature 030 — Kommunikationsprotokoll](features/030-kommunikationsprotokoll.md).
• Begleitend:
[Auftrags-Timeline](auftrags-timeline.md),
[Auftrags-Lebenszyklus](auftrags-lebenszyklus.md),
[Status- und Aktionsglossar](status-aktionsglossar.md),
[UX-Pattern-Katalog](ux-pattern-katalog.md) §3.8.

## 1. Zweck

Strukturierte Erfassung jeder relevanten Kommunikation rund um einen
**Auftrag**, einen **Kunden** oder ein **Projekt** — auch wenn sie außerhalb
des Systems (Telefon, Vor-Ort-Gespräch, E-Mail) stattfand. Ziele:

- Bei Streitfällen nachweisen, **wer was wann mit wem** abgestimmt hat.
- Entscheidungen und Folgeaktionen (Frist, Verantwortung) belegen.
- Internes von externem klar trennen (Kundenportal vs. interne Notiz).
- Kommunikation als eigene Ereignisspur in der
  [Auftrags-Timeline](auftrags-timeline.md) sichtbar machen.

Abgrenzung zu **Kommentaren**: Kommentare (UX-Pattern-Katalog §3.8) sind
freie, asynchrone Diskussionsbeiträge im System. **Kommunikationsnotizen**
sind **dokumentierte Kommunikationsereignisse außerhalb oder rund um das
System**, mit Pflichtfeldern (Typ, Beteiligte, Ergebnis).

## 2. Datenmodell

Neue Tabelle `communication_notes` (polymorpher Bezug):

```sql
CREATE TABLE communication_notes (
    id                   BIGINT PRIMARY KEY AUTO_INCREMENT,
    organization_id      BIGINT NOT NULL,
    notable_type         VARCHAR(64) NOT NULL,        -- DiaryEntry|Customer|Project|Protocol|Asset
    notable_id           BIGINT NOT NULL,
    type                 VARCHAR(24) NOT NULL,        -- siehe §3
    direction            VARCHAR(12) NOT NULL,        -- inbound|outbound|internal
    occurred_at          TIMESTAMP NOT NULL,          -- Zeitpunkt der Kommunikation
    subject              VARCHAR(180) NOT NULL,
    body                 TEXT NOT NULL,               -- Verlauf/Inhalt (Markdown light)
    result               TEXT NULL,                   -- Ergebnis / Vereinbarung
    next_action          VARCHAR(180) NULL,           -- "Angebot bis Fr. raus"
    next_action_due_at   TIMESTAMP NULL,
    next_action_user_id  BIGINT NULL,                 -- verantwortlich
    visibility           VARCHAR(12) NOT NULL,        -- internal|customer (Default internal)
    confidential         BOOLEAN NOT NULL DEFAULT 0,  -- nur Org-Admin sichtbar
    created_by_user_id   BIGINT NOT NULL,             -- Erfasser
    created_at           TIMESTAMP NOT NULL,
    updated_at           TIMESTAMP NOT NULL,
    deleted_at           TIMESTAMP NULL,              -- Soft-Delete (siehe §8)
    INDEX idx_comm_target  (notable_type, notable_id, occurred_at DESC),
    INDEX idx_comm_org     (organization_id, occurred_at DESC),
    INDEX idx_comm_followup (next_action_user_id, next_action_due_at)
);

CREATE TABLE communication_note_participants (
    id                 BIGINT PRIMARY KEY AUTO_INCREMENT,
    communication_note_id BIGINT NOT NULL,
    user_id            BIGINT NULL,         -- intern (NULL = extern)
    customer_contact_id BIGINT NULL,        -- aus Kunden-Kontakten
    name               VARCHAR(120) NOT NULL, -- Anzeigename (auch bei ext.)
    role               VARCHAR(40) NULL,    -- "Auftraggeber", "Bauleitung", "Support L2"
    party              VARCHAR(12) NOT NULL,-- internal|customer|third-party
    UNIQUE KEY uniq_note_user (communication_note_id, user_id, customer_contact_id, name)
);
```

Anhänge: bestehende `attachments`-Polymorphie (`attachable_type =
CommunicationNote`).

## 3. Typen und Richtungen

### 3.1 `type` (Pflicht)

| Schlüssel    | Label             | Bedeutung                                          |
| ------------ | ----------------- | -------------------------------------------------- |
| `call`       | „Telefonat"       | Aktiv geführt oder entgegengenommen.               |
| `email`      | „E-Mail"          | Schriftlich, an/von Kunde oder Dritte.             |
| `meeting`    | „Vor-Ort-Gespräch" | Vor-Ort, mit Anwesenheitsliste.                   |
| `videocall`  | „Videokonferenz"  | Web-Meeting (mit Beteiligten).                     |
| `chat`       | „Chat / Messenger" | WhatsApp, Teams, Signal etc.                      |
| `internal`   | „Interne Rücksprache" | Nur intern.                                    |
| `decision`   | „Entscheidung"    | Verbindliche Festlegung mit Konsequenz.            |
| `letter`     | „Brief / Fax"     | Postalisch oder Fax (Foto/PDF als Anhang).         |
| `other`      | „Sonstige"        | Mit Pflichterklärung in `subject` / `body`.        |

### 3.2 `direction` (Pflicht)

| Schlüssel | Label              | Wann                                              |
| --------- | ------------------ | ------------------------------------------------- |
| `inbound` | „Eingehend"        | Kunde/Dritte hat sich gemeldet.                   |
| `outbound`| „Ausgehend"        | Wir haben Kontakt aufgenommen.                    |
| `internal`| „Intern"           | Nur zwischen Mitarbeitenden, kein externer Kontakt.|

Konsistenz: `type = internal` ⇒ `direction = internal` (Validation).
`visibility = customer` ⇒ `direction ∈ {inbound, outbound}`.

## 4. Sichtbarkeit und Vertraulichkeit

| Feld         | Standard    | Wirkung                                                          |
| ------------ | ----------- | ---------------------------------------------------------------- |
| `visibility = internal` | ✓ | Nur Mitarbeitende der Org. **Nicht** im Kundenportal sichtbar.   |
| `visibility = customer` | — | Erscheint im Kundenportal & in der Timeline mit `visibility=customer`. |
| `confidential = true`   | — | Nur Org-Admin + Erfasser sichtbar; Audit-Event `confidential.viewed` bei Aufruf. |

`confidential` setzt `visibility = internal` zwingend (Validation).

## 5. Polymorphe Bezüge

| `notable_type`  | UI-Einbindung                                              |
| --------------- | ---------------------------------------------------------- |
| `DiaryEntry`    | Eigene Karte „Kommunikation" auf der Auftragsdetailseite + Eintrag in Auftrags-Timeline. |
| `Customer`      | Karte auf Kunden-Detailseite; späterer Kunden-Timeline-Aggregat. |
| `Project`       | Karte auf Projekt-Detailseite; späterer Projekt-Timeline-Aggregat. |
| `Protocol`      | Karte am Abnahmeprotokoll (MVP-020 ff.) — dokumentiert z. B. Kundenrückmeldung. |
| `Asset`         | Karte am Objekt (MVP-035 ff.).                              |

Eine Notiz hat **genau einen** Bezug (kein N:M). Mehrfachzuordnung
(z. B. „Auftrag X und Kunde Y") wird über zwei separate Notizen abgebildet
oder über das implizite „Notiz an Auftrag X" + „Kunde Y ist Auftraggeber".

## 6. Aktionen

Aus dem [Status-/Aktionsglossar](status-aktionsglossar.md) wird das Glossar
um eine neue Domäne ergänzt:

| Aktion-Schlüssel               | Label              | Tone      | Vorbedingung                                  |
| ------------------------------ | ------------------ | --------- | --------------------------------------------- |
| `communication.add`            | „Notiz erfassen"   | `primary` | Permission `communication.create` am Bezug.   |
| `communication.edit`           | „Bearbeiten"       | `ghost`   | Erfasser oder Org-Admin, < 24 h alt.          |
| `communication.publishToCustomer` | „Für Kunden freigeben" | `success` | `visibility=internal` und nicht confidential. |
| `communication.markConfidential` | „Vertraulich markieren" | `warning` | Nur Org-Admin.                            |
| `communication.delete`         | „Löschen"          | `error`   | Org-Admin; erzeugt Soft-Delete + Audit.       |
| `communication.completeFollowup` | „Folgeaktion erledigt" | `success` | `next_action_due_at` gesetzt.             |

## 7. Permissions

Neue Permission-Schlüssel (Spatie):

| Permission                       | Wer                                              |
| -------------------------------- | ------------------------------------------------ |
| `communication.view.own`         | Eigene Bezüge (z. B. eigene Aufträge).           |
| `communication.view.team`        | Teammitglieder.                                  |
| `communication.view.organization`| Org-Admin / Teamleitung.                         |
| `communication.create`           | Mitarbeitende (Default ja).                      |
| `communication.update`           | Erfasser, < 24 h; Org-Admin jederzeit.           |
| `communication.delete`           | Nur Org-Admin.                                   |
| `communication.confidential.manage` | Nur Org-Admin (markieren/sehen).              |

Customer-Portal-Rolle (`kunde`) erhält **keine** dieser Permissions, sieht
aber Einträge mit `visibility = customer` an ihrem zugewiesenen Auftrag
read-only über die Auftrags-Timeline.

## 8. Audit

Pflichtige Audit-Events (in `audit_logs.event`):

- `communication.created`
- `communication.updated`  (mit `changes`-Diff)
- `communication.deleted`  (Soft-Delete, mit Begründung)
- `communication.published`  (visibility: internal → customer)
- `communication.confidential.set` / `.unset`
- `communication.confidential.viewed`  (Zugriff durch nicht-Erfasser)
- `communication.followup.completed`

Diese Events erscheinen in der [Auftrags-Timeline](auftrags-timeline.md)
unter dem Typ `communication.added` (Liste-Anzeige; Diffs aufklappbar).

## 9. UI

### 9.1 Auftragsdetailseite

Neue Sektion „**Kommunikation**" zwischen Kommentaren (§3.8) und Historie
(§3.6 Pkt. 7) gemäß [UX-Pattern-Katalog](ux-pattern-katalog.md):

- Liste neueste zuerst, je Eintrag: Icon (Typ), Richtung, Datum, Aktor,
  Beteiligte, Betreff (Link), Status-Pille (`internal`/`customer`/
  „vertraulich").
- Header-Aktion: `<x-icon-btn icon="add_comment" label="Notiz erfassen"
  tone="primary"/>` öffnet Modal.
- Folgeaktionen-Bereich oben („Offene Folgeaktionen") wenn `next_action`
  offen.

### 9.2 Erfassungs-Modal

Pflichtfelder: Typ, Richtung, Zeitpunkt (Default `now`), Betreff, Body.
Optional: Beteiligte, Ergebnis, Folgeaktion (mit Frist + Verantwortlich),
Anhänge, Sichtbarkeit (Schalter „Für Kunden sichtbar"). Vertraulich-Toggle
nur für Org-Admin.

Formular folgt dem Pattern §3.4 (Pflichtfelder oben,
Speichern/Abbrechen/Löschen-Reihenfolge).

### 9.3 Folgeaktionen-Dashboard (Folge-MVP)

Globale Sicht „Meine offenen Folgeaktionen aus Kommunikation" auf dem
Dashboard, sortiert nach `next_action_due_at`.

## 10. Validation

- `subject` 3–180 Zeichen.
- `body` 1–8000 Zeichen.
- `occurred_at` ≤ `now() + 5 min` (kein Zukunfts-Backdating sinnvoll).
- `next_action_due_at` > `occurred_at`.
- `type = internal` ⇒ `direction = internal` ⇒ `visibility = internal`.
- `confidential = true` ⇒ `visibility = internal`.
- `direction = inbound` mit `participants` ohne `party = customer/third-party`
  → Warnung (kein Fehler), da theoretisch ein Mitarbeitender anrufen könnte.

## 11. Akzeptanzkriterien

1. Auftragsdetailseite zeigt Sektion „Kommunikation" mit Pflichtspalten
   (Typ, Richtung, Datum, Aktor, Beteiligte, Betreff, Sichtbarkeit).
2. Modal erfasst alle Pflichtfelder; Folgeaktion optional, aber konsistent
   validiert.
3. Vertrauliche Notizen sind ausschließlich Org-Admin + Erfasser sichtbar;
   jeder andere Aufruf erzeugt `confidential.viewed`.
4. Kunden-Portal sieht nur `visibility=customer`-Notizen über die
   Auftrags-Timeline.
5. Folgeaktionen mit Frist erscheinen oben auf der Auftragsdetailseite
   und sind „erledigen"-fähig (Audit).
6. Audit deckt alle 7 Events aus §8 ab; Tests prüfen Permissions,
   Sichtbarkeit, Validation und Audit pro Aktion.
7. Anhänge funktionieren wie an `DiaryEntry`/`Protocol` (gleiche Komponente).

## 12. Out-of-scope (MVP-012)

- Automatische E-Mail-Eingang-/Ausgang-Synchronisierung (IMAP/SMTP).
- KI-Zusammenfassung von Telefonaten.
- Anbindung an Telefonanlagen / CTI.
- Mehrfachzuordnung einer Notiz zu mehreren Objekten.
- Wiedervorlagen-Engine über Folgeaktionen hinaus (separates Modul).

## 13. Folge-MVPs

- **MVP-013** Fallakte — Sektion „Kommunikation" als feste Hauptsektion.
- **MVP-014** Globale Suche — Volltext über `subject` + `body` (mit
  Rechteprüfung).
- **MVP-039** Kundenanalyse — Anzahl Kommunikationen / offene
  Folgeaktionen pro Kunde.
- **MVP-051** In-App-Hilfe — Hilfe-Overlay zum Notiz-Modal.
- Folgemodul: Vollwertiges Tickets-/Wiedervorlagen-System mit
  Eskalationsketten.

## 14. Änderungsverfahren

1. Neue Typen oder Richtungen werden zuerst hier in §3 ergänzt.
2. Dann Glossar (§4 ergänzen), Migration, Service, UI.
3. Renaming bestehender Typ-Schlüssel erzeugt eine Migration der
   `type`-Werte; alte Schlüssel werden bis zur nächsten Major als Alias
   gemappt.
