# Abnahme: Unterschrift, Zeitstempel, PDF

Status: Aktiv (MVP-022, Issue #22) • Quellen:
[Feature 003 — Abnahmeprotokolle](features/003-dokumentation-abnahmeprotokolle.md),
[Feature 012 — Kundenportal/Freigaben](features/012-kundenportal-freigaben.md).
• Aufbauend auf:
[Protokoll-Datenmodell](protokoll-datenmodell.md) (MVP-020),
[Protokollpunkt-Typen](protokollpunkt-typen.md) (MVP-021).

## 1. Zweck

Die Abnahme eines Protokolls **verbindlich, prüfbar und
streitfest** abschließen: Unterschrift erfassen, Zeitstempel sichern,
PDF erzeugen und an Auftrag/Fallakte anhängen.

## 2. Signaturwege

| Methode      | Wann                                                      | Sicherheits-Level |
| ------------ | --------------------------------------------------------- | ----------------- |
| `onscreen`   | Vor Ort am Tablet/Smartphone, Finger/Stift.               | mittel            |
| `portal`     | Kunde unterschreibt im Customer-Portal (Login).           | hoch              |
| `emailLink`  | Signaturlink per E-Mail an Kunden (Token, 7 Tage gültig). | mittel            |
| `paper`      | Papier-Abnahme; Unterschrift als Foto + Vermerk im Protokoll. | niedrig       |

Für `acceptance` und `handover` ist Mindest-Level der Org-Konfiguration
hinterlegt (Default: `mittel`).

## 3. Signatur-Erfassung

### 3.1 Vor-Ort (`onscreen`)

- Komponente `<x-signature-pad>` (Canvas) gemäß
  [UX-Pattern-Katalog](ux-pattern-katalog.md) §3.4.
- Pflichtfelder im selben Dialog:
  - `signer_name` (Pflicht, ≥ 2 Zeichen)
  - `role` (`customer`/`contractor`/`witness`)
  - `signer_email` (optional, aber für Quittungs-Mail)
  - Bestätigungs-Checkbox „Hiermit nehme ich die dokumentierte Leistung
    ab" (Pflicht; Text aus Org-Konfig).
- Submit zeichnet PNG auf Storage und erstellt
  `protocol_signatures`-Zeile.

### 3.2 Portal (`portal`)

- Kunde sieht Protokoll im
  [Customer-Portal](security/datenschutzseite-konzept.md) read-only.
- Aktion „Abnehmen" → derselbe Dialog wie 3.1 mit Vor-Login der Person.
- IP + User-Agent + Auth-Methode werden in `protocol_signatures`
  gespeichert.

### 3.3 E-Mail-Link (`emailLink`)

- Erzeugung eines signierten Tokens (SHA-256 + Org-Secret), 7 Tage
  gültig.
- Link `https://…/sign/{token}` öffnet read-only Protokoll +
  Signatur-Pad ohne Login.
- Token-Lifecycle in `protocol_signature_tokens` (id, protocol_id,
  expires_at, used_at, signed_signature_id).

### 3.4 Papier (`paper`)

- Mitarbeiter fotografiert unterschriebenes Papier-Protokoll → Foto als
  `attachment` am Protokoll.
- Manueller Eintrag in `protocol_signatures` mit `method=paper` und
  `signature_image_path` → Foto-Pfad.
- Geringeres Vertrauen, in Berichten klar gekennzeichnet.

## 4. Hash & Integritätsprüfung

Beim Signieren wird ein **Inhalts-Hash** berechnet und in
`protocol_signatures.hash` gespeichert:

```
hash = SHA256(
    canonical_json(protocol) ||
    canonical_json(protocol_items[]) ||
    file_hashes(attachments[]) ||
    signer_name || role || signed_at
)
```

`canonical_json` ist eine determinierte Serialisierung (Schlüssel
alphabetisch, keine Whitespaces). Beim PDF-Render wird der Hash im
Footer ausgegeben („Prüfsumme: a1b2c3…").

Eine nachträgliche Änderung am `signed`-Protokoll ist laut
[Datenmodell](protokoll-datenmodell.md) §5 nicht erlaubt — eine neue
Revision würde einen neuen Hash erzeugen.

## 5. PDF-Ausgabe

### 5.1 Aufbau

```
Header:     Logo + Organisation + Titel + Typ
Meta-Block: Datum, Ort, Auftrag-Nr., Kunde, Erfasser, Revision
Inhalt:     state_initial / state_final
Items:      Tabelle nach Reihenfolge mit Label, Wert, Result, Note,
            Foto-Vorschau (mit Caption "vor"/"nach", siehe MVP-023)
Mängel:     Aggregierte Liste der defect-Items mit Severity
Offene P.:  Liste verlinkter offener Punkte (MVP-024)
Signaturen: pro Unterschrift Bild + Name + Rolle + Zeit + Methode
Footer:     Hash, Generiert-am, Generiert-von, Seite n/m
```

### 5.2 Erzeugung

- Service `ProtocolPdfRenderer` nutzt **wkhtmltopdf** oder
  **Browserless/Puppeteer** (Entscheidung pro Deployment;
  Adapter-Interface).
- PDF-Pfad nach
  [security/adr-attachment-paths.md](security/adr-attachment-paths.md):
  `storage/protocols/{org}/{year}/{protocol_id}_r{revision}_{hash8}.pdf`.
- Idempotent: bei gleichem Hash + Revision wird vorhandene Datei
  zurückgegeben.
- Wird beim ersten `protocol.signed`-Event automatisch erzeugt und
  als `attachments`-Eintrag am Protokoll registriert
  (`title = "Abnahmeprotokoll v{r}"`).

### 5.3 Sprache

PDF-Sprache = `protocols.subject.organization.locale` (Fallback `de`).
Datums-/Zahlenformat entsprechend Locale.

## 6. Audit-Events

- `protocol.signatureRequested` (E-Mail-Link erstellt)
- `protocol.signatureLinkOpened`
- `protocol.signed` (mit signature_id, role, method, hash)
- `protocol.pdfRendered` (mit Pfad, Hash, Dauer ms)
- `protocol.pdfDownloaded` (mit User-ID / Customer-ID)

## 7. Permissions

| Permission                       | Wer                                       |
| -------------------------------- | ----------------------------------------- |
| `protocol.sign.internal`         | Teamleitung / Org-Admin.                  |
| `protocol.sign.customer.onscreen`| Mitarbeitender (im Beisein) + Kunde.      |
| `protocol.sign.customer.portal`  | Kunde (Portal-Login).                     |
| `protocol.sign.customer.emailLink` | Inhaber des Tokens.                     |
| `protocol.pdf.download`          | Sehende Rolle (Kunde: nur eigene).        |

## 8. Akzeptanzkriterien

1. Alle vier Methoden funktional: `onscreen`, `portal`, `emailLink`,
   `paper`.
2. `protocol_signatures` enthält pro Unterschrift Name, Rolle, Zeit,
   Methode, IP/UA (außer paper), Hash, optionales PNG.
3. Hash ist reproduzierbar: identischer Protokoll-Inhalt + Signer →
   identischer Hash.
4. PDF wird beim Signieren automatisch erzeugt, am Protokoll als
   Anhang sichtbar, in der Auftrags-Timeline als Event verlinkt.
5. PDF-Footer zeigt Hash, Revision und Generierungs-Daten.
6. Token aus `emailLink` läuft nach 7 Tagen / einmaliger Nutzung ab.
7. Audit-Events §6 vollständig; Download getrennt vom Render.
8. Datenschutz: PDFs liegen unter ADR-konformen Pfaden; Download nur
   per signed URL.

## 9. Out-of-scope (MVP-022)

- Qualifizierte elektronische Signatur (QES) / eIDAS-konform —
  Folge-MVP (eigener Anbieter, Vertrag).
- Mehrstufige Vier-Augen-Workflows — MVP-028.
- Re-Signing (Kettenrevision mit Vorgängerhash) — Folge.

## 10. Folge

- MVP-023 Foto-Strukturen.
- MVP-024 Offene Punkte.
- MVP-028 Zweite Person / Freigeber.
- Folge: QES-Integration; Re-Signing.
