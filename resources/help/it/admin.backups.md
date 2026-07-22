---
title: "Backup e monitoraggio operativo"
topic: admin.backups
version: 2
audience:
    - admin
related:
    - admin.handbook
    - admin.security
---

WorkDiary monitora i backup esterni tramite un **heartbeat**: il job di
backup segnala l'esito di ogni esecuzione all'endpoint
`POST /admin/backup/heartbeat` (token Bearer impostato con la variabile
d'ambiente `BACKUP_HEARTBEAT_TOKEN`; senza token l'endpoint è
disattivato), trasmettendo `manifest_sha256`, `size_bytes`, `source` e
`occurred_at`; ogni ricezione viene registrata nell'audit trail. I
backup non si registrano quindi manualmente nell'interfaccia: al primo
heartbeat la sorgente compare automaticamente nella pagina
**Backup & Restore**, che mostra l'ultimo backup per sorgente e lo
segna come scaduto oltre la freschezza configurata
(`BACKUP_HEARTBEAT_FRESHNESS_HOURS`, 26 h di default). I test di
ripristino si annotano lì con **Registra test di ripristino**; il
ripristino vero e proprio avviene volutamente fuori da WorkDiary. Il
comando `php artisan system:health` verifica database, migrazioni,
storage, coda, APP_KEY, mail e licenza senza modificare dati. Per il
ripristino salva sempre anche l'**APP_KEY**, altrimenti i campi
crittografati vanno persi, e testa regolarmente i restore su un
ambiente separato.
