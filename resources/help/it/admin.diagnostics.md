---
title: "Diagnostica"
topic: admin.diagnostics
version: 1
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
disponibile anche in JSON per l'elaborazione automatica. Puoi inoltre
inviare una **mail di prova** al tuo indirizzo per verificare la
configurazione di posta. Consultazione e test vengono registrati nel
log di audit e richiedono permessi dedicati; gli indicatori operativi
si trovano in **Metriche**.
