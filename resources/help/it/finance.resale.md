---
title: "Abbonamenti e licenze"
topic: finance.resale
version: 11
keywords:
    - Anteprima importazione
    - Azioni di massa
    - fatturazione abbonamenti
    - servizi ricorrenti
    - rivendita servizi
    - periodi di fatturazione
    - rinnovi
    - termine di disdetta
    - margine
    - fattura ricorrente
    - importare abbonamenti
    - rivenditore
    - Lexoffice
    - Telekom
    - Quality Hosting
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
Nell’elenco degli abbonamenti filtrate «Solo senza titolare», selezionate più
abbonamenti e assegnateli con «Assegna titolare» in un passo a un cliente, un
cliente finale o al parco proprio; un singolo abbonamento passa dall’icona
nella sua riga. I domini ricevono il titolare anche nella gestione domini, e
la casella porta lì gli abbonamenti senza nome azienda.

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
— lo imposti su «terminato». Permessi: vedere con *Vedere il registro di
rivendita*, gestire con *Gestire il registro di rivendita*.

**Riconciliazione per destinatario fattura:** quando periodi restano aperti
e non è chiaro se manca una fattura o solo l’assegnazione, usi la
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
parte delle licenze di un contratto, ceda quelle licenze sul contratto
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
arrivo. Per azienda decide una volta: cliente, cliente finale di un partner
oppure parco proprio — il suggerimento viene dal confronto dei nomi con
clienti e clienti finali. La decisione viene memorizzata; l’import
successivo assegna subito la stessa azienda. Le righe che l’import non ha
potuto elaborare (data illeggibile, quantità senza numero, identificativo
duplicato) restano come rilievi sull’import: il numero nel messaggio, i
dettagli espandibili nell’elenco.

**Periodi:** la pagina dei periodi mostra i periodi scaduti di tutti gli
abbonamenti con riquadri di stato (aperto, fatturato, parziale, rinunciato,
contestato). «Calcola proposte» confronta i periodi aperti con le righe di
licenza delle fatture rispecchiate e crea proposte; Lei le conferma, collega a
mano una riga (solo fatture dello stesso destinatario, solo mesi di licenza
liberi) oppure rinuncia con un motivo («cortesia»). I periodi decisi non
vengono più toccati dalla pianificazione; «riapri» li riapre. Se una fattura
collegata viene poi stornata in Lexoffice, l’esecuzione successiva azzera i
mesi del collegamento, annota lo storno e riapre il periodo, così la fattura
sostitutiva può essere collegata. Gli abbonamenti di dominio sono coperti
dalle righe che nominano il dominio (ad esempio «dominio .de incl. gestione
(thieme-logistik.de)»), indipendentemente dalla classificazione dell’articolo.
I domini che un subrivenditore regola tramite il portale del fornitore sono
coperti dal giornale contabile del modulo domini: un documento per sottoutente
e mese, numerato «numero cliente-MM-AAAA» (inserisca il numero cliente nella
pagina del sottoutente); il destinatario è il cliente del sottoutente.

**Bozza di fattura:** da tutti i periodi aperti di un destinatario fattura
nasce con un clic una bozza — con fatturazione Lexoffice come bozza in
Lexoffice (nulla viene finalizzato; verifica ed emette lì), con
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
righe (Telekom) indica la quota del fornitore e il mese di prestazione,
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

**Classificazione prodotti:** l’elenco mostra tutti gli articoli del
catalogo articoli — gli articoli attivi dell’anagrafica articoli e quelli
dei programmi di contabilità collegati come Lexoffice, con la loro origine.
Quali siano prodotti in abbonamento il registro lo riconosce dal nome. Per
articolo può forzare: «prodotto in abbonamento» impone il riconoscimento,
«mai riga di abbonamento» tiene fuori da proposte, elenchi fatture e «righe
senza abbonamento» i servizi con un nome di prodotto nel testo (manutenzione
su Exchange). La classificazione vale allo stesso modo per ogni origine:
decide quali righe di fattura lo specchio tratta come righe di licenza, e il
controllo prezzi confronta il prezzo di vendita dell’articolo con i prezzi
degli abbonamenti (un prezzo mensile conta dodici volte per un abbonamento
annuale).

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
dal catalogo articoli (anagrafica articoli o programma di contabilità) per l’estensione; prezzi, articoli e titolari
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

**Conto licenze:** Un conto porta il nome del suo intestatario; se la fattura
va a un partner, questo è indicato accanto («tramite LDS Systems»), e i
modelli ricorrenti del partner compaiono solo nel conto del cliente finale a
cui sono assegnati. La scheda *Conti licenze* mostra per conto fornitore
(numero cliente presso il fornitore, predecessori di un cambio fornitore
inclusi) quante licenze per prodotto sono attive, chi le detiene e quale anno
di licenza è fatturato, come griglia prodotto × intestatario × anno con numero
di fattura o «manca». Se più aziende detengono licenze dello stesso conto,
curate la **ripartizione** per prodotto («Märkische Bunker 5 × Exchange dal
18.10.2025»): gli altri intestatari riempiono prima il contratto con la
quantità maggiore, poi per inizio; l'intestatario del contratto tiene il
resto. Le cessioni per contratto ne derivano, seguono ogni modifica e passano
al successore in caso di cambio fornitore; le cessioni manuali restano
possibili come eccezione. La pianificazione ricalcola anche i contratti
sostituiti e terminati degli ultimi tre anni, così una ripartizione successiva
ne cambia ancora l'obiettivo. I **casi da verificare sui collegamenti**
mostrano righe che coprono più mesi di licenza di quanti ne abbiano
(sovrassegnate) e collegamenti il cui periodo di prestazione non coincide con
il periodo, con «Sposta su …» sul periodo giusto dello stesso abbonamento o
«Scollega»; la finestra di collegamento e l'assegnazione rapida avvisano prima
con ⚠.

**Modelli ricorrenti (piano):** Se un programma di contabilità emette le
fatture, le fatture ricorrenti nascono lì come modelli ricorrenti. Il registro
li specchia (Lexoffice: ogni giorno e dopo la sincronizzazione dei documenti,
in sola lettura) e mostra nel conto licenze per modello stato, ritmo, prossima
esecuzione e le righe di licenza con gli abbonamenti che fatturano.
L'assegnazione abbonamento ↔ riga del modello è proposta dall'elaborazione —
prima tramite la fattura che il modello ha creato per ultima ed è assegnata a
un periodo, altrimenti tramite stesso destinatario, articolo e quantità (con
più clienti finali di un partner solo se nominati): confermate, assegnate a mano o rimuovete. I periodi di un
abbonamento con modello attivo restano fuori dalla proposta di fattura. I
**controlli dotazione ↔ piano** segnalano prima della fattura: abbonamento senza
modello, modello senza abbonamento («Crea abbonamento dal modello» riprende
articolo, quantità, ritmo e prezzo), quantità diversa, modello che continua
oltre la fine dell'abbonamento, modello in pausa o terminato con abbonamento
attivo, prossima esecuzione a più di sette giorni dall'inizio periodo, ultima
esecuzione fallita: nel riquadro, nel riepilogo settimanale e nella coda di
lavoro. Ogni fattura specchiata porta il riferimento al suo modello.

**Panoramica e schede:** La pagina iniziale «Abbonamenti e licenze» è un
**elenco di attività in quattro gruppi**: Fatturare (prestazione scaduta
senza fattura, solo dopo 21 giorni di tolleranza e mai finché un modello
ricorrente attivo ha ancora davanti a sé l’esecuzione per il periodo; più
rilievi sui collegamenti fattura, ripartizioni oltre la dotazione, licenze a
pezzo senza fattura), Assegnare (abbonamenti senza titolare, righe di
licenza senza abbonamento), Verificare il piano (rilievi sui modelli
ricorrenti, cioè ciò che la prossima fattura sbaglierebbe) e Stato fornitore
(importazioni obsolete, rinnovi entro 30 giorni). Ogni riga mostra numero e
importo e porta alla pagina in cui si decide. Accanto lo **stato**:
l’ultima esecuzione delle proposte, le proposte non confermate come
campione, i periodi pianificati tramite modello o ancora in tolleranza, per
fornitore l’ultima importazione con la sua età. Le schede seguono il
flusso: Panoramica · Conti licenze · Riconciliazione · Periodi · Posta in
arrivo │ Abbonamenti · Scorte licenze · Acquisti │ Report · Prezzi;
classificazione prodotti e fatturazione stanno nel menu ⋯.

**Proposte sicure e incerte:** L’esecuzione delle proposte (ogni ora e dopo
ogni importazione) conferma da sola le corrispondenze sicure — un documento
la cui data fattura è al massimo 14 giorni prima o 45 giorni dopo l’inizio
del periodo e che copre il periodo per intero con il numero di licenze
corretto; la conferma compare nel registro come «confermata
automaticamente». Tutto il resto resta proposta e porta «incerta» con il
motivo nel suggerimento. Le proposte valgono senza conferma:
la pagina dei periodi mostra per impostazione predefinita «Solo problemi»
(aperto, parziale, contestato); le proposte non confermate si consultano
come campione tramite il filtro e lo stato della panoramica. Un clic sul numero del
documento apre il documento: righe, periodo di prestazione, testo fattura,
consumo per riga e il confronto con il periodo (data nella finestra,
prestazione, articolo); così vedete prima di confermare se la fattura
appartiene al periodo.

**Azioni di massa:** Nella pagina dei periodi selezionate le righe con le
caselle e confermate tutte le proposte in una volta, rinunciate con un
motivo per tutte («Rinuncia / contesta …») o fate creare una bozza per
destinatario fattura della selezione. I periodi decisi e futuri vengono
saltati e contati; ogni periodo riceve la stessa voce di registro
dell’azione singola. Non c’è preselezione.

**Classificazione prodotti con filtro:** La classificazione mostra per
impostazione predefinita solo i prodotti in abbonamento e gli articoli con
abbonamenti o righe di modello; «Tutti gli articoli», «Senza
classificazione» e la ricerca per nome o numero restringono. Se un programma
di contabilità emette le fatture, ogni articolo indica in quanti modelli
ricorrenti attivi compare.

**Importazione con anteprima:** Dopo il caricamento il registro mostra
prima cosa cambierebbe l’importazione — per file nuovo, modificato
(quantità, prezzo, stato, inizio, fine con il valore precedente), invariato
e gli abbonamenti pianificabili del fornitore che il file non cita più
(l’importazione non li termina). Si applica solo dopo «Applica»; «Annulla»
elimina i file. Se il fornitore cambia un prezzo, l’importazione aggiorna
il prezzo d’acquisto dell’abbonamento finché coincide con l’ultimo prezzo
del fornitore — un prezzo inserito a mano resta — e scrive una riga di
catalogo dalla data del file (data nel nome come «2026-10-26_Export.xlsx»,
altrimenti il giorno dell’importazione). Se l’ultima importazione di un
fornitore ha più di 30 giorni, la panoramica lo segnala e il riepilogo
settimanale lo ricorda.

**Acquisti dalla posta fatture:** Nella scheda Acquisti, con «Associa
fornitori» collegate un fornitore dell’anagrafica al provider (il nome offre
un suggerimento; nulla viene associato automaticamente). Appena una fattura
in entrata di quel fornitore è assegnata nella posta fatture, il registro la
acquisisce come acquisto: Quality Hosting per riga tramite il lettore di
fatture (contratto e durata per riga), provider di domini per nome di
dominio sull’abbonamento del dominio (solo se il giornale contabile del
registrar non fornisce già i costi), tutti gli altri pro rata sui periodi del
mese della fattura. Ogni riga porta la fattura in entrata come origine; una
seconda esecuzione la riconosce dall’hash. Le righe senza abbonamento
restano «senza abbonamento» — il filtro «Solo senza abbonamento» le mostra,
«Assegna abbonamento» le collega all’abbonamento giusto e al periodo della
data del documento. `resale:import-incoming --since=` recupera
retroattivamente le fatture assegnate. Il conto licenze mostra per prodotto
acquisto previsto ed effettivo e il margine degli ultimi dodici mesi; il
riepilogo settimanale segnala gli abbonamenti il cui prezzo contrattuale si
discosta dall’ultimo prezzo di catalogo o del provider importato.

**Collegamento alla fattura per le licenze singole:** Quando vende una
licenza dal magazzino sceglie la riga di fattura del programma di
contabilità che fattura questa licenza; l’elenco mostra le righe del cliente
scelto intorno alla data di vendita, quelle corrispondenti (stesso articolo
o nome del prodotto) vengono prima e sono proposte. Senza riga il
riferimento della fattura resta testo libero. Elenco licenze, pagina del
lotto e scheda cliente mostrano per la riga collegata il numero del
documento con il salto al programma di contabilità e all’immagine del
documento. Una vendita che dopo 30 giorni non porta né riga né riferimento
compare come caso da verificare «Licenze singole senza fattura» nella
panoramica, nella riconciliazione del destinatario e tramite il filtro
«Senza fattura» del magazzino licenze; aggiunga il collegamento con
«Correggi vendita». Nel portale clienti i clienti vedono le licenze vendute
con il numero di fattura, mai la chiave.
