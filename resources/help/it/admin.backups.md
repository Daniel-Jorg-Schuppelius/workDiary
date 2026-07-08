---
title: "Backup e monitoraggio operativo"
topic: admin.backups
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.security
---

WorkDiary monitora i backup esterni tramite un **heartbeat**: il job di
backup segnala l'esito di ogni esecuzione all'endpoint
`POST /admin/backup/heartbeat` (token Bearer), trasmettendo tra l'altro
**hash del manifest (SHA-256)** e **dimensione**; ogni ricezione viene
registrata nell'audit trail. Non esiste ancora un'interfaccia di
monitoraggio: configura un allarme esterno per heartbeat mancanti. Il
comando `php artisan system:health` verifica database, migrazioni,
storage, coda, APP_KEY, mail e licenza senza modificare dati. Per il
ripristino salva sempre anche l'**APP_KEY**, altrimenti i campi
crittografati vanno persi, e testa regolarmente i restore su un
ambiente separato.
