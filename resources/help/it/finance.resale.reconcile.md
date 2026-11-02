---
title: "Riconciliazione per destinatario"
topic: finance.resale.reconcile
version: 1
keywords:
    - riconciliazione
    - destinatario fattura
    - elenco di lavoro
    - importo aperto
    - mai fatturato
    - fattura ad altro cliente
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.board
    - finance.resale.periods
---

La **riconciliazione** confronta, per destinatario fattura, i periodi scaduti
con le righe di licenza delle sue fatture. L'elenco indica per destinatario
l'importo aperto, i rilievi sui modelli e se è completato; il filtro
predefinito nasconde i destinatari completati, e «Elabora prossimo
destinatario» apre la prima cartella di lavoro aperta.

**Riconciliazione per destinatario fattura:** quando periodi restano aperti
e non è chiaro se manca una fattura o solo l’assegnazione, usi la
riconciliazione (pulsante nell’elenco abbonamenti, nella pagina periodi e
sul cliente). Per destinatario — il cliente con i suoi clienti finali —
confronta i periodi scaduti di tutti gli abbonamenti con le righe licenza
delle sue fatture, in mesi licenza per prodotto: *dovuto* dai periodi,
*fatturato* dalle righe. L’esito dice cosa fare: «solo non assegnato» (le
righe libere bastano — assegnare), «mai fatturato» (più periodi che righe —
fattura di recupero tramite bozza o rinuncia al periodo) oppure «senza
periodo» (più righe che periodi — manca un abbonamento nel registro o
doppia fatturazione). Per ogni periodo aperto sono elencate le righe dello
stesso prodotto con la distanza dall’inizio periodo: le libere con
assegnazione, le consumate con il loro titolare per controllo. La data di
riferimento è il **periodo di prestazione** della fattura, altrimenti la
data fattura; licenze e mesi sono mostrati separati («5 × 12 mesi» = cinque
licenze per un anno). Inoltre tre trappole invisibili per abbonamento:
**fattura a un altro cliente** (l’account del fornitore non è il cliente,
oppure il cliente finale è fatturato direttamente invece che tramite il
partner; riconosciuto da una parte di nome comune) — la soluzione è
«Titolare → cliente»: l’abbonamento passa a quel cliente e la proposta si
applica subito. Le fatture **stornate** vicino all’inizio periodo spiegano
un periodo vuoto. Gli abbonamenti della **posta in arrivo** la cui azienda
compare nei testi fattura attendono il titolare. Una riga libera che non
tocca più alcun periodo del suo prodotto segnala un contratto assente nel
registro (l’export del fornitore non lo conosce): la riga prodotto dice
«Riga senza abbonamento dal …» e «Crea abbonamento dalla riga» apre il
dialogo con articolo, quantità, inizio e prezzo presi dalla fattura.
L’assegnazione può colpire un periodo di un altro abbonamento dello stesso
destinatario — mai un periodo di un altro cliente.
