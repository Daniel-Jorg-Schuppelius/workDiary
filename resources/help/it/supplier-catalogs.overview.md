---
title: "Cataloghi fornitori"
topic: supplier-catalogs.overview
version: 2
audience: []
modules:
    - module.lager
related:
    - articles.master
    - procurement.orders
---

I cataloghi fornitori mantengono nel sistema i listini prezzi dei
fornitori — separati dall'anagrafica articoli propria, ma collegabili
ad essa.

**Fonti di catalogo:** per ogni fornitore vengono create una o più
fonti. I formati supportati sono DATANORM, BMEcat e CSV con mappatura
delle colonne liberamente assegnabile (numero articolo, denominazione,
prezzo di acquisto, valuta, GTIN, numero del produttore, gruppo
merceologico, disponibilità, tempo di consegna). I file arrivano
tramite upload o prelievo remoto automatico a intervallo selezionabile;
una shopinfo.xml caricata precompila mappatura, set di caratteri e
separatore. La mappatura viene salvata sulla fonte e riutilizzata nei
prelievi successivi.

**DATANORM in dettaglio:** sono supportate le versioni 4 e 5 — oltre ai
file articoli (DATANORM.nnn) anche i gruppi di sconto (DATANORM.RAB), i
gruppi merceologici (DATANORM.WRG) e i file prezzi (DATPREIS.nnn). I
prezzi di listino (indicatore 1) diventano prezzi netti d'acquisto
tramite il gruppo di sconto; i file di modifiche non toccano
l'esistente (modalità di elaborazione selezionabile nella finestra di
importazione). Per i file prezzi specifici del cliente, il record di
controllo K viene verificato con il numero cliente salvato sulla fonte.
Il set di caratteri è di norma CP850. In senso inverso, l'elenco
articoli esporta la propria anagrafica come catalogo DATANORM o file
prezzi DATPREIS (anche per accesso al catalogo B2B con prezzi cliente).

**Import:** ogni esecuzione riepiloga quanti articoli di catalogo sono
stati creati, aggiornati, modificati nel prezzo o contrassegnati come
fuori produzione. Gli articoli di catalogo riportano, oltre al prezzo
di acquisto, anche i prezzi scaglionati.

**Collegamento (fonti di approvvigionamento):** gli articoli di
catalogo vengono collegati manualmente o tramite proposta GTIN/EAN agli
articoli propri (anche varianti). Solo questo collegamento stabilisce
la fonte di approvvigionamento — l'anagrafica articoli in sé resta
intatta dall'importazione. I collegamenti possono essere sciolti in
qualsiasi momento.

**Allineamento prezzi con approvazione:** se un'importazione modifica
il prezzo di acquisto di un articolo collegato, nasce un avviso di
calcolo che viene verificato e confermato. Dalle regole di margine il
sistema calcola proposte di prezzo di vendita direttamente sull'articolo
di catalogo. L'acquisizione nell'articolo non avviene mai
automaticamente: in modalità diretta l'operatore la esegue
espressamente, in modalità a quattro occhi nasce invece una richiesta
di approvazione che una seconda persona deve approvare o rifiutare.

**Accesso al negozio (OCI o IDS-Connect):** le fonti con accesso al
negozio memorizzato consentono il passaggio diretto al webshop del
fornitore. Il protocollo si sceglie sulla fonte; IDS-Connect, offerto
dai grossisti di materiale elettrico e termoidraulico, richiede inoltre
il Suo numero cliente presso il grossista. Il carrello riempito lì
ritorna come bozza d'ordine per il magazzino di destinazione scelto.
Vengono riprese le posizioni il cui codice articolo del fornitore è
associato a un articolo; le indicazioni del negozio (ad esempio tempi di
consegna o articoli bloccati) compaiono come messaggio. Se il negozio
segnala il carrello come già ordinato, non lo ordini una seconda volta.
Per le fonti IDS, il simbolo del negozio nell'elenco articoli apre la
pagina dell'articolo direttamente nel negozio.

La lettura è possibile con permessi di lettura del magazzino; la
creazione, l'importazione e il collegamento richiedono permessi di
registrazione del magazzino.
