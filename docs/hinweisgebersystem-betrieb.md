# Hinweisgebersystem – Betrieb & Go-Live (Phase 6)

> Betriebsdokumentation und Härtungs-/Freigabe-Checkliste. Ergänzt das Konzept
> `docs/hinweisgebersystem.md`. Phase 6 ist überwiegend organisatorisch — der
> Code liefert die Werkzeuge (`whistleblowing:preflight`, `:demo-seed`), die
> eigentliche Freigabe ist eine bewusste, dokumentierte Entscheidung.

## 1. Konfiguration (.env)

| Variable | Zweck | Pflicht |
|---|---|---|
| `WHISTLEBLOWING_KEY` | Modul-KEK (base64 32 Byte), **getrennt von APP_KEY** | ja |
| `WHISTLEBLOWING_LOOKUP_KEY` | HMAC-Key für Postfach-Lookup (getrennt) | empfohlen |
| `WHISTLEBLOWING_SCANNER` | `none` (Default) oder `clamav` | – |
| `WHISTLEBLOWING_CLAMAV_BINARY` | z. B. `clamdscan` | bei clamav |
| `WHISTLEBLOWING_RETENTION_MONTHS` | Aufbewahrung nach Abschluss (Default 36) | – |
| `WHISTLEBLOWING_EMERGENCY_TTL_MINUTES` | Dauer einer Notfallfreigabe (Default 240) | – |
| `WHISTLEBLOWING_MAILBOX_SESSION_MINUTES` | Postfach-Sitzungsdauer (Default 30) | – |

Schlüssel erzeugen: `php -r 'echo base64_encode(random_bytes(32));'` — je einen
für `WHISTLEBLOWING_KEY` und `WHISTLEBLOWING_LOOKUP_KEY`.

**Session/Cookie** (Postfach läuft über ein Cookie): in Produktion
`SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=strict` (oder `lax`),
`http_only` aktiv.

## 2. Cron / Scheduler

| Command | Takt | Zweck |
|---|---|---|
| `whistleblowing:deadlines` | stündlich | Fristen-Erinnerungen (idempotent) |
| `whistleblowing:retention-review` | täglich | fällige Fälle → Aufbewahrungsprüfung (keine Auto-Löschung) |
| `whistleblowing:scan` | minütlich/5-min | Anhang-Quarantäne → clean/rejected (nur mit Scanner) |
| `audit:verify` | täglich | Integrität der Hash-Ketten |

## 3. Malware-Scanner (ClamAV)

`WHISTLEBLOWING_SCANNER=none` ist fail-safe: Anhänge bleiben in Quarantäne und
werden **nie** ausgeliefert. Für echten Betrieb ClamAV bereitstellen
(`clamd`/`clamdscan`), möglichst in einem gesandboxten Worker (das Parsen
attacker-kontrollierter Dateien ist Angriffsfläche). Metadaten-Scrubbing
(EXIF/Office/PDF) ebenfalls dort. Bis dahin: Anhänge nicht freigegeben.

## 4. Schlüsselverwaltung

- `WHISTLEBLOWING_KEY` ist der KEK; er wrappt die per-Fall-DEKs. Verlust = alle
  Fälle unlesbar. Sicher hinterlegen (Secrets-Manager), **nicht** in DB-Backups
  derselben Schutzklasse.
- Crypto-Shredding löscht einen Fall durch Vernichten seines DEK — auch in
  Backups wertlos.
- Zielbild: per-Org-Envelope mit KMS/Vault (siehe Konzept §10).

## 5. Backup & Restore

Nach einem Restore müssen zwischenzeitlich gelöschte Fälle erneut behandelt
werden: den **Tombstone-Ledger** (`whistleblowing_case_tombstones`) anwenden und
die betroffenen Fälle erneut crypto-shredden/löschen. (Ops-Prozess; ein
Abgleichs-Command ist noch offen.)

## 6. Incident Response

- `audit:verify` rot → mögliche Manipulation/Verlust: forensisch sichern,
  Ursache klären, Aufsicht/Datenschutz einbinden.
- Verdacht auf Kompromittierung des `WHISTLEBLOWING_KEY`: Vorfall behandeln;
  ein Key-Wechsel erfordert Re-Encryption (Zielbild) und ist KEIN Routinevorgang.
- Datenpanne mit Reporter-Bezug: DSGVO-Meldepflichten prüfen.

## 7. Readiness-Check

`php artisan whistleblowing:preflight` prüft Schlüssel (inkl. Trennung von
APP_KEY), Disk, Scanner, Retention und Session-Sicherheit. **Muss vor Go-Live
grün sein** (FAIL = blockierend, WARN = bewusst entscheiden).

Pilot-Daten: `php artisan whistleblowing:demo-seed <orgId> --count=N` (synthetische
Fälle über den echten Meldepfad; in Produktion nur mit `--force`).

## 8. Go-Live-Checkliste (vor Freigabe für echte Meldungen)

Technisch (durch Code/Ops abgedeckt):

- [ ] `whistleblowing:preflight` grün
- [ ] eigener `WHISTLEBLOWING_KEY` (≠ APP_KEY), sicher hinterlegt
- [ ] Scanner produktiv (oder bewusste Entscheidung, dass Anhänge bis dahin in
      Quarantäne bleiben)
- [ ] Cron-Jobs eingerichtet, `audit:verify` im Monitoring
- [ ] Session-Cookies sicher (secure/strict/http_only)
- [ ] WB-Tabellen aus Standard-Export ausgeschlossen (per Test abgesichert)

Organisatorisch/extern (NICHT durch Code leistbar — Phase-6-Kern):

- [ ] **Datenschutz-Folgenabschätzung (DSFA)** durchgeführt/freigegeben
- [ ] **Verfahrensordnung** und Datenschutzinformationen der Meldestelle freigegeben
- [ ] Rolle „Softwarelieferant/AVV" vertraglich geklärt (§24)
- [ ] Mindestens zwei Meldestellen-Beauftragte benannt + geschult; externer
      Ersatzkontakt für Interessenkonflikte
- [ ] **Berechtigungs- und Prozessreview** (Rolle `meldestelle`, Notfall-/
      Konfliktpfade) abgenommen
- [ ] **Unabhängiger Penetrationstest** (IDOR, Deanonymisierung, Upload, Krypto)
      ohne kritische offene Befunde
- [ ] Last-/Missbrauchstest (Rate-Limits, Bot-Schutz)
- [ ] Infrastruktur-Logs (Proxy/WAF/CDN/Hosting) IP-minimiert/dokumentiert
      (Anonymität ist nicht rein App-seitig)
- [ ] Aufbewahrungs-/Löschfristen pro Rechtsraum rechtlich bestätigt

Erst wenn beide Blöcke abgehakt sind, für echte Meldungen freigeben.
