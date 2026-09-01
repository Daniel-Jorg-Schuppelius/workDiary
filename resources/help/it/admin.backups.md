---
title: "Backup & monitoraggio operativo"
topic: admin.backups
version: 3
audience:
    - admin
schema: process
related:
    - admin.handbook
    - admin.security
    - admin.import
---

## Scopo e contesto

WorkDiary sorveglia i backup esterni tramite **heartbeat**: il job di
backup segnala il successo alla piattaforma dopo ogni esecuzione. I
backup non si registrano a mano — al primo heartbeat la sorgente
appare automaticamente su **Backup & ripristino**. Backup e
ripristino veri e propri avvengono volutamente fuori da WorkDiary.

## Prerequisiti

- Un job di backup esterno (p. es. il `backup.sh` incluso).
- Il token nella variabile d'ambiente `BACKUP_HEARTBEAT_TOKEN` —
  senza token l'endpoint è disattivato.
- Diritti di amministrazione per la pagina **Backup & ripristino**.

## Procedura consigliata

1. Configurare il job e fargli inviare l'heartbeat:
   `POST /admin/backup/heartbeat` con token Bearer (fuori dal normale
   circuito di login, con rate limit); vengono trasmessi
   `manifest_sha256`, `size_bytes`, `source` e `occurred_at`.
2. Controllare la sorgente su **Backup & ripristino**: la pagina
   mostra l'ultimo backup per sorgente e la marca **in ritardo** se
   l'heartbeat più recente supera la freschezza configurata
   (`BACKUP_HEARTBEAT_FRESHNESS_HOURS`, predefinito 26 h).
3. Testare regolarmente i ripristini su un ambiente separato e
   annotarli con **Registra test di ripristino**.
4. Controllare lo stato del sistema: `php artisan system:health`
   verifica database, migrazioni, storage, coda, APP_KEY, mail e
   licenza (exit code 0/1, non modifica dati — ideale per cron/CI).

## Esempio pratico

Il cron notturno salva alle 23 e invia l'heartbeat. Quando un
intervento sullo storage ferma il job per due notti, la sorgente
passa a «in ritardo» — l'admin lo vede al mattino in dashboard, prima
che si rischi una perdita di dati.

## Errori tipici

- **Salvare solo il database:** senza l'**APP_KEY** i campi cifrati
  (PII, 2FA, casi di protezione dati) sono persi per sempre.
- **Non testare mai i ripristini:** un backup senza ripristino
  verificato è una speranza, non un concetto.
- **Confondere heartbeat e backup:** l'heartbeat segnala solo il
  successo — non sostituisce né backup né conservazione.

## Effetti e prossimi passi

Ogni heartbeat viene salvato e verbalizzato come evento di audit
`backup.heartbeatReceived`; i ritardi emergono nel monitoraggio. Poi:
programmare un test di ripristino, mettere `system:health` nel cron e
leggere nel manuale di amministrazione le note sul disaster recovery.
