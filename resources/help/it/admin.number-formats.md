---
title: "Formati di numerazione"
topic: admin.number-formats
version: 6
keywords:
    - serie numerica
    - numero fattura
    - numero cliente
    - codice articolo
    - numerazione progressiva
    - prefisso
    - numero iniziale
    - contatore annuale
    - azzerare contatore
    - numerazione dalla contabilità
    - numero cliente da Lexoffice
    - numero provvisorio
    - separatore
    - via di fatturazione
    - numero offerta dal programma
    - numero fattura dal programma
    - fattura a Lexoffice
    - fattura a sevDesk
    - fattura a easybill
audience:
    - admin
    - teamleitung
related:
    - invoices.manage
    - admin.classifications
    - glossary.core
---

I formati di numerazione definiscono le serie progressive per
organizzazione, ad esempio per ticket, asset, articoli, ordini di
produzione, clienti, fornitori e fatture. Per ogni formato configura
prefisso e separatore, l'inclusione facoltativa dell'anno, il numero
di cifre di riempimento, il valore iniziale e l'azzeramento annuale
del contatore; un'anteprima mostra il prossimo numero generato.
Modifichi formato o valore iniziale con cautela, perché i numeri già
assegnati restano invariati.

## Salvare e separatori per tutte le serie

Salvi tutte le modifiche della pagina insieme con **Salva** nell'intestazione.
Le serie che non modifica restano sul valore standard («Default»). Se un
inserimento non è valido, il messaggio indica la serie e i suoi inserimenti
restano.

Sopra la tabella si trovano i campi **Separatore dopo il prefisso** e
**Separatore dopo l'anno** per tutte le serie. **Applica a tutte le serie**
imposta entrambi i separatori in ogni serie assegnata da WorkDiary e salva la
pagina. Un campo vuoto significa nessun separatore. I numeri già assegnati
restano invariati.

## Chi assegna i numeri cliente e fornitore?

Nella colonna **Assegnato da** sceglie per **Cliente** e **Fornitore** se
il numero lo assegna WorkDiary o un programma di contabilità collegato.
Sono proposti i programmi attivi in grado di assegnare numeri: Lexoffice
(cliente e fornitore), sevDesk ed easybill (cliente). orgaMAX non assegna numeri cliente
numeri per WorkDiary.

Se un programma assegna i numeri:

- Un nuovo cliente riceve prima un numero provvisorio (`ENTWURF-…`). Se
  creato nel modulo, viene subito trasferito al programma e ne riceve il
  numero. Un'elaborazione oraria recupera i clienti da importazioni e i
  trasferimenti non riusciti.
- Il campo **Numero cliente** è bloccato per questi clienti.
- La colonna **Prossimo numero** mostra l'ultimo numero ripreso, quanti
  contatti attendono ancora il loro numero e, per sevDesk, il prossimo numero
  del programma.
- Se il numero del programma appartiene già a un altro cliente, resta il
  numero precedente e la **casella di abbinamento** mostra il caso. Dopo
  aver rinumerato l'altro cliente, la sincronizzazione successiva adotta il
  numero.
- I clienti esistenti mantengono il loro numero.
- Se la contabilità gestisce i dati anagrafici, WorkDiary non trasferisce
  nulla; il numero arriva non appena la sincronizzazione dei contatti
  collega il cliente.

Se il numero lo assegna WorkDiary, ogni trasferimento verifica se il numero
appartiene già a un altro contatto nel programma e in tal caso si interrompe
con un messaggio invece di sovrascrivere quel contatto.

## Fattura, nota di credito e storno

Nella colonna **Assegnato da** sceglie anche per **Fattura**, **Nota di
credito** e **Storno** chi assegna il numero: WorkDiary o un programma
collegato – Lexoffice ed easybill per tutti e tre, sevDesk per fattura e storno, orgaMAX per la fattura. Se assegna un programma, la bozza ha un numero provvisorio;
**Emettere** trasmette il documento e riprende il numero del programma, nota
di credito e storno con riferimento alla fattura trasmessa. Crei allora le
fatture di acconto e finali nel programma.

Se la **via di fatturazione** nelle impostazioni dell'organizzazione è su un
programma di contabilità, questo gestisce completamente le fatture; la
colonna **Assegnato da** lo indica. Se singoli clienti fatturano per un'altra via,
sotto è indicato per quanti.

## Serie bloccate

Se un programma assegna i numeri di una serie, ne stabilisce anche il
formato. Prefisso, separatori, anno, cifre, azzeramento annuale e inizio sono
allora bloccati; i valori salvati tornano a valere non appena assegna
WorkDiary. Se un cliente viene fatturato in WorkDiary nonostante una via di
fatturazione esterna, fattura, nota di credito e storno restano aperti,
perché le sue fatture ricevono numeri locali. La selezione **Assegnato da**
resta modificabile; i campi si liberano dopo il
salvataggio.

## Offerta, conferma d'ordine e bolla di consegna

Per **Offerta**, **Conferma d'ordine** e **Documento di trasporto** scelga
Lexoffice o easybill nella colonna **Assegnato da**, per offerta e conferma d'ordine anche orgaMAX. Il numero nasce all'invio
dell'offerta, alla conferma della conferma d'ordine e alla consegna della
bolla, quando WorkDiary trasmette il documento al programma.
