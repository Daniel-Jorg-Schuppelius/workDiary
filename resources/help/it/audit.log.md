---
title: "Registro di audit"
topic: audit.log
version: 2
keywords:
    - traccia di audit
    - registro modifiche
    - log attività
    - cronologia modifiche
    - chi ha modificato cosa
    - tracciabilità
    - a prova di manomissione
    - catena hash
    - GoBD
    - conformità
audience:
    - admin
related:
    - admin.security
    - admin.handbook
    - privacy.overview
---

Il registro di audit (`/audit`) è il protocollo di controllo a prova di
revisione delle modifiche e delle azioni eseguite nel sistema. Le voci
sono **append-only** (solo aggiunta) e concatenate tra loro tramite una
**catena di hash SHA-256** (GoBD); non vengono mai scritte in forma
grezza e non possono essere modificate o eliminate a posteriori.

**Filtri**: l'elenco può essere limitato per

- **Azione** (ad es. creato, modificato, eliminato, archiviato,
  ripristinato e gli eventi di importazione),
- **Tipo** dell'oggetto interessato (tra cui voce di diario, commento,
  cliente, fornitore, esecuzione di importazione, sequenza numerica),
- **Utente** e
- **Periodo** (tramite il filtro data globale).

Per ogni voce vede il momento, l'utente che l'ha originata, l'azione,
l'oggetto, le modifiche concrete e l'indirizzo IP.

**Verificare l'integrità**: la catena di hash viene verificata con il
comando da console `php artisan audit:verify`. Il comando convalida la
concatenazione e, in caso di interruzione, termina con codice di uscita
1 – ideale per cron/CI. Mantenga il comando sempre verde; un'interruzione
indica una manipolazione o un errore nei dati. Con `--chain` è
possibile verificare in modo mirato una singola catena (`audit_logs`
oppure `organization_audit_logs`).

Nota: il registro di audit è uno strumento di sola lettura. Mostra le
operazioni, ma non modifica di per sé alcun dato.
