---
title: "Dati dimostrativi"
topic: admin.demo-data
version: 1
audience:
    - admin
related:
    - admin.tenants
    - admin.handbook
    - admin.data-transfer
---

I dati dimostrativi popolano un'organizzazione con dati di esempio per
test e formazione, in base a un settore selezionabile. **Genera
(seed)** crea dati di esempio come clienti e voci del diario; la
panoramica indica se l'organizzazione è attualmente vuota. **Reimposta
(reset)** azzera un tenant demo, ma è consentito solo per tenant
contrassegnati come demo (`is_demo`) per proteggere i dati reali; su un
tenant demo il reset sovrascrive o rimuove i dati esistenti. Entrambe
le azioni richiedono permessi propri e vengono registrate nel log di
audit.
