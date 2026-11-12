---
title: "Eseguire una procedura"
topic: procedures.run
version: 4
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

## Prova di backup

Un passaggio di tipo «Registrazione di backup» mostra la scheda **Prova di
backup**.

1. **Registra prova di backup** apre la finestra di dialogo: sistema
   salvato, ambito, momento del backup, archiviazione (**Allegato** con
   file oppure **Archiviazione esterna** con posizione e dimensione) e
   metodo di verifica. Ciò che il modello stabilisce non è selezionabile;
   dimensione minima ed età massima vengono controllate al salvataggio.
2. Con il metodo **Confronto checksum** Lei indica la checksum attesa. Per
   un file caricato il sistema ricalcola la checksum e conferma da sé la
   prova; con l'archiviazione esterna la persona che verifica inserisce la
   checksum ricalcolata.
3. **Test di ripristino** e **Conferma della direzione** vengono
   confermati da una **seconda persona** con il permesso «Verifica un
   record di backup» tramite **Conferma prova**, non dalla persona che ha
   registrato la prova.
4. **Rifiuta prova** richiede un motivo; in seguito registri una nuova
   prova (**Sostituisci prova**).

Solo con una prova confermata il passaggio può essere completato. Un
passaggio successivo che richiede un backup preliminare diventa così
disponibile. Se nessun backup è possibile, lo registri come
**scostamento**. La posizione di un backup esterno è visibile solo con il
permesso di visualizzare l'archiviazione esterna dei backup.

## Qualifica

Se un passaggio indica una **Qualifica**, può completarlo solo chi possiede
oggi questa qualifica in corso di validità (sigla o nome come riportato
nelle qualifiche dei dipendenti). Lo stesso vale per la seconda persona
quando il modello richiede una qualifica per la controfirma.

## Scostamento critico e accettazione del rischio

Uno scostamento compare sul passaggio con tipo, gravità, motivo e misura
di follow-up. Uno scostamento **critico** blocca la chiusura
dell'esecuzione finché non ne è derivato né un punto aperto né un ordine
successivo. Chi dispone del permesso «Accetta uno scostamento critico» apre
**Accetta rischio** sullo scostamento e indica un motivo. Persona, momento
e motivo sono riportati nella cronologia dell'esecuzione; il report
«Deviazioni di procedura» mostra la decisione.

## Procedure critiche

L'esecuzione di un modello con livello di rischio «Critica» può essere
conclusa solo se almeno un passaggio a quattro occhi è completato e
controfirmato.

## Avvio su un asset

I modelli che il designer assegna per categoria di asset, prodotto o
contratto SLA compaiono nella scheda dell'asset, nel riquadro
**Procedure**, sotto «Procedure previste per questo asset:». Se l'asset è
bloccato, si avvia solo un modello con l'opzione **Può essere eseguita su
asset bloccati**.

**Eseguire di nuovo:** se scegliete questa azione successiva per una deviazione, WorkDiary avvia subito un’esecuzione ripetuta dello stesso modello sullo stesso ordine o asset. La deviazione vi rimanda e una deviazione critica non blocca più la chiusura. Se l’asset è bloccato, non nasce alcuna esecuzione; il motivo è nella cronologia.
