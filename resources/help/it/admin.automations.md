---
title: "Automazioni"
topic: admin.automations
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.notification-rules
    - admin.webhooks
---

Le automazioni sono flussi basati su regole secondo lo schema
**evento → condizione → azione**: quando si verifica l'evento di
attivazione e le condizioni corrispondono, vengono eseguite le azioni
associate, sempre limitate alla propria organizzazione. Nella
panoramica puoi **creare** regole (condizioni e azioni per ora in
JSON), **attivarle/disattivarle**, consultare nel dettaglio le
esecuzioni recenti ed **eliminarle**. La **priorità** determina
l'ordine tra più regole corrispondenti (valore più basso per primo);
JSON non valido viene rifiutato. Per semplici notifiche sono spesso più
adatte le **regole di notifica**, per sistemi esterni i **webhook**.
