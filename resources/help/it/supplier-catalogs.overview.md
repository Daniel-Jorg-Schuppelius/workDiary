---
title: "Cataloghi fornitori"
topic: supplier-catalogs.overview
version: 1
audience: []
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

**OCI-Punchout:** le fonti con accesso al negozio memorizzato
consentono il passaggio diretto al webshop del fornitore. Il carrello
riempito lì ritorna tramite un rientro firmato e a tempo limitato e
viene assegnato al magazzino di destinazione scelto — come base per il
successivo approvvigionamento.

La lettura è possibile con permessi di lettura del magazzino; la
creazione, l'importazione e il collegamento richiedono permessi di
registrazione del magazzino.
