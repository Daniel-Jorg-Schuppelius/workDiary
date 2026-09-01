---
title: "Fatture & documenti"
topic: invoices.manage
version: 3
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - contacts.manage
    - projects.manage
    - finance.datev-bookings
    - finance.transfers
    - travel-expenses.manage
---

## Scopo e contesto

La panoramica fatture gestisce le fatture locali e i documenti
collegati. Quale via guida dipende dall'organizzazione e
dall'integrazione di fatturazione in uso: per periodo emette le
fatture WorkDiary oppure esattamente un sistema esterno — mai
entrambi insieme.

## Prerequisiti

- Anagrafiche verificate: cliente, indirizzo del destinatario, dati
  fiscali.
- **Periodo di prestazione e collegamento al progetto** delle
  posizioni da fatturare.
- Il diritto di creare fatture; per i solleciti il rispettivo ruolo
  finanziario.

## Procedura consigliata

1. Scegli cliente e periodo — la finestra di creazione mostra
   un'**anteprima** delle posizioni (numero, durata in formato orario
   e decimale, importo, avviso ritardatari).
2. Escludi se serve singole registrazioni con la casella — restano
   aperte e ricompaiono al giro successivo.
3. Controlla e completa la bozza; per posizione si espandono le
   **registrazioni di origine** (1,50 h = 1:30 h).
4. Emetti o invia — PDF, invio e sincronizzazione esterna sono uscite
   dello stesso stato documentato.
5. In caso di ritardo usa il **sollecito**: il livello 1 crea un
   promemoria di pagamento come PDF separato con riepilogo crediti,
   eventuale spesa e scadenza; l'e-mail contiene lettera e fattura
   originale. Non nasce un nuovo documento.

## Esempio pratico

A fine mese la contabilità sceglie «Müller GmbH» e il mese
precedente: l'anteprima mostra 14 posizioni e segnala due tempi
ritardatari. Una registrazione contestata viene esclusa e passa
automaticamente al giro successivo — la fattura parte senza
discussioni.

## Errori tipici

- **Modificare in silenzio documenti inviati o consegnati:** i
  documenti emessi, contabilizzati o consegnati sono immutabili — per
  gli errori c'è lo storno o la correzione.
- **Sovrascrivere numeri o importi** invece di correggere — si
  distrugge la tracciabilità.
- **Doppia titolarità di fatturazione:** se un sistema esterno guida
  la fatturazione, le fatture locali volutamente non esistono in
  parallelo.

## Effetti e prossimi passi

Le fatture emesse alimentano partite aperte, solleciti e consegna
contabile. Poi: controllare incassi e abbinamenti e creare il lotto
DATEV per lo studio.
