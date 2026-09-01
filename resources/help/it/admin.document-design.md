---
title: "Design dei documenti"
topic: admin.document-design
version: 1
audience:
    - admin
modules:
    - module.dokumentdesign
related:
    - admin.branding
    - invoices.manage
---

Il design dei documenti adatta i PDF generati all'aspetto della
tua organizzazione: carta intestata, aree di stampa e aree bloccate,
blocchi informativi e preset di stile tabella curati.

Procedura:

1. **Carica la carta intestata** (PDF, JPG o PNG, A4 verticale) — un asset
   per la prima pagina e, facoltativamente, uno per le pagine successive.
   I PDF vengono ridotti a una pagina raster sicura e non interattiva;
   l'originale resta archiviato come prova.
2. **Crea un profilo** e definisci nell'editor aree di stampa, finestra
   indirizzo, riga mittente e aree bloccate in millimetri — visivamente o
   numericamente, anche da tastiera.
3. **Dichiara i blocchi informativi**: `dinamico` (WorkDiary stampa),
   `fornito dalla carta intestata` (con conferma per versione di profilo)
   oppure `non applicabile`. I blocchi obbligatori dei tipi di documento
   assegnati e i dati variabili sono protetti.
4. **Genera un documento di prova** per tipo di documento con testi lunghi,
   molte posizioni e più aliquote; il preflight mostra sovrapposizioni,
   blocchi obbligatori mancanti e problemi di contrasto.
5. **Attiva la versione** — solo con preflight senza errori. Le versioni
   attivate sono immutabili; le modifiche passano da una nuova bozza. I
   documenti finalizzati mantengono lo stato congelato.

Senza profilo vale lo standard di sistema (output attuale). Le fatture
ZUGFeRD/PDF-A-3 restano valide dopo l'applicazione del design — la fattura
strutturata resta vincolante.


Design base CI ed ereditarietà:

- Il profilo standard dell'organizzazione è il tuo **design base CI**.
  Le varianti per singoli tipi di documento (ad es. offerta, fattura,
  nota di credito, sollecito) o intere famiglie (vendite, acquisti,
  attestazioni) **ereditano** tutte le sezioni non sovrascritte — per
  ogni sezione è visibile se è ereditata o sovrascritta; «ripristina al
  design base» rimuove la sovrascrittura. La variante più specifica
  prevale: tipo prima della famiglia prima del design base.
- L'**anteprima PDF integrata** dell'editor renderizza tramite la
  stessa pipeline dell'output finale; tipo di documento e dati di
  esempio (testi lunghi, molte posizioni, più aliquote) sono
  commutabili.
- **Famiglia di caratteri e dimensione base** provengono da un elenco
  curato compatibile con i PDF; i colori primario/di accento possono
  **riferirsi al branding dell'organizzazione** — le modifiche al
  branding si applicano quindi automaticamente, senza copia dei colori
  nel profilo.
- All'attivazione il design base viene verificato contro i blocchi
  obbligatori di TUTTI i tipi di documento personalizzabili; i veri
  formati speciali (ad es. etichette) dichiarano la loro restrizione
  nel registro centrale dei tipi di documento.
- I **testi di intestazione/piè** dei documenti di vendita (in
  precedenza modelli di fattura) sono una sezione propria ed ereditabile
  del profilo — versionata e congelata per i documenti finalizzati. I
  **design specifici del cliente** sono profili regolari assegnati nella
  scheda cliente (pannello «Design dei documenti»); i profili contrassegnati
  come «specifico del cliente» agiscono solo tramite tale assegnazione.
- **Rifiniture:** l'ereditarietà vale per gruppo di impostazioni
  (margini, finestra indirizzo, aree bloccate, righe di intestazione/piè,
  tipografia, carta intestata, blocchi, stile tabella, testi). Gli
  **avvisi** del preflight bloccano l'attivazione finché non vengono
  confermati consapevolmente nel dialogo. Nuove anche le **righe di
  intestazione/piè** per pagina e tutte le opzioni dello stile tabella
  (griglia, spaziature, colori, ripetizione intestazione, enfasi totali).
