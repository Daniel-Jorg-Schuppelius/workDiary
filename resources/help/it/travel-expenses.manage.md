---
title: "Viaggi, spese e diarie"
topic: travel-expenses.manage
version: 3
keywords:
    - nota spese
    - rimborso spese
    - trasferta
    - libretto di viaggio
    - rimborso chilometrico
    - diaria
    - scansione scontrino
    - scontrino
    - auto aziendale
    - fringe benefit
    - tempi di guida
    - registro viaggi
    - registrare un viaggio
audience: []
modules:
    - module.spesen
related:
    - invoices.manage
    - exports.payroll
    - reports.overview
---

Registro viaggi, spese e diarie di vitto documentano le trasferte di
lavoro separatamente ma con riferimento comune a periodo e giustificativi.
Flusso tipico: registri il viaggio con data, tragitto, scopo, veicolo e
chilometraggi, aggiunga le spese con categoria, importo, modalità di
pagamento e giustificativo, faccia calcolare la diaria per i viaggi di più
giorni e inoltri il tutto per approvazione o conteggio. Giustificativi,
chilometraggi e orari di viaggio devono essere plausibili; i record
approvati o già conteggiati non vengono modificati in silenzio, le
correzioni richiedono un percorso tracciabile.

## Registrare un viaggio

Un nuovo viaggio si crea da **Nuovo …** nella barra laterale: nel gruppo
**Pianificazione**, **Registro viaggi** apre la finestra **Registra nuovo
viaggio**. Tutti i viaggi registrati si trovano in **Trasferte e spese** →
**Registro viaggi**.

- **Viaggio:** **Data**, **Veicolo** (tipo di veicolo con la sua tariffa al
  chilometro), **Veicolo della flotta (opzionale)**, **Tipo di viaggio** e
  inoltre **Da (indirizzo)** e **A (indirizzo)**.
- **Distanza e tariffa:** **Distanza (km, solo andata)** è obbligatoria. Se
  **Tariffa €/km (facoltativo)** resta vuoto, vale la tariffa del veicolo della
  flotta, altrimenti quella del tipo di veicolo. Si aggiungono
  **Contachilometri inizio (km)**, **Contachilometri fine (km)**, **Inizio
  (ora)** e **Fine (ora)**; se il viaggio termina dopo mezzanotte, inserisca
  semplicemente l'ora minore.
- **Assegnazione:** **Progetto (facoltativo)**, **Cliente (facoltativo)** e
  **Scopo**.
- **Opzioni e note:** **Andata e ritorno (raddoppia i km)**, **Rimborsabile**
  (preselezionato) e **Note**.

**Registra** salva il viaggio a Suo nome e torna all'elenco. Se inizio e fine
sono compilati, WorkDiary crea di norma una registrazione del tempo non
fatturabile per il tempo di viaggio. Un contachilometri finale più alto viene
riportato nel veicolo della flotta. Se il veicolo della flotta è in **Modalità
registro viaggi**, i chilometraggi sono obbligatori e il viaggio viene bloccato dopo
la fine della giornata. Chiunque abbia effettuato l'accesso può registrare
viaggi, se la Sua organizzazione usa il modulo; un viaggio appartiene sempre
alla persona che lo ha registrato.

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

Se qualcosa non va in una spesa già trasmessa, la corregge nella finestra del
giustificativo **con un documento di storno**, indicando obbligatoriamente il
motivo. Viene trasmessa una nota di credito d'acquisto dello stesso importo che
annulla il documento originale in contabilità. Contemporaneamente nasce una nuova
spesa in **bozza** con riferimento a quella vecchia; segue approvazione e
trasmissione come qualsiasi altra.

Se la spesa originale era approvata ma non ancora rimborsata, viene annullata:
altrimenti verrebbero pagate entrambe. Se era già rimborsata, la bozza avvisa che
va rimborsata solo la differenza.

## Scansionare il giustificativo invece di digitarlo

Invece di inserire importo, data ed esercente a mano, può **fotografare il
giustificativo o caricarlo in PDF**. Il riconoscimento legge i campi consueti e
precompila il modulo.

Il risultato è una **proposta**, non una registrazione conclusa: verifichi
importo, data, aliquota ed esercente prima di salvare. Foto poco illuminate,
carta termica e giustificativi scritti a mano sono le cause più frequenti di
letture errate.

Il giustificativo originale resta allegato invariato: il riconoscimento non lo
sostituisce, le risparmia solo la digitazione.

## Registro viaggi: firma, correzione e confronto 1 %

In modalità registro viaggi chi guida chiude un viaggio **con la propria
firma**; il viaggio viene poi bloccato. Anche un viaggio già bloccato a fine
giornata si può ancora firmare. Se un viaggio a metà della catena viene corretto
con un viaggio di storno e cambia il chilometraggio finale, il viaggio successivo
inizia automaticamente da lì — come correzione conseguente, l'originale resta.

Il **confronto 1 %** sotto il giustificativo del registro viaggi mette a confronto, per
veicolo e anno, il metodo del registro viaggi con la regola dell'1 %. Il veicolo richiede
il prezzo di listino lordo e la distanza casa–lavoro; lì Lei inserisce gli altri
costi annuali (leasing, assicurazione, bollo), l'energia deriva dai giustificativi
di rifornimento e ricarica. Facoltativamente un'impostazione blocca nuovi viaggi
finché il controllo obbligatorio di un veicolo è scaduto.

**Tempi di guida e di riposo.** Se la Sua organizzazione applica le regole sui
tempi di guida, indichi un secondo conducente sul viaggio (multipresenza) o
contrassegni le traversate in cui il veicolo viaggia su traghetto o treno. Il
secondo conducente non accumula tempo di guida, ma il suo tempo nel veicolo
non conta come riposo; in multipresenza bastano 9 ore di riposo in 30 ore. Una
traversata in traghetto o treno non interrompe il riposo.
