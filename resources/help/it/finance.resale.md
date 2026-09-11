---
title: "Abbonamenti e licenze"
topic: finance.resale
version: 2
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
rimosso le cessioni. Anche un cambio di titolare nel tempo — un’azienda si
scinde e la nuova rileva i contratti — è una cessione: tutte le licenze del
vecchio periodo al titolare precedente; la riconciliazione lo propone su una
fattura dell’altro cliente come «Cedere il periodo a …», precompilato.

**Posta in arrivo:** gli abbonamenti importati la cui azienda il registro
non riesce ancora ad assegnare a un titolare finiscono nella posta in
arrivo. Per azienda decidi una volta: cliente, cliente finale di un partner
oppure parco proprio — il suggerimento viene dal confronto dei nomi con
clienti e clienti finali. La decisione viene memorizzata; l’import
successivo assegna subito la stessa azienda. Le righe che l’import non ha
potuto elaborare (data illeggibile, quantità senza numero, identificativo
duplicato) restano come rilievi sull’import: il numero nel messaggio, i
dettagli espandibili nell’elenco.

**Periodi:** la pagina dei periodi mostra i periodi scaduti di tutti gli
abbonamenti con riquadri di stato (aperto, fatturato, parziale, rinunciato,
contestato). «Calcola proposte» confronta i periodi aperti con le righe di
licenza delle fatture rispecchiate e crea proposte; tu le confermi, colleghi
a mano una riga (solo fatture dello stesso destinatario, solo mesi di
licenza liberi) oppure rinunci con un motivo («cortesia»). I periodi decisi
non vengono più toccati dalla pianificazione; «riapri» li riapre. Se una
fattura collegata viene poi stornata in Lexoffice, l’esecuzione successiva
azzera i mesi del collegamento, annota lo storno e riapre il periodo, così
la fattura sostitutiva può essere collegata.

**Bozza di fattura:** da tutti i periodi aperti di un destinatario fattura
nasce con un clic una bozza — con fatturazione Lexoffice come bozza in
Lexoffice (nulla viene finalizzato; verifichi ed emetti lì), con
fatturazione locale come bozza di fattura locale con righe e collegamenti
proposti. Una riga per abbonamento e periodo, cliente finale nella
descrizione, quantità in mesi per gli articoli mensili. I periodi ricordano
la bozza: un secondo clic non ne crea una seconda ma indica quella in
sospeso con numero e data; solo quando la bozza è diventata fattura o il
periodo è deciso tornano liberi. La finestra elenca solo destinatari con
periodi aperti e prezzo di vendita e indica sotto ciò che è già in una
bozza. La creazione richiede il diritto *Creare bozze di fattura dai
periodi*.

**Fatturazione ricorrente:** con la fatturazione locale il registro può
creare da solo le bozze ogni giorno. L’interruttore si trova nella pagina
*Classificazione prodotti* (diritto *Gestire il registro di rivendita*): attivo = la
prossima esecuzione crea, per ogni destinatario con periodi scaduti non
ancora in bozza, la stessa bozza del clic — righe per abbonamento e
periodo, periodi collegati come proposta e marcati. L’*anticipo* in giorni
include anche i periodi prima del loro inizio (0 = solo periodi scaduti).
I destinatari fatturati tramite Lexoffice/DATEV non vengono toccati, i
periodi senza prezzo di vendita vengono saltati, il patrimonio proprio non
viene mai fatturato. Le bozze si finalizzano nell’elenco fatture; il
riepilogo settimanale indica quante bozze degli ultimi sette giorni sono
ancora aperte. Una tantum o di prova: `php artisan resale:draft-local
--organization=… --dry-run` (`--force` ignora l’interruttore).

**Documenti di acquisto:** l’acquisto effettivo per abbonamento e periodo
proviene da tre fonti: (1) fatture e note di credito del fornitore in PDF
(Quality Hosting, layout tedesco e inglese) — ogni riga indica contratto,
cliente finale e durata, l’importo va esattamente al periodo; le righe di
nota di credito senza contratto valgono per l’azienda. (2) Documenti in
entrata dallo specchio documenti pro rata: per fatture cumulative senza
righe (Telekom) indichi la quota del fornitore e il mese di prestazione,
l’importo viene ripartito su tutti i periodi del mese, ponderato con il loro
acquisto previsto mensile. (3) Registrazioni di dominio dalla gestione
domini, automaticamente. All’import PDF il registro verifica il totale: se
la somma delle righe differisce dal totale del documento (per esempio una
pagina non letta), l’import avviene comunque e la differenza viene
segnalata. La pagina acquisti filtra per fornitore, fonte, periodo e testo
di ricerca; un’assegnazione si scioglie sempre per intero per documento.

**Report margine:** per prodotto e per destinatario fattura compaiono i
periodi scaduti dell’intervallo con vendita prevista (quantità × prezzo di
vendita), fatturato (importi netti dei collegamenti fattura, proposte
incluse), acquisto previsto (prezzo fornitore × quantità) e acquisto
effettivo dai documenti di acquisto. Margine = fatturato − acquisto;
l’acquisto effettivo conta non appena ogni periodo della riga ne ha uno,
altrimenti l’acquisto previsto. Gli importi non vengono mai sommati tra
valute — con più valute c’è una riga per valuta e un avviso. Esportazione
in CSV, XLSX o PDF; la proposta di fattura (periodi aperti con mesi di
licenza aperti e importo) in CSV o XLSX.

**Verifica prezzi:** per prodotto l’acquisto secondo contratto, il prezzo di
listino e il prezzo consigliato dell’ultimo listino importato a confronto
con i prezzi di vendita degli abbonamenti (minimo, mediana, massimo).
Avvisi: «vendita sotto acquisto», «vendita sotto prezzo consigliato»,
«contratto più caro del listino», «nessun prezzo di vendita».

**Classificazione prodotti:** quali articoli Lexoffice siano prodotti in
abbonamento il registro lo riconosce dal nome. Per articolo puoi forzare:
«prodotto in abbonamento» impone il riconoscimento, «mai riga di
abbonamento» tiene fuori da proposte, elenchi fatture e «righe senza
abbonamento» i servizi con un nome di prodotto nel testo (manutenzione su
Exchange). La stessa classificazione esiste per gli articoli attivi
dell’anagrafica articoli locale (sezione *Articoli locali*): decide quali righe
delle fatture locali lo specchio tratta come righe di licenza, e il controllo
prezzi confronta il prezzo di vendita dell’articolo con i prezzi degli
abbonamenti.

**Contratti:** un abbonamento può avere un contratto della gestione
contratti come quadro delle scadenze (campo «Contratto» nella finestra
dell’abbonamento; solo contratti cliente la cui controparte è il
destinatario della fattura dell’abbonamento). La scheda del contratto mostra
i suoi abbonamenti nel pannello «Abbonamenti e licenze». Nessun secondo
catalogo di scadenze: l’esecuzione giornaliera inserisce una data per
abbonamento nel calendario contrattuale — il termine di disdetta alla fine
per gli abbonamenti disdetti, un avviso di rinnovo prima del periodo
successivo per il rinnovo automatico, sempre anticipata del preavviso del
contratto (30 giorni se non indicato), con 14 giorni di preavviso. Se la
fine cambia, la data la segue; le date completate restano completate; gli
abbonamenti terminati o senza contratto chiudono la loro data aperta.

**Domini:** ogni dominio della gestione domini diventa ogni giorno un
abbonamento «Dominio» con intervallo annuale dalla registrazione, acquisto =
prezzo di rinnovo e titolare dalla gestione domini finché il registro non
ne ha deciso uno. Il prezzo di vendita per estensione viene dal listino
prezzi (fornitore rivendita domini, prodotto ad es. «.de»), l’articolo
dall’articolo Lexoffice dell’estensione; prezzi, articoli e titolari
inseriti a mano sopravvivono a ogni esecuzione. I domini scomparsi
terminano al giorno di riferimento; se l’elenco domini di un’esecuzione è
vuoto, nulla viene terminato. Gli abbonamenti di dominio e i loro
documenti di acquisto non si creano a mano — arrivano solo tramite la
sincronizzazione.

**Rinnovi e abbonamenti senza fattura:** il report «Rinnovi» mostra quali
abbonamenti si rinnovano o terminano nel periodo (riquadri 30, 60, 90
giorni): il rinnovo è l’inizio del prossimo periodo pianificato, per gli
abbonamenti disdetti conta la fine. «Senza fattura» elenca gli abbonamenti
il cui periodo scaduto aperto più vecchio risale a più di N giorni fa
(predefinito 60), con periodi aperti e importo aperto. Entrambi in CSV o
XLSX.

**Riquadro dashboard:** il riquadro «Periodi di abbonamento aperti» (gruppo
Finanze, disattivato per impostazione predefinita) mostra periodi aperti
con importo aperto, proposte non confermate e abbonamenti senza titolare e
porta alla pagina corrispondente.

**Rilievi di import:** ogni import (Telekom, Quality Hosting, listino,
elenco generico) registra contatori e rilievi per riga. Le date devono
essere date (le celle data di Excel vengono lette; «3.2026» o «2026» da
soli no), le quantità numeri, gli identificativi univoci all’interno di un
file — altrimenti la riga viene saltata e il motivo indicato.

**Conservazione dei file di import:** i file di import caricati (possono
contenere nomi di clienti finali) restano 90 giorni nella cartella di
archiviazione e vengono poi eliminati dalla pianificazione; il record di
import con i suoi contatori resta. A mano: `resale:prune-imports` (--days
modifica il termine).

**Riparazione dei collegamenti fattura:** se lo specchio documenti è stato
ricostruito in passato con nuovi ID di riga, i collegamenti confermati
puntano nel vuoto (collegamento senza testo di riga, periodo considerato
non coperto). Il comando `lexoffice:repair-resale-links` riaggancia tali
collegamenti tramite numero fattura e riga di licenza; ciò che non è
univoco viene solo elencato (--dry-run mostra in anticipo cosa
accadrebbe).
