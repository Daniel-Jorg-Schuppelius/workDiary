---
title: "Raccolte"
topic: knowledge.collections
version: 4
audience: []
related:
    - knowledge.articles
    - communication.notes
    - ideas.overview
    - documents.manage
    - learning.overview
---

Una **raccolta** ordina contenuti tra moduli diversi: note, mappe di idee,
articoli della knowledge base, documenti, corsi e percorsi formativi possono
stare insieme in una raccolta. L'appartenenza a cliente, intervento o progetto
resta prioritaria: la raccolta è l'ordine aggiuntivo e libero per tutto ciò che
non appartiene a un singolo caso.

Procedura tipica:

1. Crea una raccolta in **Raccolte**, se serve come sottoraccolta di un'altra.
   Le raccolte si annidano fino a cinque livelli e si possono spostare in
   seguito.
2. Nella pagina di dettaglio di un contenuto scegli **Aggiungi a raccolta**. Un
   contenuto può stare in più raccolte; non viene creata alcuna copia.
3. **Archivia** le raccolte che non servono più invece di eliminarle: le
   assegnazioni restano e si possono ripristinare.

**Una raccolta non concede accessi.** Ognuno vede solo ciò che può già vedere:
le note riservate di altri, le mappe di idee non condivise, i documenti
riservati e i contenuti formativi senza il modulo della piattaforma restano
nascosti, senza nemmeno mostrarne il numero. Una raccolta **privata** la vede
solo chi l'ha creata.

## L'accesso «Conoscenza»

La pagina **Conoscenza** mostra note, mappe di idee, articoli della knowledge
base, documenti, corsi e percorsi formativi in un unico elenco, oppure a
riquadri. A sinistra c'è l'albero delle raccolte (una raccolta include le sue
sottoraccolte); in alto filtrano titolo e tipo, i badge dei tag restringono
ulteriormente. Gli accessi esistenti a note, knowledge base, mappe di idee e
raccolte restano invariati.

Seleziona più contenuti e usa **Aggiungi** per metterli insieme in una raccolta;
lo stesso vale nei risultati della **Ricerca** per note, articoli e corsi.

## Convertire una nota in articolo

Nella finestra di lettura di una nota, **Converti in articolo della knowledge
base** crea una bozza: l'oggetto diventa il titolo, il testo la descrizione del
problema e i tag vengono mantenuti. L'articolo mostra la nota come origine in
«Citato in»; un secondo clic apre l'articolo esistente invece di crearne uno
nuovo. Le note riservate non possono essere convertite.

## Importare da Obsidian e OneNote

Gli amministratori importano note esistenti **una volta o su richiesta**: sola
lettura, senza riscrittura e senza sincronizzazione continua. L'accesso
**Conoscenza** offre due pulsanti:

- **Importa Obsidian** legge un vault Obsidian tramite una connessione cartella
  esistente dell'acquisizione documenti cloud (Nextcloud, OneDrive, Dropbox,
  Google Drive). Indica il percorso del vault relativo alla cartella radice
  della connessione. Le sottocartelle diventano raccolte, i tag dell'intestazione
  YAML e i `#tag` nel testo vengono mantenuti, i `[[wikilink]]` diventano
  riferimenti. `.obsidian/` e `.trash/` restano esclusi.
- **Importa OneNote** compare solo quando l'organizzazione ha attivato
  **Consenti importazione OneNote** nelle impostazioni del plugin Microsoft 365
  e ha usato **Connetti OneNote** nel pannello Microsoft 365. La connessione
  richiede l'autorizzazione aggiuntiva in sola lettura Notes.Read. Il blocco
  appunti diventa una raccolta, i gruppi di sezioni e le sezioni sottoraccolte,
  ogni pagina una nota o un articolo; il contenuto viene importato come testo.

Si importa come nota o come bozza di articolo. Ogni contenuto importato mostra
la sua origine («Importato da …»). Un'ulteriore esecuzione salta ciò che esiste
già e importa solo il nuovo: al massimo 300 nuovi contenuti per esecuzione.

## Riferimenti e backlink

Nelle pagine di dettaglio di questi contenuti, la scheda **Riferimenti** mostra a
cosa rimanda un contenuto e dove viene citato:

- **Aggiungi riferimento** collega il contenuto a una nota, una mappa di idee,
  un articolo della knowledge base, un documento, un corso o un percorso
  formativo. Con il campo di ricerca puoi restringere l'elenco.
- **Citato in** elenca, raggruppato per tipo, tutto ciò che punta alla pagina,
  compresi i collegamenti della knowledge base e le destinazioni convertite o
  collegate dai nodi delle idee. Clienti, progetti e ordini mostrano questo
  elenco non appena qualcosa rimanda a loro.
- **Rimuovi riferimento** elimina solo i riferimenti aggiunti a mano; i
  collegamenti della knowledge base e delle mappe di idee si gestiscono lì.

Come per le raccolte, un riferimento non concede accessi: una fonte compare solo
se puoi già aprirla.

Per creare e riempire raccolte e aggiungere riferimenti serve il diritto
«Gestire raccolte e riferimenti»; per vedere le raccolte, «Vedere le raccolte».
