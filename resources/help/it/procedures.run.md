---
title: "Eseguire una procedura"
topic: procedures.run
version: 2
keywords:
    - compilare checklist
    - seguire istruzioni operative
    - avviare procedura
    - passaggi obbligatori
    - registrare deviazione
    - controfirma
    - doppio controllo
    - prova di backup
    - confermare passaggio
audience: []
related:
    - protocols.create
---

Una procedura si avvia su un **incarico** o su un **asset**. I
**passaggi obbligatori** vanno eseguiti nell'ordine definito; i passaggi
di **backup** richiedono la prova (hash + dimensione o link esterno) e
i passaggi a **quattro occhi** la conferma di una **seconda persona**
con il ruolo adatto. Le deviazioni vanno registrate con motivazione e
azione conseguente: non è possibile saltare senza motivazione. Alla
chiusura viene fissata la versione del modello usata; modifiche
successive del modello **non** hanno effetto retroattivo.

## Passaggi a quattro occhi

La barra **Approvazione a quattro occhi** del passaggio mostra lo stato:
non ancora richiesta, richiesta, presa in carico o controfirmata.

1. Chi esegue il passaggio clicca su **Richiedi seconda persona**. I
   responsabili di team ricevono una notifica con il link
   all'esecuzione.
2. Un'altra persona autorizzata apre l'esecuzione e clicca su
   **Controfirmare come seconda persona**. Chi ha richiesto non può
   controfirmare.
3. Solo allora il passaggio si può completare.

Una controfirma si può **revocare** con una motivazione (permesso
«Revoca l'approvazione a quattro occhi», di norma responsabili o
amministrazione). Il passaggio torna «aperto», la richiesta resta attiva
e un'altra persona può controfirmare. Richiesta, controfirma e revoca
sono registrate con persona e orario nella cronologia dell'esecuzione.
