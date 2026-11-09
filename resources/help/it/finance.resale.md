---
title: "Abbonamenti e licenze"
topic: finance.resale
version: 13
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
    - finance.resale.accounts
    - finance.resale.reconcile
    - finance.resale.board
    - finance.resale.subscription
    - finance.resale.periods
    - finance.resale.plan
    - finance.resale.reports
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

**Quantità e copertura:** il registro non consente mai di assegnare più di
quanto ci sia. A un periodo Lei assegna al massimo ciò che ha ancora aperto,
anche spostando un collegamento. Si possono cedere, ripartire o rilevare
solo licenze che nel periodo non sono né cedute né già fatturate al
titolare; una cessione al proprio titolare viene rifiutata e i casi
precedenti compaiono con «Revoca cessione». Una riga di un modello
ricorrente accoglie al massimo tante licenze quante ne fattura («Riga del
modello sovrassegnata» segnala i casi precedenti). Se un modello copre solo
una parte di un abbonamento, la proposta di fattura contiene il resto («4 di
9 licenze, 5 tramite modello ricorrente»); l’esecuzione automatica lo
esclude. Una riga di fattura copre al massimo tante licenze di magazzino
quanti pezzi ha.

**Elenco generico:** oltre agli export dei fornitori (Telekom, Quality
Hosting) l’import accetta qualsiasi elenco CSV o XLSX le cui colonne siano
riconoscibili dal nome — tedesco o inglese: id, azienda, prodotto, quantità,
inizio, fine, intervallo, durata, prezzo di acquisto, prezzo di vendita,
fornitore, ordine. Obbligatori sono azienda, prodotto e inizio. Senza id
viene derivato da azienda, prodotto e inizio, così un nuovo import aggiorna
gli stessi abbonamenti invece di duplicarli. Il fornitore viene dalla
colonna o dal dialogo; un modello CSV è nel dialogo di import.

**Posta in arrivo:** gli abbonamenti importati la cui azienda il registro
non riesce ancora ad assegnare a un titolare finiscono nella posta in
arrivo. Per azienda decide una volta: cliente, cliente finale di un partner
oppure parco proprio — il suggerimento viene dal confronto dei nomi con
clienti e clienti finali e dai testi delle fatture: se l’azienda compare nel
testo di fatture a un partner, la posta in arrivo propone quel partner, e un
clic apre l’assegnazione come cliente finale. Anche gli abbonamenti terminati
restano qui finché un periodo scaduto è aperto — senza titolare nessuno
troverebbe le loro fatture. «Assegnare il titolare» accanto a «Non ancora
assegnato» (conto licenze, abbonamento, periodi) apre la stessa assegnazione. La decisione viene memorizzata; l’import
successivo assegna subito la stessa azienda. Le righe che l’import non ha
potuto elaborare (data illeggibile, quantità senza numero, identificativo
duplicato) restano come rilievi sull’import: il numero nel messaggio, i
dettagli espandibili nell’elenco.

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
entrata dallo specchio documenti (Lexoffice, sevDesk, Akaunting, Invoice
Ninja e altri programmi; senza bozze, storni e documenti eliminati) pro rata: per fatture cumulative senza
righe (Telekom) indica la quota del fornitore e il mese di prestazione,
l’importo viene ripartito su tutti i periodi del mese, ponderato con il loro
acquisto previsto mensile. (3) Registrazioni di dominio dalla gestione
domini, automaticamente. All’import PDF il registro verifica il totale: se
la somma delle righe differisce dal totale del documento (per esempio una
pagina non letta), l’import avviene comunque e la differenza viene
segnalata. La pagina acquisti filtra per fornitore, fonte, periodo e testo
di ricerca; un’assegnazione si scioglie sempre per intero per documento.

**Classificazione prodotti:** l’elenco mostra tutti gli articoli del
catalogo articoli — gli articoli attivi dell’anagrafica articoli e quelli
dei programmi di contabilità collegati come Lexoffice, con la loro origine.
Anche easybill, sevDesk, Invoice Ninja, Akaunting e InvoicePlane forniscono
i loro articoli: il recupero dei documenti li legge prima dei documenti, e
le righe specchiate portano poi l’articolo dell’origine invece del solo
identificativo.
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
I domini di un account subutente sono fatturati dal portale dei domini
stesso: invia al subutente fatture mensili che il giornale delle
registrazioni assegna ai periodi. Questi domini non richiedono alcun modello
ricorrente e non entrano mai in una bozza di fattura del registro; la
cartella di lavoro e la pagina dell’abbonamento indicano il subutente.

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
I riquadri sopra gli elenchi portano a ciò che contano: nei conti licenze,
nella riconciliazione, nella dotazione di licenze e in un lotto filtrano
l’elenco; nella cartella di lavoro di un destinatario filtrano la tavola di
riconciliazione sui prodotti interessati, e lì un secondo clic sul riquadro
attivo rimuove il filtro.

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
settimanale lo ricorda. Un fornitore senza abbonamenti in corso, ad esempio
dopo un cambio, mostra «nessun abbonamento in corso» e non è considerato
obsoleto.

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

**Fatture presenti solo nel programma di contabilità:** Una volta al giorno
il registro legge anche i documenti dei fornitori associati dal programma di
contabilità (Lexoffice), quando il provider fornisce righe leggibili
(Quality Hosting). La ricezione fatture prevale: una fattura il cui numero è
già noto agli acquisti non viene riletta e, se arriva più tardi nella
ricezione, la sua origine rimanda poi a questa. Le fatture cumulative
(Telekom) si ripartiscono ancora a mano con «Assegna documento in entrata»,
perché il programma di contabilità conosce solo il totale del documento. Se
un provider con abbonamenti in corso non ha un fornitore, il riepilogo
segnala «Provider senza fornitore». Registrazioni dei domini: il provider
registra un rinnovo alcuni giorni prima del nuovo anno contrattuale; conta
come acquisto dell’anno che paga.

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
