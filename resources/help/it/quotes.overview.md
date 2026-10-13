---
title: "Offerte"
topic: quotes.overview
version: 7
keywords:
    - creare un'offerta
    - preventivo
    - proposta commerciale
    - inviare offerta
    - accettazione online
    - accettazione parziale
    - validità dell'offerta
    - conferma d'ordine
    - convertire in fattura
    - voci opzionali
    - nuova versione dell'offerta
    - documenti collegati
    - catena dei documenti
    - numero offerta
    - offerta a Lexoffice
    - numero conferma
    - bolla di consegna
    - consegna parziale
    - numero bolla di consegna
audience: []
modules:
    - module.vertrieb
related:
    - invoices.manage
---

Le offerte attraversano un ciclo di vita fisso: bozza → approvazione →
invio → decisione del cliente → trasformazione in fattura. La
panoramica filtra per cliente e stato (bozza, approvata, inviata,
accettata, parzialmente accettata, rifiutata, scaduta).

**Bozza:** un'offerta viene creata con cliente, progetto facoltativo,
termine di vincolo e testo delle condizioni. Le posizioni (descrizione,
quantità, unità, prezzo unitario, aliquota fiscale) possono essere
aggiunte, modificate e rimosse solo in bozza; i totali vengono
ricalcolati automaticamente. Singole posizioni possono essere
contrassegnate come facoltative — se il cliente rifiuta solo queste,
resta comunque un'accettazione integrale.

**Approvazione e invio:** dopo l'approvazione, l'offerta viene
contrassegnata come inviata. Al contempo nasce un link di accettazione
per il cliente: viene mostrato in chiaro esattamente una volta, viene
salvato solo un valore di verifica — il link va quindi copiato subito e
spedito con il messaggio dell'offerta (e-mail o lettera). Dall'invio lo
stato è immutabile nel merito; le modifiche passano esclusivamente
attraverso una nuova versione che rimanda alla precedente. L'intera
catena delle versioni resta visibile sull'offerta.

**Decisione del cliente:** tramite il link il cliente può consultare
l'offerta senza autenticarsi e accettarla, accettarla parzialmente
(selezione di singole posizioni) o rifiutarla. In alternativa,
l'operatore documenta internamente una decisione comunicata per
telefono o per iscritto, in caso di rifiuto con motivo facoltativo.
Scaduto il termine di vincolo, l'accettazione non è più possibile; le
offerte scadute, rifiutate o inviate possono essere trasformate in una
nuova versione e proposte di nuovo.

**Fattura:** le offerte accettate o parzialmente accettate vengono
trasformate con un clic in una fattura in bozza. Vengono acquisite
esclusivamente le posizioni accettate; la fattura attraversa poi il
normale processo di fatturazione (verifica, emissione, invio).
Sull'offerta restano collegate le fatture che ne sono derivate,
cosicché il percorso dall'offerta al documento è tracciabile.

Le bozze di offerta non inviate possono essere eliminate; tutto ciò che
segue l'invio resta conservato come cronologia.

**Fatturato nel programma contabile:** se un’offerta accettata viene
fatturata nel programma contabile anziché qui, la contrassegni dal menu
della pagina dell’offerta con **Fatturato nel programma contabile**,
eventualmente con il numero di fattura usato lì. L’offerta esce così
dall’elenco **Preventivi accettati senza fattura**; il contrassegno può
essere rimosso. L’azione è disponibile finché per l’offerta non esiste
alcuna fattura.

**PDF:** ogni offerta può essere scaricata come PDF (con marcatura delle
opzioni e ripartizione delle imposte); le offerte inviate mantengono
permanentemente il design del momento dell'invio. La conferma d'ordine è un
documento proprio (vedi sotto).

## Documenti collegati

In fondo alla pagina, **Documenti collegati** elenca tutti i documenti legati
a questo: l'offerta e le sue versioni, fatture, acconti e fattura finale, note
di credito e storni, documenti di trasporto, ordini di produzione e
trasmissioni. Se un programma di contabilità ha ripreso un documento, il suo
corrispondente compare lì con il numero del programma. Compaiono anche i
collegamenti segnalati dal programma stesso, per esempio un acconto creato in
Lexoffice. La colonna **Relazione** indica che cosa è il documento rispetto a
questo; «tramite …» nomina il documento attraverso cui esiste il
collegamento.

## Numero dell'offerta dal programma di contabilità

Se nella pagina **Serie numeriche** imposta un programma di contabilità
(Lexoffice, easybill o orgaMAX) per **Offerta**, una nuova offerta riceve prima un
numero provvisorio. All'**invio** WorkDiary crea l'offerta nel programma, la
finalizza lì e ne adotta il numero; solo dopo viene creato il PDF. Se il
trasferimento non riesce, l'offerta resta approvata e può ripetere l'invio.
Una nuova versione è nel programma una nuova offerta con un proprio numero.
Lexoffice contrassegna le posizioni facoltative come facoltative; easybill le
riporta senza calcolo; orgaMAX non le riprende.

## Conferma d'ordine

Per un'offerta accettata, **Crea conferma d'ordine** crea un documento
proprio con le posizioni accettate. La conferma d'ordine ha una propria serie
numerica (**Serie numeriche**, serie «Conferma d'ordine»). Con **Conferma**
riceve il suo numero; se un programma di contabilità (Lexoffice, easybill o orgaMAX)
assegna il numero, WorkDiary la trasferisce in quel momento. Dopo sono
disponibili il PDF e l'invio per e-mail. L'annullamento la ritira in WorkDiary;
nel programma resta.

## Bolla di consegna

Una conferma d'ordine confermata offre **Crea bolla di consegna**. La bolla
riprende le quantità non ancora consegnate, senza prezzi. Per una consegna
parziale, modifichi le quantità in **Modifica quantità**; 0 toglie una voce da
questa bolla, che resta aperta per la successiva. **Consegna** fissa il numero
della serie «Documento di trasporto»; se un programma di contabilità
(Lexoffice o easybill) assegna il numero, WorkDiary trasmette la bolla in quel
momento. Dopo sono disponibili il PDF e l'invio per e-mail. Le bolle della
produzione nascono con ogni consegna (vedi ordini di produzione).
