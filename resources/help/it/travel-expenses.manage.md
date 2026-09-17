---
title: "Viaggi, spese e diarie"
topic: travel-expenses.manage
version: 1
audience: []
modules:
    - module.spesen
related:
    - invoices.manage
    - exports.payroll
    - reports.overview
---

Registro dei viaggi, spese e diarie di vitto documentano le trasferte di
lavoro separatamente ma con riferimento comune a periodo e giustificativi.
Flusso tipico: registra il viaggio con data, tragitto, scopo, veicolo e
chilometraggi, aggiungi le spese con categoria, importo, modalità di
pagamento e giustificativo, fai calcolare la diaria per i viaggi di più
giorni e inoltra il tutto per approvazione o conteggio. Giustificativi,
chilometraggi e orari di viaggio devono essere plausibili; i record
approvati o già conteggiati non vengono modificati in silenzio, le
correzioni richiedono un percorso tracciabile.

## Trasmettere una spesa alla contabilità come documento

Una spesa **approvata** può essere trasmessa direttamente dal dialogo dei
documenti al sistema contabile di riferimento come documento di acquisto —
invece di registrarla una seconda volta. L’ID esterno torna alla creazione; il
duplicato non può nemmeno nascere.

Tre regole:

- **Solo spese approvate.** La trasmissione è irrevocabile — il sistema di
  destinazione non conosce né modifica né cancellazione dei documenti. Le
  correzioni avvengono lì con un documento di storno.
- **Nessuna trasmissione senza categoria contabile.** L’abbinamento si cura
  per categoria di spesa (Amministrazione → Categorie di spesa); una categoria
  indovinata sarebbe peggio del messaggio di errore.
- **Dalla trasmissione fa fede il documento.** Il collegamento non si può più
  sciogliere — il documento esiste, collegato o no.

I file della spesa vengono trasmessi insieme — senza file il documento non
vale nulla per la contabilità.

### Correzione con documento di storno

Se qualcosa non va in una spesa già trasmessa, la correggi nella finestra del
giustificativo **con un documento di storno**, indicando obbligatoriamente il
motivo. Viene trasmessa una nota di credito d'acquisto dello stesso importo che
annulla il documento originale in contabilità. Contemporaneamente nasce una nuova
spesa in **bozza** con riferimento a quella vecchia; segue approvazione e
trasmissione come qualsiasi altra.

Se la spesa originale era approvata ma non ancora rimborsata, viene annullata:
altrimenti verrebbero pagate entrambe. Se era già rimborsata, la bozza avvisa che
va rimborsata solo la differenza.

## Scansionare il giustificativo invece di digitarlo

Invece di inserire importo, data ed esercente a mano, puoi **fotografare il
giustificativo o caricarlo in PDF**. Il riconoscimento legge i campi consueti e
precompila il modulo.

Il risultato è una **proposta**, non una registrazione conclusa: verifica
importo, data, aliquota ed esercente prima di salvare. Foto poco illuminate,
carta termica e giustificativi scritti a mano sono le cause più frequenti di
letture errate.

Il giustificativo originale resta allegato invariato: il riconoscimento non lo
sostituisce, ti risparmia solo la digitazione.
