---
title: "Regole di prezzo e di margine"
topic: pricing.margin-rules
version: 1
audience:
    - admin
related:
    - supplier-catalogs.overview
    - articles.master
---

Le regole di margine derivano proposte di prezzo di vendita dai prezzi
di acquisto. Garantiscono che i prezzi dei cataloghi fornitori non
debbano essere calcolati a mano — e che nessuna acquisizione aggiri il
calcolo.

**Contenuto della regola:** una regola calcola con una maggiorazione in
percentuale sul prezzo di acquisto oppure con un margine obiettivo in
percentuale sul prezzo di vendita; se entrambi sono impostati, il
margine obiettivo ha la precedenza. Facoltativamente si aggiungono: un
margine minimo (la proposta viene contrassegnata se ne risultasse un
valore inferiore), un prezzo di vendita minimo e uno schema di
arrotondamento per prezzi finali commercialmente regolari. Le regole
possono essere disattivate senza rimuoverle.

**Ambito di validità e ordine di applicazione:** una regola vale a
livello globale, per un fornitore, per un gruppo merceologico o per la
combinazione di entrambi. In presenza di più regole attive applicabili,
vince la più specifica: fornitore più gruppo merceologico prima di uno
solo dei due criteri, prima della regola globale. A parità, decide la
priorità della regola, poi la più recente. È così possibile mantenere
una maggiorazione standard a livello aziendale e sovrascriverla in modo
mirato per singoli fornitori o gruppi merceologici.

**Effetto sulle acquisizioni da catalogo:** le proposte compaiono
direttamente sugli articoli di catalogo collegati dei cataloghi
fornitori. Non arrivano mai automaticamente nel prezzo di vendita
dell'articolo: in modalità diretta l'operatore le acquisisce
espressamente, in modalità a quattro occhi nasce invece una richiesta
di approvazione. Una richiesta può essere approvata solo da una persona
diversa dal richiedente; i rifiuti possono essere motivati. La modalità
di approvazione (diretta o a quattro occhi) si imposta su questa pagina
per ciascuna organizzazione; le richieste aperte e quelle decise sono
consultabili lì.

Le operazioni già concluse e i prezzi storici non sono toccati dalle
modifiche delle regole — una regola modificata ha effetto solo sulla
successiva acquisizione di prezzo. La lettura richiede permessi di
lettura del magazzino, la gestione delle regole e delle richieste
permessi di configurazione del magazzino.
