---
title: "Dati dimostrativi"
topic: admin.demo-data
version: 2
audience:
    - admin
related:
    - admin.tenants
    - admin.handbook
    - admin.data-transfer
---

I dati dimostrativi popolano un'organizzazione con dati di esempio per
test, formazione e presentazioni, in base a un **settore campione**
selezionabile: ogni profilo di settore ne ha esattamente uno, con propri
clienti, progetti, un incarico principale completo, materiale, asset,
verbale firmato ed esecuzione di procedura. **Crea organizzazione demo**
(amministratore della piattaforma) genera un'organizzazione nuova e
isolata; **Genera (seed)** popola l'organizzazione corrente ancora vuota;
**Reimposta (reset)** cancella e ricrea i dati di un tenant demo
mantenendo settore e ambito. Senza la spunta «Mostrare l'ambito
completo», la demo segue la raccomandazione dei moduli del profilo e crea
dati solo per i moduli attivi. La finestra indica in anticipo con quale
licenza funzionerà la demo: se l'istanza può rilasciare licenze,
l'organizzazione riceve una licenza a tempo; altrimenti vale la licenza
dell'installazione, e senza entrambe la demo funziona nel piano Free con
la maggior parte dei moduli bloccata. Il reset è consentito solo per
tenant contrassegnati come demo (`is_demo`) e lì sovrascrive i dati
esistenti; con un periodo di conservazione configurato, lo scheduler
elimina definitivamente le organizzazioni demo scadute. Tutte le azioni
richiedono permessi propri e vengono registrate nel log di audit.
