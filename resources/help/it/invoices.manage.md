---
title: "Fatture & documenti"
topic: invoices.manage
version: 6
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

1. Scelga cliente e periodo — la finestra di creazione mostra
   un'**anteprima** delle posizioni (numero, durata in formato orario
   e decimale, importo, avviso ritardatari).
2. Escluda se serve singole registrazioni con la casella — restano
   aperte e ricompaiono al giro successivo.
3. Controlli e completi la bozza; per posizione si espandono le
   **registrazioni di origine** (1,50 h = 1:30 h). Per un articolo con peso
   di rame, la casella **supplemento rame** nella finestra della riga
   aggiunge il supplemento al prezzo DEL del giorno come riga separata.
4. Emetta o invii — PDF, invio e sincronizzazione esterna sono uscite
   dello stesso stato documentato.
5. In caso di ritardo usi il **sollecito**: il livello 1 crea un
   promemoria di pagamento come PDF separato con riepilogo crediti,
   eventuale spesa e scadenza; l'e-mail contiene lettera e fattura
   originale. Non nasce un nuovo documento.

**Fattura elettronica.** La XRechnung viene generata in sintassi UBL; se un
destinatario richiede CII, scelga sul cliente o all’invio il formato di
consegna «XRechnung (XML, sintassi CII)». Tramite Peppol si usa sempre UBL.
Senza partita IVA — ad esempio come piccola impresa ai sensi del § 19 UStG —
basta il codice fiscale nei dati di fatturazione elettronica: viene indicato
anche come identificativo del venditore, richiesto dalla verifica del
destinatario.

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

## Fattura libera senza tempi

Nella finestra di creazione, **«Comporre le righe manualmente»** è alla pari
con la ripresa da tempi o consumo di materiale. La bozza richiede solo il
cliente (facoltativi progetto, cliente finale e termine di pagamento) e parte
vuota: può essere salvata e completata dopo, ma non emessa né inviata finché
non ha righe — vale allo stesso modo per emissione, e-mail, Peppol e
trasferimento a Lexoffice. Un doppio clic su «Crea bozza» non genera una
seconda fattura.

**Articoli, materiale e prestazioni.** Una riga è un articolo (con variante
facoltativa), materiale o testo libero — ad esempio «Montaggio forfettario» o
una produzione speciale senza ordine di produzione. Descrizione, numero
articolo, unità e prezzo vengono congelati come valori del documento; le
modifiche successive all'anagrafica non cambiano il documento. Un prezzo
mancante va inserito consapevolmente (0,00 è ammesso come riga gratuita); un
prezzo in valuta estera non viene mai convertito in silenzio. Le righe articolo
e a testo libero **non movimentano il magazzino**; le consegne passano da
magazzino/consegna.

**Fatturare consegne di produzione.** Con il modulo magazzino attivo, «Riprendi
consegna» importa le consegne effettuate del cliente con destinazione di
fatturazione locale — ciascuna per intero come una riga, con quantità
vincolata alla fonte e prezzo di vendita della consegna (non il costo di
produzione). Una consegna può stare in una sola bozza alla volta; l'emissione
la segna come fatturata, rimuovere la riga, scartare la bozza o uno storno
totale la liberano di nuovo e l'origine resta visibile sul documento. Le note
di credito parziali non liberano nulla; il magazzino resta invariato in tutte
le operazioni di fattura.
