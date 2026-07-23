---
title: "Integrità del codice sorgente"
topic: admin.integrity
version: 1
audience:
    - admin
related:
    - admin.security
---

Il **monitoraggio dell’integrità del codice sorgente** (funzionalità 095)
rileva manipolazioni dei file dell’installazione: ogni file sorgente è
verificato in SHA-256 rispetto a una **baseline**, `vendor/` con un checksum
per pacchetto. L’hash radice della baseline fa parte del manifest di release
firmato — una baseline manipolata fallisce la verifica della firma.

- **Pannello di stato**: esito dell’ultima verifica, origine della baseline
  (release = firmabile, locale = rilevamento della deriva dal momento del
  congelamento) e hash radice.
- **Verifica ora** avvia una verifica in coda; il risultato appare
  nell’elenco dei rilievi e nella catena hash `audit_logs`.
- **Congela baseline** crea una nuova baseline locale — necessario dopo
  modifiche legittime (hotfix, `composer dump-autoload`), altrimenti ogni
  esecuzione segnala uno scostamento permanente.
- **Avvisi**: gli amministratori di piattaforma vengono notificati per
  rilievi nuovi o modificati; il cessato allarme segue la successiva
  verifica pulita.
- **Limiti**: la verifica rileva, non impedisce. Un attaccante con pieno
  controllo del server può attaccare anche il verificatore — restano
  raccomandati il monitoraggio esterno (`integrity:verify --json`, codice di
  uscita) e l’hardening del sistema operativo (mount in sola lettura, AIDE).
  `.env` e `storage/` non fanno volutamente parte della baseline.

L’esecuzione giornaliera si disattiva con `INTEGRITY_CHECK_ENABLED` e si
ripianifica dalla pagina dello scheduler.
