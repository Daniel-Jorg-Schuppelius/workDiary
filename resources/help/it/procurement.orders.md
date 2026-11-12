---
title: "Approvvigionamento e ordini"
topic: procurement.orders
version: 6
keywords:
    - acquisti
    - ordine d'acquisto
    - ordine fornitore
    - entrata merci
    - consegna parziale
    - avviso di spedizione
    - proposte di riordino
    - punto di riordino
    - quantità minima d'ordine
    - consegne attese
audience: []
modules:
    - module.lager
related:
    - inventory.stock
    - articles.master
    - manufacturing.orders
    - contacts.manage
---

Gli ordini di acquisto registrano l’acquisto di articoli presso un
fornitore per un magazzino di destinazione. Li trova in **Vendite e
fatturazione** → **Approvvigionamento e cataloghi** → **Ordini di
acquisto**. Con **Nuovo ordine** nasce dapprima una bozza con
**Fornitore**, **Magazzino** e, facoltativamente, **Data di consegna** e
**Nota**. Con **Aggiungi riga** compila le righe d’ordine (**Articolo**,
**Quantità**, facoltativamente **Prezzo unitario**) e poi invia l’ordine
con **Ordina**. Si possono ordinare solo articoli con la caratteristica
**Acquistabile**. Lo stato passa per «Bozza», «Ordinato», «Parzialmente
ricevuto», «Ricevuto» o «Annullato».

L’**Entrata merci** si registra sulla singola riga d’ordine e aumenta lo
stock in modo valorizzato; sono supportate consegne parziali ed
eccedenti, e le colonne **Ordinato** e **Ricevuto** mostrano lo stato.
In alternativa, con **Inserisci avviso di spedizione** può registrare le
quantità annunciate per un ordine e in seguito acquisire l’entrata merci
con **Registra entrata merci**. La scheda **Entrate previste** apre la
vista «Entrate previste», che elenca le righe aperte degli ordini
inviati, ordinate per data di consegna.

La scheda **Proposte di ordine** determina, dopo **Seleziona
magazzino**, il **Fabbisogno** in base alla scorta di riordino e alle
richieste aperte e propone quantità (**Proposta**) tenendo conto della
quantità minima d’ordine e del fornitore preferito. **Crea ordini** ne
ricava bozze per fornitore, da verificare prima di ordinare. Creare,
ordinare e registrare richiedono il permesso **Registra movimenti di
magazzino**; **Annulla** su un ordine non è reversibile.

## Semaforo dei documenti

Se al fornitore sono richiesti documenti obbligatori (ad esempio certificato di esenzione o assicurazione), l’intestazione dell’ordine mostra il loro **semaforo**: verde se tutto è valido, giallo se qualcosa scade a breve, rosso se mancano o sono scaduti. Il clic apre la panoramica dei documenti. Se il rosso blocca l’invio resta stabilito dall’impostazione dei documenti.

## Risposta del fornitore via GAEB

Oltre alla conferma openTRANS, **Importare la risposta del fornitore** legge anche i file commerciali GAEB. Un **X97** (conferma dell’ordine X96) registra quantità, prezzo e data confermati per riga e segnala gli scostamenti senza modificare l’ordine. Un **X94** (offerta di prezzo alla richiesta X93) applica i prezzi unitari offerti all’ordine finché è in bozza. Le righe vengono abbinate tramite il codice articolo del fornitore; il file deve riferirsi al numero dell’ordine. La richiesta di prezzo stessa (**Richiesta di prezzo (GAEB X93)**) e il campo per l’offerta si trovano nell’ordine in bozza.

## Unità d’ordine e ricevimento merci

Se l’articolo ha altre unità, Lei sceglie l’**unità d’ordine** quando aggiunge la posizione (per esempio rotolo invece di metro). Quantità e prezzo unitario valgono allora per unità d’ordine, così come le quantità ordinata e ricevuta della riga. Il ricevimento merci converte nell’unità base: il magazzino riceve la quantità in unità base, il prezzo di costo è tenuto per unità base e il giornale di magazzino mostra l’inserimento nell’unità d’ordine. La confezione di un articolo fornitore resta una grandezza di arrotondamento delle proposte d’ordine in unità base.

Gli articoli con la proprietà «scadenza obbligatoria» richiedono lotto e data di scadenza al ricevimento merci; un lotto già noto porta con sé la propria data.

## Tipi di attestazione e proposta d’ordine

In **Attestazioni obbligatorie → Tipi di attestazione** Lei gestisce i tipi propri dell’organizzazione: denominazione, validità standard, preavviso, comportamento se manca l’attestazione e obbligatorietà. Con i tag l’obbligo vale solo per i fornitori che ne portano uno (mestiere, tipo di prestazione). I tipi del catalogo sono modelli e possono essere adottati come tipo proprio; i tipi propri vengono disattivati invece che eliminati.

La loro gestione richiede il permesso **Gestire i tipi di attestazione dei fornitori** (predefinito: amministrazione e direzione). Il permesso di modificare i fornitori non basta, perché un tipo di attestazione può bloccare l’invio degli ordini a tutti i fornitori dell’organizzazione.

La **proposta d’ordine** arrotonda alla confezione dell’articolo fornitore. Se l’articolo ha un’unità con esattamente questo fattore, la proposta ordina in tale unità e converte il prezzo per unità d’ordine.
