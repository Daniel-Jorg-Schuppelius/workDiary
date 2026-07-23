# Betriebsdokumentation

Betreiber-Handbücher, die mit dem Code ausgeliefert werden. Entwicklungs- und
Architekturdoku liegt bewusst getrennt im Schwester-Repo
**WorkDiary-Architecture**.

- [systemdienste.md](systemdienste.md) — Systemdienst-Installer
  (`scripts/install-system.sh`): Cron/Queue-Worker/Reverb/Integritätswächter/
  fail2ban nach der Web-Installation in einem Schritt einrichten.
- [backup-restore.md](backup-restore.md) — Backup-Strategie, Heartbeat,
  verschlüsselte Cloud-Backupziele (`BACKUP_MASTER_KEY`), Restore-Anleitung
  und Restore-Tests.
