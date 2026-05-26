# Restore-Test-Protokoll

> Vorlage gemäß [`docs/backup-restore.md` §6.3](backup-restore.md). Pro
> Durchführung eine Kopie im Org-Dokumentenbereich (oder als PDF im
> Compliance-Ordner) ablegen.

| Feld              | Wert                                  |
| ----------------- | ------------------------------------- |
| Datum / Uhrzeit   | YYYY-MM-DD HH:MM (Zeitzone)           |
| Durchgeführt von  | _Name + Rolle_                        |
| Backup-Stempel    | `YYYYMMDD_HHMMSS` (Dateinamen-Suffix) |
| Manifest-SHA256   | `…64-Hex…`                            |
| Test-Umgebung     | _Hostname / VM / Container-Tag_       |
| WorkDiary-Version | `git describe` oder Release-Tag       |

## 1. Restore

- [ ] DB-Dump entpackt und eingespielt (`gunzip … | mysql …`)
- [ ] Storage-Tar entpackt (`tar -C $APP_DIR -xzf storage_*.tar.gz`)
- [ ] `.env` zurückgespielt (`chmod 600`)
- [ ] `php artisan optimize:clear`
- [ ] `php artisan migrate --force` (sofern neuere Version)
- [ ] `php artisan workdiary:backup:rotate-token` (neuer Heartbeat-Token)

## 2. Smoke-Test

- [ ] Login mit bekanntem Test-Admin erfolgreich
- [ ] `php artisan workdiary:diagnostics --format=text` ohne Critical
- [ ] Stichprobe Tagebucheintrag öffnen, Anhang sichtbar
- [ ] Stichprobe Stundenzettel sichtbar

## 3. Vergleichszählungen

| Tabelle         | Pre-Backup (Manifest) | Post-Restore | Δ   |
| --------------- | --------------------: | -----------: | --- |
| `diary_entries` |                       |              |     |
| `time_entries`  |                       |              |     |
| `attachments`   |                       |              |     |
| `protocols`     |                       |              |     |
| `invoices`      |                       |              |     |

Abweichungen erklären (z. B. ungesicherte In-Flight-Daten).

## 4. Audit

- [ ] Audit-Event `backup.restoreTested` ausgelöst (manuell oder per
      Skript; siehe `AuditLog` mit `event = 'backup.restoreTested'`).
- [ ] Protokoll abgelegt unter: _Pfad / Link_.

## 5. Ergebnis

- [ ] Erfolgreich
- [ ] Mit Auflagen (Beschreibung): _…_
- [ ] Fehlgeschlagen — Folge-Ticket: _…_

Unterschrift / Freigabe: ********\_\_\_\_********
