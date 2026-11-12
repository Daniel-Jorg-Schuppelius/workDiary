---
title: "Diagnostica"
topic: admin.diagnostics
version: 2
keywords:
    - stato del sistema
    - health check
    - controllo sistema
    - risoluzione problemi
    - email di prova
    - verificare invio email
    - coda
    - scheduler
    - cron job
audience:
    - admin
related:
    - admin.security
    - admin.metrics
    - admin.handbook
---

La diagnostica fornisce un rapporto sullo stato del sistema con
semaforo per ogni area verificata, tra cui **versione**, **licenza**,
**coda**, **scheduler**, **mail**, **storage** e **backup**. Ogni area
riceve uno stato (OK, avviso, critico o sconosciuto) e il rapporto è
disponibile anche in JSON per l'elaborazione automatica. Può inoltre
inviare una **mail di prova** al suo indirizzo per verificare la
configurazione di posta. Consultazione e test vengono registrati nel
log di audit e richiedono permessi dedicati; gli indicatori operativi
si trovano in **Metriche**.

**Accesso di monitoraggio:** per un monitoraggio esterno crei un accesso in «Accesso di monitoraggio». Il monitoraggio recupera il rapporto senza accesso all’indirizzo /api/monitoring/diagnostics e invia il token nell’intestazione «Authorization: Bearer …». Il token viene mostrato una sola volta e salvato solo come impronta; la revoca lo blocca subito. Il rapporto non contiene nomi né indirizzi, come licenziatario o indirizzo del mittente. Le richieste vengono registrate al massimo una volta all’ora per accesso.
