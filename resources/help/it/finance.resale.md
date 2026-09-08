---
title: "Abbonamenti e licenze"
topic: finance.resale
version: 1
audience: []
modules:
    - module.reselling
related:
    - roles.buchhaltung
    - glossary.core
---

Il **registro di rivendita** tiene ogni servizio ricorrente rivenduto come
abbonamento: licenze Microsoft 365, domini, hosting, caselle e-mail, backup o
altro — indipendente dal fornitore, in un unico elenco.

**Titolare:** ogni abbonamento ha esattamente un titolare. Un **cliente**
viene fatturato direttamente. Un **cliente finale** appartiene a un partner;
la fattura va al partner, che la gira. Il **parco proprio** (licenze
interne, domini propri) non viene mai fatturato. Gli abbonamenti senza
titolare attendono l’assegnazione e contano in «Senza titolare».

**Durata e periodi:** da inizio, intervallo di fatturazione (annuale o
mensile) e fine il registro pianifica i periodi di fatturazione attesi — fino
a 90 giorni in avanti, così il prossimo rinnovo è visibile. Un abbonamento
senza fine si rinnova automaticamente. Un resto a fine durata più breve di un
mese (annuale) o cinque giorni (mensile) è un residuo di allineamento, non un
periodo. I periodi decisi (fatturato, parziale, rinunciato, contestato)
sopravvivono a ogni ripianificazione; i periodi aperti seguono le modifiche
di quantità, prezzo e fine.

**Prezzi:** acquisto e vendita per unità e intervallo, netti. L’articolo
fornisce prodotto e prezzo di vendita per le fatture. Vendita prevista per
periodo = quantità × prezzo di vendita.

**Stato:** attivo, disdetto (fine nota, i periodi vengono pianificati fino ad
allora), sostituito (successore presso un altro fornitore) e terminato. Gli
abbonamenti terminati e sostituiti non ricevono nuovi periodi.

**Eliminazione:** un abbonamento con periodi decisi non può essere eliminato
— impostalo su «terminato». Permessi: vedere con *Vedere il registro di
rivendita*, gestire con *Gestire il registro di rivendita*.

**Riconciliazione per destinatario fattura:** quando periodi restano aperti
e non è chiaro se manca una fattura o solo l’assegnazione, usa la
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

**Elenco generico:** oltre agli export dei fornitori (Telekom, Quality
Hosting) l’import accetta qualsiasi elenco CSV o XLSX le cui colonne siano
riconoscibili dal nome — tedesco o inglese: id, azienda, prodotto, quantità,
inizio, fine, intervallo, durata, prezzo di acquisto, prezzo di vendita,
fornitore, ordine. Obbligatori sono azienda, prodotto e inizio. Senza id
viene derivato da azienda, prodotto e inizio, così un nuovo import aggiorna
gli stessi abbonamenti invece di duplicarli. Il fornitore viene dalla
colonna o dal dialogo; un modello CSV è nel dialogo di import.

**Cedere licenze:** se due aziende condividono la sede e la seconda usa una
parte delle licenze di un contratto, cedi quelle licenze sul contratto
(«Cedere licenze»: titolare, quantità, periodo, prezzo di vendita). Nasce un
abbonamento separato per l’altro titolare con periodi propri; il contratto
pianifica i suoi periodi con il resto. Ogni titolare riceve le proprie
fatture assegnate. Se un successore sostituisce il contratto (import), la
cessione prosegue lì. Un contratto con cessioni si elimina solo dopo aver
rimosso le cessioni.
