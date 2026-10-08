---
title: "Report di supporto e diagnostica"
topic: admin.support
version: 2
keywords:
    - report diagnostico
    - informazioni di sistema
    - numero di versione
    - stato di salute
    - pacchetto di supporto
    - risoluzione problemi
    - segnalare un problema
    - report tecnico
    - info di debug
audience:
    - admin
    - geschaeftsfuehrung
    - support
related:
    - admin.security
    - admin.backups
    - admin.handbook
---

Il **Report di supporto** raccoglie lo stato tecnico della Sua
installazione, affinché il supporto possa analizzare un problema —
**senza che i dati dei clienti lascino l'azienda**.

Ecco come è strutturato il report:

- **Versioni & build**: versione dell'app, hash della build, versioni
  di PHP, Laravel e del database, oltre ai moduli e ai plugin attivi.
- **Stato di salute**: il risultato di `php artisan system:health`
  (database, migrazioni, storage, coda, APP_KEY, posta, licenza,
  backup) come blocco di stato compatto.
- **Errori dei plugin (7 giorni)**: solo ID del plugin, fase e numero —
  nessun testo di errore, nessun payload.
- **Operatività**: stato della coda e ultimi heartbeat di backup (solo
  conteggi e metadati come dimensione e momento).
- **Conteggi dei dati anagrafici**: numero di record per tabella — mai
  i contenuti.
- **Flag di configurazione**: quali moduli/funzionalità sono attivi,
  tipo di trasporto della posta, driver della coda. I segreti
  (APP_KEY, password, token) vengono sempre oscurati.

**La minimizzazione dei dati è la promessa centrale.** Il report
contiene esclusivamente campi tecnici esplicitamente autorizzati
(whitelist). Nomi dei clienti, dati personali, credenziali in chiaro e
segreti non compaiono mai.

Ecco come generare il report:

- **Pagina di amministrazione** «Report di supporto»: pacchetto ZIP
  (facoltativamente protetto da password), semplice file JSON oppure
  anteprima nel browser.
- **Riga di comando** (on-premise/CI): `php artisan support:report`
  emette il report su STDOUT, `--output=percorso.json` lo scrive in un
  file.

Ogni generazione viene registrata nel log di audit
(`support.reportGenerated`, `support.reportDownloaded`).
