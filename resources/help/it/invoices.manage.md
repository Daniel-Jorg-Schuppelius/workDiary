---
title: "Fatture e documenti"
topic: invoices.manage
version: 2
audience: []
related:
    - contacts.manage
    - projects.manage
    - finance.transfers
    - travel-expenses.manage
---

La panoramica fatture gestisce le fatture locali e i documenti collegati;
quale canale sia quello principale dipende dall'organizzazione e
dall'integrazione di fatturazione in uso. Prima della creazione verifica
cliente, periodo di prestazione, riferimento al progetto, posizioni, dati
fiscali e indirizzo del destinatario. Le bozze possono essere integrate, ma
i documenti inviati, registrati o trasferiti a sistemi esterni non vanno mai
modificati in silenzio: in caso di errore usa il processo previsto di storno
o correzione invece di sovrascrivere numeri o importi.

Da MVP-462 il dialogo di creazione mostra un'**anteprima** delle
posizioni generate (numero, durata nei formati orologio e decimale,
importo, avviso di registrazioni tardive) non appena cliente e periodo
sono selezionati. Le singole registrazioni possono essere **escluse**
dal ciclo tramite casella — restano aperte e riappaiono nel ciclo
successivo. Sulla fattura, le **registrazioni di tempo di origine** di
ogni posizione sono espandibili; le quantità in ore appaiono anche nel
formato orologio (ad es. 1,50 h = 1:30 h).

**Lettera di sollecito:** il sollecito genera un PDF dedicato (livello
1 = promemoria di pagamento) con riepilogo del credito, spese di
sollecito facoltative e termine di pagamento; l'e-mail contiene la
lettera e la fattura originale in allegato. Non viene creato alcun
nuovo documento contabile.
