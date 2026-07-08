---
title: "Report di supporto e diagnostica"
topic: admin.support
version: 1
audience:
    - admin
related:
    - admin.security
    - admin.backups
    - admin.handbook
---

Il **report di supporto** raccoglie lo stato tecnico
dell'installazione per l'analisi dei problemi, senza che dati dei
clienti lascino la casa: versioni e build, stato di salute, errori dei
plugin (solo conteggi), dati operativi e flag di configurazione, con i
secret sempre redatti. La minimizzazione dei dati è la promessa
centrale: solo campi tecnici in whitelist, mai dati personali o
credenziali. Genera il report dalla pagina admin «Report di supporto»
(ZIP con password opzionale, JSON o anteprima) oppure da riga di
comando con `php artisan support:report`; ogni generazione viene
registrata nel log di audit.
