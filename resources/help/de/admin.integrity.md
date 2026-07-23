---
title: "Quelltext-Integrität"
topic: admin.integrity
version: 1
audience:
    - admin
related:
    - admin.security
---

Die **Quelltext-Integritätsüberwachung** (Feature 095) erkennt Manipulationen
an den Dateien der Installation: Jede Quelldatei wird per SHA-256 gegen eine
**Baseline** geprüft, `vendor/` als Prüfsumme je Paket. Der Root-Hash der
Baseline ist Teil des signierten Release-Manifests — eine manipulierte
Baseline fällt bei der Signaturprüfung auf.

- **Ampel**: Status des letzten Prüflaufs, Baseline-Quelle
  (Release = signierbar, Lokal = Drift-Erkennung ab Freeze-Zeitpunkt) und
  Root-Hash.
- **Jetzt prüfen** startet einen Prüflauf in der Warteschlange; das Ergebnis
  erscheint in der Befundliste und in der `audit_logs`-Hash-Kette.
- **Baseline einfrieren** erzeugt eine neue lokale Baseline — nach
  legitimen Änderungen (Hotfix, `composer dump-autoload`) nötig, sonst
  meldet jeder Lauf eine Dauer-Abweichung.
- **Alarme**: Bei neuem oder verändertem Befund werden Plattform-Admins
  benachrichtigt; die Entwarnung kommt nach dem nächsten sauberen Lauf.
- **Grenzen**: Die Prüfung erkennt, sie verhindert nicht. Wer den Server
  vollständig kontrolliert, kann auch die Prüfung angreifen — externes
  Monitoring (`integrity:verify --json`, Exit-Code) und OS-Härtung
  (read-only Mounts, AIDE) bleiben empfohlen. `.env` und `storage/` sind
  bewusst nicht Teil der Baseline.

Der tägliche Lauf ist über `INTEGRITY_CHECK_ENABLED` schaltbar und über die
Scheduler-Seite umplanbar.
