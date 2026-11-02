---
title: "Cartella di lavoro e tavola di riconciliazione"
topic: finance.resale.board
version: 1
keywords:
    - cartella di lavoro
    - tavola di riconciliazione
    - linea temporale
    - corrispondenza
    - subentro
    - credito inesigibile
    - insolvenza
    - cambio ordine
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.reconcile
    - finance.resale.subscription
---

La **cartella di lavoro** di un destinatario fattura è lo spazio in cui si
assegna: in alto i suoi abbonamenti, sotto, per prodotto, la tavola di
riconciliazione con periodi e righe di fattura su un'unica linea temporale. I
riquadri sopra la tavola la filtrano sui prodotti interessati.

**Cartella di lavoro e tavola di riconciliazione:** la riconciliazione di un
destinatario è la cartella di lavoro: in alto gli abbonamenti con modifica,
subentro, cessione di licenze e restituzione, sotto la tavola di
riconciliazione per prodotto. A sinistra tutti i periodi in ordine di data
con previsto e copertura, a destra le righe di fattura: quelle collegate con
il periodo di servizio, per i periodi aperti quelle libere corrispondenti,
fatture ad altri clienti e annullamenti. Le righe senza periodo adatto
compaiono come riga propria «senza periodo» alla loro data. Ogni fattura
libera compare solo al periodo che le corrisponde meglio e «Assegna …» la
assegna con un clic; la quantità è ciò di cui il periodo ha ancora bisogno.
Uno sfasamento di alcune settimane tra la data d’ordine presso il fornitore
e la data della fattura è normale e non viene mostrato; una fattura
cumulativa che copre l’intero periodo corrisponde. Le fatture senza periodo
adatto compaiono in una riga «senza periodo» con «Crea abbonamento» o
«Assegna a un altro periodo». Le assegnazioni dubbie chiedono conferma prima
del salvataggio; nella finestra di collegamento e nell’assegnazione rapida
della pagina dell’abbonamento Lei le conferma con la casella. I collegamenti
che non corrispondono temporalmente sono evidenziati in giallo: li verifichi
e li scolleghi se necessario. «Elaborare il prossimo destinatario» nella
panoramica guida attraverso tutti i destinatari con importo aperto; un
destinatario è completato quando non resta alcun periodo scaduto aperto né
alcun rilievo sui modelli. Se il fornitore rinnova un abbonamento come nuovo
ordine, l’esecuzione delle proposte rinuncia al breve periodo residuo del
vecchio ordine e sposta i suoi collegamenti al primo periodo del nuovo. Il
periodo residuo compare allora come «Cambio d’ordine», non come rinuncia: le
fatture del successore coprono quel tempo.

**Corrispondenza:** ogni riga di fattura porta una corrispondenza con il
periodo — dalla distanza tra l'inizio del periodo e l'inizio di ciclo più
vicino del suo periodo di prestazione, oppure dalla data fattura se manca il
periodo di prestazione. Fino a 14 giorni vale come esatta, fino a 90 giorni
come spostata; le righe che si sovrappongono soltanto, sono molto lontane o
fuori, oppure corrispondono nettamente meglio a un altro periodo aperto dello
stesso prodotto chiedono conferma prima dell'assegnazione: nella cartella come
domanda sul pulsante, nella finestra del collegamento e nell'assegnazione
rapida della pagina abbonamento come casella. Se il periodo di prestazione
copre l'intero periodo, la riga corrisponde senza domanda.

**Insolvenza e subentro:** se una fattura è stata annullata perché il
cliente è insolvente, registri il periodo nella tavola con «Registrare come
perdita»: la finestra di rinuncia propone il motivo con il numero della
fattura. Se un’altra azienda prosegue le licenze, usi «Subentro»
sull’abbonamento: nuovo titolare e data di decorrenza. Se la data di
decorrenza coincide con l’inizio del primo periodo aperto, al nuovo titolare
vengono fatturati anche i periodi aperti; se è successiva, il registro
rinuncia su richiesta ai periodi aperti precedenti come crediti inesigibili.
Una data di decorrenza all’inizio dell’abbonamento passa l’intero
abbonamento al nuovo titolare, altrimenti dalla data di decorrenza nasce una
cessione. Se una fattura va già all’azienda subentrante, la tavola offre il
subentro direttamente su quella fattura.
Se ci sono già periodi fatturati al titolare precedente, il registro rifiuta
una data di decorrenza all’inizio dell’abbonamento: deve allora partire dal
primo periodo aperto.
