---
title: "Giacenze e scansione"
topic: inventory.stock
version: 8
keywords:
    - magazzino
    - carico merce
    - scarico magazzino
    - trasferimento di magazzino
    - prenotazione
    - punto di riordino
    - scorta minima
    - bloccare lotto
    - gestione lotti
    - FEFO
    - scansionare codice a barre
    - valore di magazzino
audience: []
modules:
    - module.lager
related:
    - warehouses.manage
    - inventory.counts
    - inventory.labels
    - articles.master
---

La panoramica delle giacenze mostra per magazzino le quantità disponibili,
fisiche e riservate delle varianti, il prezzo medio mobile, il valore di
magazzino e la scorta minima di riordino. Con il permesso di registrazione
inserisce movimenti manuali (entrata, prelievo, riserva, rilascio) e imposta
scorte minime per variante e magazzino; i prelievi in negativo sono possibili
solo se li consente espressamente. I lotti si gestiscono nell'apposito elenco
(divisione e unione), mentre la vista di scansione risolve un codice (numero
di serie, lotto, GTIN o SKU) e registra direttamente un'azione. Tutti i
movimenti finiscono nel giornale progressivo e non sono reversibili: le
correzioni avvengono tramite scritture di segno opposto.

Un lotto può essere **bloccato** e di nuovo **sbloccato** nell’elenco dei
lotti — sempre con una motivazione; entrambe le azioni restano nel registro
di audit. La giacenza di un lotto bloccato resta in magazzino, ma la lista di
prelievo non lo propone più; divisione e unione sono possibili solo dopo lo
sblocco. Con l’unione, la giacenza del lotto di origine passa al lotto di
destinazione tramite scritture di segno opposto (trasferimento
uscita/entrata); il lotto unito è poi chiuso e non accetta più entrate.

**Prelievo per lotto.** Se il magazzino contiene giacenze a lotti, il
modulo di registrazione offre il campo «Lotto (prelievo)»: se lo lascia
vuoto, il prelievo segue FEFO (prima la scadenza più vicina, come nella
lista di prelievo); altrimenti viene prelevato esattamente il lotto scelto.
Per entrata, riserva e rilascio il campo non ha effetto. Gli articoli a
lotti si possono prelevare così finché i loro lotti coprono la quantità —
per essi non si preleva giacenza senza lotto; l’entrata passa sempre dal
ricevimento merci. Un trasferimento tramite scansione porta i lotti nel
magazzino di destinazione; un codice lotto scansionato registra esattamente
quel lotto.

**Blocco nella giacenza.** Bloccare un lotto registra la sua giacenza nello
stato «bloccato»: resta in magazzino, ma non conta più come disponibile e
compare nella panoramica delle giacenze nella colonna «Bloccato». Un lotto
bloccato non accetta ricevimenti; resi e ricollocazioni in esso restano
bloccati. Lo sblocco storna la giacenza bloccata. Anche la divisione di un
lotto è una registrazione: la quantità divisa passa al nuovo lotto nel
giornale dei movimenti.

**Ripulire la giacenza storica.** Fino a ottobre 2026 i prelievi non
portavano il lotto; la giacenza di un lotto può quindi superare quanto ne
resta davvero. Il Suo amministratore lo verifica con il comando
`inventory:lots:repair`: senza opzioni è una simulazione con una tabella per
lotto (saldo contabile, residuo degli strati di valutazione, differenza);
con `--apply` trasferisce la differenza a «senza lotto» con valutazione FIFO
o FEFO, e la giacenza totale resta invariata. Con il prezzo medio mobile
segnala soltanto il lotto; i lotti bloccati solo dopo lo sblocco.

## Unità, giornale di magazzino e impostazioni

**Registrare in un’unità dell’articolo.** Se un articolo ha altre unità con fattore di conversione (per esempio 1 rotolo = 100 m), il modulo dei movimenti e la vista di scansione offrono la scelta **Unità** accanto alla quantità. La registrazione avviene sempre nell’unità base; la quantità inserita e la sua unità restano sul movimento. L’arrotondamento è commerciale a quattro decimali. Una quantità che equivale a zero nell’unità base e un’unità non definita per l’articolo vengono rifiutate.

**Giornale di magazzino.** La scheda **Giornale di magazzino** elenca tutti i movimenti con data e ora, magazzino, tipo di movimento, quantità in unità base e la colonna **Registrato come** (quantità e unità inserite). Ricerca, magazzino e periodo in alto restringono l’elenco.

**Scadenza.** Se un articolo ha la proprietà «scadenza obbligatoria», il magazzino accetta la sua entrata solo tramite il ricevimento merci di un ordine, con lotto e data di scadenza. Le entrate manuali e da scansione vengono rifiutate. Un lotto già noto porta con sé la propria data.

**Impostazioni di magazzino.** Con il diritto di configurazione del magazzino Lei apre le **impostazioni di magazzino** nella panoramica delle giacenze. Lì sceglie il **metodo di valutazione** dell’organizzazione (media mobile, FIFO o FEFO) e il **momento della prenotazione** in produzione (al rilascio o all’avvio). Passando a FIFO o FEFO, la giacenza esistente riceve uno strato iniziale al prezzo medio precedente. Il ritorno alla media mobile è possibile solo quando non resta giacenza negli strati, perché la media non è stata aggiornata in questo periodo.

## Diritti, prenotazioni e numeri articolo

**Diritti separati.** Registrare movimenti, contare l’inventario, approvare le differenze inventariali, modificare le prenotazioni e compensare le registrazioni esterne sono diritti distinti. Chi poteva registrare mantiene conteggio, prenotazione e compensazione; il Suo amministratore può separare i diritti per ruolo.

**Modificare una prenotazione.** Con il diritto sulle prenotazioni, l’icona della matita di una prenotazione attiva apre una finestra con la quantità prenotata, prelevata e aperta. Lì Lei imposta la priorità (un numero più basso significa priorità più alta) e rilascia una quantità parziale, ad esempio per assegnarla a un altro ordine.

**Approvazione a quattro occhi.** Nelle impostazioni di magazzino è possibile attivare l’approvazione a quattro occhi per le differenze inventariali, con una soglia per il valore di giacenza della differenza per riga. A partire dalla soglia non approva chi ha contato. Soglia e approvatore risultano nel registro di audit.

**SKU e GTIN.** Le impostazioni di magazzino mostrano chi gestisce numero articolo, SKU delle varianti e GTIN. La scelta avviene nella pagina «Numerazioni», numerazione «Articolo». Se un gestionale di magazzino gestisce i numeri, i campi sono bloccati nell’anagrafica articoli e arrivano con la sincronizzazione.

**Giacenza negativa a quattro occhi:** se l’organizzazione richiede nelle impostazioni di magazzino un’approvazione a quattro occhi per i prelievi in giacenza negativa, un prelievo con «Consenti giacenza negativa» diventa una richiesta nella casella delle approvazioni. Viene registrato solo quando un’altra persona con il diritto «Autorizzare giacenze negative» acconsente; richiedente e approvatore compaiono nel protocollo di audit.

**Consumo di materiale sul foglio ore:** il materiale collegato a un articolo gestito a magazzino registra, quando viene inserito sul foglio ore o in «Oggi», il prelievo dal magazzino contrassegnato come «Predefinito» (se ce ne sono più, il primo per nome). Il movimento compare nel giornale di magazzino con la quantità e l’unità inserite; una modifica registra la differenza, l’eliminazione lo riporta in giacenza. Valgono le regole di questa pagina: giacenza negativa solo con il permesso ed eventualmente quattro occhi, lotti per data di scadenza, numeri di serie solo tramite il magazzino. Il materiale che finora prelevava anche qui, d’ora in poi lo registri solo sul foglio ore, altrimenti il prelievo conta due volte.

**Variante del consumo di materiale:** se l’articolo ha più varianti attive, il consumo registra la variante scelta sul foglio ore, senza scelta la variante predefinita. Un cambio di variante riporta in giacenza la precedente e preleva la nuova.

**Valorizzazione dei prelievi:** i prelievi dalla giacenza propria — nel modulo di registrazione, tramite scansione e dopo un’approvazione a quattro occhi — sono valorizzati secondo il metodo dell’articolo, come consegne e consumo di materiale: FIFO e FEFO consumano gli strati di valorizzazione, il costo medio ponderato riduce il libro delle medie al prezzo medio attuale. I prelievi da giacenza di terzi (cliente, conto deposito, fornitore, progetto) restano non valorizzati. Anche il prelievo di magazzino nella scheda cliente registra secondo il metodo dell’articolo; eliminare l’assegnazione lo riporta in giacenza come nuovo strato al costo unitario del prelievo con FIFO e FEFO, e nel libro delle medie con il costo medio ponderato. L’entrata manuale non ha prezzo unitario e resta non valorizzata.

**Ridistribuire le prenotazioni:** nella finestra «Modifica prenotazione» cedete la quantità aperta a un’altra prenotazione dello stesso articolo, magazzino e proprietà, ad esempio a un ordine con priorità più alta. Una prenotazione più recente non ne scalza mai una più vecchia in silenzio; la ridistribuzione compare nel protocollo di audit e la riga materiale di un ordine di produzione la segue.

**Valore di magazzino per metodo:** Prezzo medio e valore di magazzino della
panoramica seguono il metodo di valutazione dell'articolo: con la media mobile
dal registro delle medie, con FIFO e FEFO dagli strati di valutazione aperti
(valore delle quantità residue, media = valore ÷ quantità). La classificazione
ABC dell'inventario ciclico usa gli stessi valori.
