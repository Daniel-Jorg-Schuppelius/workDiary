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
