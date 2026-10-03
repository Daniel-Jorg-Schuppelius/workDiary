---
title: "Flusso documenti"
topic: billing.feed
version: 1
audience: []
modules:
    - module.vertrieb
related:
    - billing.chain
    - invoices.manage
    - quotes.overview
    - finance.incoming-invoices
    - travel-expenses.manage
---

Il flusso documenti mostra **tutti i documenti in un unico elenco**:
preventivi, fatture di vendita e di acquisto, note di credito, documenti
replicati da una contabilità collegata e note spese. Le precedenti pagine
«Preventivi» e «Fatture» portano qui: ora sono schede dello stesso elenco.

**Periodo:** l'elenco segue il filtro data nell'intestazione. Se manca un
documento, verifichi anzitutto il periodo selezionato.

**Schede:** «Tutti», «Preventivi», «Fatture di vendita», «Fatture di
acquisto», «Note di credito» e «Note spese» sono filtri salvati, non pagine
distinte. Il numero sulla scheda indica i documenti nel periodo. «Altri»
(conferme d'ordine, documenti di trasporto, varie) compare solo se contiene
qualcosa. Ricerca e filtri restano attivi quando cambia scheda.

**Indicatori:** i riquadri sono calcolati sull'intero insieme filtrato, non
solo sulla pagina visibile – separati per valuta e senza conversione.

- **Ricavi**, **Costi (esterni)** e **Saldo** confrontano documenti in uscita
  e in entrata.
- **Le mie note spese** indica le sue note spese e la parte ancora in
  verifica.
- **Aperto** e **di cui scaduti**: lo scaduto è un sottoinsieme dell'aperto,
  i due importi non si sommano. Un clic sul riquadro filtra i documenti
  scaduti.
- **Senza effetto monetario:** preventivi, conferme d'ordine e documenti di
  trasporto contano solo come numero.

**Filtri:** la ricerca trova numero, cliente e fornitore. Si aggiungono
l'origine (creato in WorkDiary o proveniente da un sistema collegato),
l'assegnazione (cliente o fornitore), lo stato (Bozza, Aperto, Chiuso,
Annullato), «Solo scaduti» e «Includi archiviati». La direzione si sceglie
solo in «Tutti» e «Note di credito», perché le altre schede la fissano già.
Nella scheda «Note spese», «Solo senza documento contabile» mostra le note
spese prive di documento associato; l'amministrazione passa lì da «Le mie» a
«Tutte».

**Righe:** il numero porta dove l'operazione viene gestita – la fattura, il
preventivo, la fattura di acquisto o il giustificativo di spesa. I documenti
replicati senza pagina propria non hanno collegamento. Per i documenti aperti
la colonna «Scadenza» indica i giorni di ritardo e il livello di sollecito
raggiunto. Le sue fatture scadute si sollecitano direttamente dalla riga con
«Sollecita».

**Nuovi documenti:** in alto a destra crea un preventivo o una fattura oppure
converte un file fattura in fattura elettronica. «Da fatturare e da
sollecitare» apre la catena dei documenti con tutto ciò che è ancora in
sospeso.

**Visibilità:** il flusso mostra solo ciò che i suoi permessi nelle singole
aree già consentono. Senza il permesso sui preventivi, ad esempio, mancano la
scheda e le relative righe.
