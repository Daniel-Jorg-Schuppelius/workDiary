---
title: "Fatture & documenti"
topic: invoices.manage
version: 22
keywords:
    - luogo della prestazione
    - prestazione del catalogo
    - immobile
    - creare fattura
    - fattura attiva
    - fatturazione
    - stornare fattura
    - nota di credito
    - fattura elettronica
    - XRechnung
    - Peppol
    - link di pagamento
    - pagamento online
    - supplemento rame
    - Stripe
    - Mollie
    - SumUp
    - documenti collegati
    - catena dei documenti
    - trasmettere fattura a Lexoffice
    - richiesta sulla fattura
    - attestazione delle prestazioni
    - documenti giustificativi
    - pubblica amministrazione
    - Leitweg-ID
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - contacts.manage
    - projects.manage
    - finance.datev-bookings
    - finance.transfers
    - travel-expenses.manage
---

## Scopo e contesto

La panoramica fatture gestisce le fatture locali e i documenti
collegati. Quale via guida dipende dall'organizzazione e
dall'integrazione di fatturazione in uso: per periodo emette le
fatture WorkDiary oppure esattamente un sistema esterno — mai
entrambi insieme.

## Prerequisiti

- Anagrafiche verificate: cliente, indirizzo del destinatario, dati
  fiscali.
- **Periodo di prestazione e collegamento al progetto** delle
  posizioni da fatturare.
- Il diritto di creare fatture; per i solleciti il rispettivo ruolo
  finanziario.

## Procedura consigliata

1. Scelga cliente e periodo — la finestra di creazione mostra
   un'**anteprima** delle posizioni (numero, durata in formato orario
   e decimale, importo, avviso ritardatari).
2. Escluda se serve singole registrazioni con la casella — restano
   aperte e ricompaiono al giro successivo.
3. Controlli e completi la bozza; per posizione si espandono le
   **registrazioni di origine** (1,50 h = 1:30 h). Per un articolo con peso
   di rame, la casella **supplemento rame** nella finestra della riga
   aggiunge il supplemento al prezzo DEL del giorno come riga separata.
4. Emetta o invii — PDF, invio e sincronizzazione esterna sono uscite
   dello stesso stato documentato.
5. In caso di ritardo usi il **sollecito**: il livello 1 crea un
   promemoria di pagamento come PDF separato con riepilogo crediti,
   eventuale spesa e scadenza; l'e-mail contiene lettera e fattura
   originale. Non nasce un nuovo documento.

**Fattura elettronica.** La XRechnung viene generata in sintassi UBL; se un
destinatario richiede CII, scelga sul cliente o all’invio il formato di
consegna «XRechnung (XML, sintassi CII)». Tramite Peppol si usa sempre UBL.
Senza partita IVA — ad esempio come piccola impresa ai sensi del § 19 UStG —
basta il codice fiscale nei dati di fatturazione elettronica: viene indicato
anche come identificativo del venditore, richiesto dalla verifica del
destinatario.

## Esempio pratico

A fine mese la contabilità sceglie «Müller GmbH» e il mese
precedente: l'anteprima mostra 14 posizioni e segnala due tempi
ritardatari. Una registrazione contestata viene esclusa e passa
automaticamente al giro successivo — la fattura parte senza
discussioni.

## Errori tipici

- **Modificare in silenzio documenti inviati o consegnati:** i
  documenti emessi, contabilizzati o consegnati sono immutabili — per
  gli errori c'è lo storno o la correzione.
- **Sovrascrivere numeri o importi** invece di correggere — si
  distrugge la tracciabilità.
- **Doppia titolarità di fatturazione:** se un sistema esterno guida
  la fatturazione, le fatture locali volutamente non esistono in
  parallelo.

## Effetti e prossimi passi

Le fatture emesse alimentano partite aperte, solleciti e consegna
contabile. Poi: controllare incassi e abbinamenti e creare il lotto
DATEV per lo studio.

## Fattura libera senza tempi

Nella finestra di creazione, **«Comporre le righe manualmente»** è alla pari
con la ripresa da tempi o consumo di materiale. La bozza richiede solo il
cliente (facoltativi progetto, cliente finale e termine di pagamento) e parte
vuota: può essere salvata e completata dopo, ma non emessa né inviata finché
non ha righe — vale allo stesso modo per emissione, e-mail, Peppol e
trasferimento a Lexoffice. Un doppio clic su «Crea bozza» non genera una
seconda fattura.

**Articoli, materiale e prestazioni.** Una riga è un articolo (con variante
facoltativa), materiale o testo libero — ad esempio «Montaggio forfettario» o
una produzione speciale senza ordine di produzione. Descrizione, numero
articolo, unità e prezzo vengono congelati come valori del documento; le
modifiche successive all'anagrafica non cambiano il documento. Un prezzo
mancante va inserito consapevolmente (0,00 è ammesso come riga gratuita); un
prezzo in valuta estera non viene mai convertito in silenzio. Le righe articolo
e a testo libero **non movimentano il magazzino**; le consegne passano da
magazzino/consegna.

**Fatturare consegne di produzione.** Con il modulo magazzino attivo, «Riprendi
consegna» importa le consegne effettuate del cliente con destinazione di
fatturazione locale — ciascuna per intero come una riga, con quantità
vincolata alla fonte e prezzo di vendita della consegna (non il costo di
produzione). Una consegna può stare in una sola bozza alla volta; l'emissione
la segna come fatturata, rimuovere la riga, scartare la bozza o uno storno
totale la liberano di nuovo e l'origine resta visibile sul documento. Le note
di credito parziali non liberano nulla; il magazzino resta invariato in tutte
le operazioni di fattura.

## Forfait dai tempi

Se a un tipo di attività è associata una prestazione con l'unità «forfait» o
«Pauschale» — ad esempio un articolo nella regola di fatturazione del progetto o
come prestazione predefinita dell'organizzazione —, la fatturazione, il
trasferimento e il giustificativo dei tempi Lexoffice fatturano **una riga con
quantità 1 per ordine** al prezzo di tale prestazione, indipendentemente da quanti
tempi sono stati registrati. I tempi senza ordine danno un forfait per progetto e
tipo di attività. I tempi restano collegati alla riga come giustificativo e
risultano fatturati. L'arrotondamento, i minuti di trasferta della condizione
cliente e l'integrazione fino alle ore concordate non valgono per i forfait. Se la
prestazione non ha un prezzo, il forfait compare con 0,00 nell'anteprima. Valgono
come forfait «forfait», «forf.», «Pauschale» e «pauschal», oltre agli equivalenti
nelle altre lingue come «flat». Qualsiasi altra unità — vuota, «ora», «giorno»,
«pz.», «km» o testo libero — continua a fatturare in base alla durata.

## Pagamento online

Se un fornitore di pagamento (Stripe, Mollie o SumUp) è configurato come
plugin, le fatture emesse riportano un link di pagamento con codice QR; lo
stesso indirizzo compare nell'e-mail tramite il segnaposto
{{payment_link}} e nel portale clienti. Il link passa sempre da workDiary:
la pagina di pagamento del fornitore viene creata solo all'apertura, per
l'importo in quel momento ancora aperto, così un codice stampato resta
corretto dopo pagamenti parziali.

Quando il fornitore conferma il pagamento, la fattura risulta pagata o
pagata in parte, come con un movimento bancario assegnato; un rimborso lo
annulla. Con la contabilità locale il pagamento compare nella casella
delle registrazioni (denaro in transito contro il credito, la commissione
del fornitore come spese bancarie e di pagamento); il successivo accredito
del fornitore lo registri sul conto del denaro in transito. Un pagamento
anticipato online del noleggio viene registrato già alla data del
pagamento come acconto ricevuto, con IVA; la fattura che lo scomputa
compensa l'acconto e riporta come credito solo il residuo aperto. Il link
su fattura ed e-mail si disattiva nelle impostazioni dell'organizzazione,
alla voce «Pagamento online»; nel portale clienti rimane.

## Annullare e fatturare di nuovo

Una bozza si annulla direttamente, una fattura emessa tramite un documento
di storno con numero proprio. Entrambe le vie liberano tutto ciò che la
fattura ha fatturato, come lo storno di un trasferimento: tempi
registrati, materiale, trasferte, spese, voci di noleggio, consegne e
l'integrazione fino al tempo obiettivo del progetto. Un pagamento
anticipato online del noleggio torna alla sua prenotazione e viene
scomputato dalla fattura successiva. Le voci della fattura annullata
restano invariate; la fattura successiva per il periodo contiene di nuovo
le ore e l'integrazione. Una fattura pagata si corregge con una fattura di
rettifica. Questa non libera nulla, perché può accreditare anche solo una
parte della fattura.

Anche una fattura emessa che solo un pagamento anticipato online copre in
parte si storna tramite un documento di storno: il pagamento anticipato torna
alla sua prenotazione e viene scomputato dalla fattura successiva. Se un altro
pagamento copre la fattura in parte – un movimento bancario nella
riconciliazione dei pagamenti (anche uno sconto accettato), un pagamento in
cassa, un pagamento online della fattura o un pareggio della partita aperta –,
lo storno resta bloccato e la fattura ne indica il motivo. Annulli prima quel
pagamento; quando la fattura è pagata per intero, la corregga con una fattura
di rettifica.

Con la contabilità locale, il documento di storno pareggia esattamente la
partita aperta della fattura stornata, dopo un pagamento anticipato il resto
dopo l'acconto; le altre partite aperte del cliente restano invariate.
L'acconto torna poi aperto e viene chiuso con la fattura successiva. Registri
quindi la fattura prima del suo documento di storno; altrimenti il documento
di storno attende nella casella di registrazione con un avviso. Anche una
fattura stornata con documento di storno vi compare da registrare, perché
entrambi i documenti appartengono al giornale. Il documento di storno non
conta come pagamento, nemmeno nei prospetti per cassa (conto per cassa, IVA
per cassa).

## Documenti collegati

In fondo alla pagina, **Documenti collegati** elenca tutti i documenti legati
a questo: l'offerta e le sue versioni, fatture, acconti e fattura finale, note
di credito e storni, documenti di trasporto, ordini di produzione e
trasmissioni. Se un programma di contabilità ha ripreso un documento, il suo
corrispondente compare lì con il numero del programma. Compaiono anche i
collegamenti segnalati dal programma stesso, per esempio un acconto creato in
Lexoffice. La colonna **Relazione** indica che cosa è il documento rispetto a
questo; «tramite …» nomina il documento attraverso cui esiste il
collegamento.

## Numero dal programma di contabilità

Se un programma di contabilità assegna i numeri di fattura (pagina
**Serie numeriche**), la bozza ha un numero provvisorio e la fattura mostra un
avviso. **Emettere** – anche l'invio di una bozza per e-mail – trasmette la
fattura al programma e ne riprende il numero prima che nascano PDF e oggetto.
Lexoffice ed easybill riprendono la conferma d'ordine o l'offerta come documento precedente; orgaMAX crea la fattura tramite un ordine e la blocca. Nota di credito e storno vengono trasmessi con
riferimento alla fattura trasmessa; per sevDesk ed easybill la fattura deve trovarsi nel programma; orgaMAX non riprende nessuno dei due. Se il programma rifiuta, il documento resta una bozza
e il messaggio ne indica il motivo. In tal caso crei le fatture di acconto e
finali nel programma.

## Richieste sulle fatture

Quando un cliente risponde a una fattura per e-mail, WorkDiary registra
durante lo scarico della casella una **richiesta sulla fattura**: nella
cronologia della fattura, come nota di comunicazione presso il cliente e
nella sezione **Richieste** della pagina della fattura. Vengono riconosciute
la risposta all'e-mail di fattura inviata da WorkDiary e un numero di fattura
o di documento nell'oggetto o nel testo – anche per documenti emessi da
Lexoffice, sevDesk, easybill o da un altro programma collegato. Per un
numero il mittente deve appartenere alla pratica (cliente, referente,
account del portale o destinatario dell'e-mail di fattura); altrimenti
l'e-mail finisce contrassegnata nella posta di assegnazione, come pure in
presenza di più numeri. Ogni messaggio viene registrato una sola volta, le
risposte automatiche di assenza mai.

L'elenco di lavoro **Richieste sulle fatture** (pulsante nel flusso dei
documenti) mostra le richieste aperte e risposte di tutte le fatture e di
tutti i documenti; **Contrassegna come risposta** chiude una richiesta,
**Riapri** la riporta. Entrambe compaiono nella cronologia della fattura.

## Documenti giustificativi e attestazione delle prestazioni

Fogli ore e attestazioni delle prestazioni viaggiano incorporati nella fattura
elettronica: nella XRechnung, nel PDF ZUGFeRD e tramite Peppol. Così lo
richiedono le amministrazioni e il fisco: un allegato e-mail separato o un link
non fa parte della fattura. La scheda **Documenti giustificativi** della pagina
della fattura offre tre vie finché la fattura è una bozza:

- **Crea attestazione delle prestazioni** genera un PDF dai tempi fatturati: per
  posizione data, persona, orario, durata e attività, con le approvazioni dei
  fogli ore firmati. Su richiesta si aggiungono i fogli firmati e una tabella
  CSV (organizzazione, sezione **Fattura elettronica: documenti e destinatari**).
- **Aggiungi documento** accetta un file proprio: PDF, PNG, JPEG, CSV, XLSX o ODS
  senza password. I destinatari non accettano altri formati.
- **Aggiungi link** serve solo per i portali, come i grandi allegati di OZG-RE.
  Ai fini fiscali un link non sostituisce un allegato incorporato.

WorkDiary assegna i nomi dei file (`nachweis-1-…`) perché siano univoci.
**Emetti** fissa i documenti; un’attestazione generata viene ricreata con il
numero definitivo. Dopo, i documenti non si possono più modificare né rimuovere.
Con l’invio in PDF i documenti vanno come file separati nella stessa e-mail.

La fattura elettronica indica il momento della prestazione nella parte
strutturata: la data o il periodo delle posizioni, altrimenti la data della
fattura. La descrizione delle prestazioni resta nelle posizioni;
l’attestazione la integra.

### Clienti della pubblica amministrazione

Nella scheda del cliente impostare il **profilo destinatario della fattura
elettronica** su «Pubblica amministrazione». Allora:

- La Leitweg-ID (riferimento dell’acquirente) è obbligatoria. Se struttura o
  cifra di controllo sono errate, il controllo prima dell'invio avvisa; lo stesso
  vale per i clienti indirizzati tramite Peppol con lo schema 0204.
- Via e-mail parte solo la XRechnung, senza PDF accanto; il dialogo d’invio
  offre solo questi formati. L’invio elettronico emette prima la bozza, così
  l’XML porta i documenti fissati.
- L’attestazione delle prestazioni viene allegata automaticamente all’emissione
  se la fattura contiene tempi.
- La fattura elettronica con documenti può pesare al massimo 10 MB; il
  controllo lo segnala oltre. Il limite si cambia per cliente, come pure se
  l’attestazione va sempre o mai allegata.

Se un programma di contabilità assegna il numero, i documenti restano in
WorkDiary: WorkDiary non li trasmette a Lexoffice, sevDesk, easybill o orgaMAX.
Chi invia la fattura dal programma li allega lì a mano; la casella delle
integrazioni lo ricorda. Il trasferimento offre **Attestazione delle
prestazioni** da scaricare.

## Liberare le ritenute a garanzia

Le ritenute compaiono nella pagina della fattura, nella scheda **Ritenute a
garanzia**, con tipo, importo, data di liberazione e stato. Su una fattura
emessa, l’icona del lucchetto apre la finestra **Libera**; una nota ne
registra il motivo. Con la liberazione l’importo diventa una normale partita
aperta, rientra nell’importo da pagare e viene sollecitato. Una ritenuta
sostituita da una fideiussione è già stata pagata: la sua liberazione pone
fine solo allo scopo di garanzia, non nasce alcun nuovo credito e il registro
delle fideiussioni segnala l’atto da restituire. Per liberare serve il
permesso di fatturazione.

## Approvazione e regime fiscale della bozza

Con l'**approvazione obbligatoria**, l'approvazione vale per lo stato verificato:
se in seguito modifica posizioni, condizioni, cliente, date o il testo della
fattura, l'approvazione viene revocata e va concessa di nuovo, non dalla persona
che ha creato la fattura. Il registro annota la revoca.

Ogni bozza riprende il proprio **regime fiscale** (inversione contabile,
esportazione, esenzione) dai dati del cliente, anche la bozza libera. Se i dati del
cliente cambiano in seguito — ad esempio con l'attributo «Il destinatario deve
l'IVA» o una nuova partita IVA —, la modifica successiva della bozza applica il
nuovo regime. Se emette una bozza con un regime fiscale superato, workDiary la
adegua e interrompe l'emissione: verifichi gli importi ed emetta di nuovo.

Con l'**inversione contabile**, la fattura elettronica richiede la partita IVA del
cliente (regola BR-AE-02); per l'inversione nazionale ai sensi del § 13b UStG
basta il suo numero di registro. Il controllo prima dell'invio segnala se manca il
dato necessario. Il numero di registro compare nella fattura elettronica come
identificativo di registrazione dell'acquirente (BT-47). Tutte le posizioni compaiono allora
allo 0 % con la dicitura «Inversione contabile» nella XRechnung.

Il **testo di avviso** segue il regime fiscale: se l'avviso automatico del regime
precedente (ad esempio sull'inversione contabile o sull'esportazione) è ancora
invariato nel testo della fattura, viene sostituito dall'avviso del nuovo regime o
eliminato. Se ha modificato l'avviso a mano, il suo testo resta: workDiary lo
segnala e lei verifica l'avviso personalmente.

## Luogo della prestazione dei servizi

Oltre al cliente, il **tipo di luogo della prestazione** determina il regime
fiscale (§ 3a UStG). Si imposta dove la prestazione è definita: sulla **regola
di fatturazione** del progetto, altrimenti sul suo **articolo** o sull’articolo
di prestazione della posizione, altrimenti nel valore predefinito
dell’organizzazione (impostazioni dell’organizzazione, scheda
**Fatturazione**). Di serie tutto è **Generale**: senza interventi nessun
regime fiscale cambia.

- **Generale:** come finora; IVA tedesca per consumatori extra UE (§ 3a c. 1
  UStG), «non imponibile» per imprese extra UE.
- **Prestazione del catalogo** (§ 3a c. 4 UStG), ad esempio consulenza,
  elaborazione dati, diritti, pubblicità, noleggio di beni mobili: «non
  imponibile» (O) con una propria indicazione per consumatori extra UE,
  altrimenti come «Generale».
- **Prestazione relativa a un immobile** (§ 3a c. 3 n. 1 UStG), ad esempio
  lavori edili, di finitura e di pulizia di edifici: se l’immobile si trova in
  Germania, la fattura porta l’IVA tedesca, anche per clienti di altri paesi UE
  ed extra UE; se il cliente è debitore dell’imposta secondo il § 13b UStG,
  resta così. Se si trova all’estero, la prestazione è «non imponibile» (O).
  L’immobile si trova nel luogo d’intervento dell’ordine; se non indicato, vale
  la Germania.

Le merci (fatture di materiale) non sono interessate. Per le fatture dai tempi
conta la regola dei tempi fatturati, per le posizioni libere l’articolo di
prestazione. Le posizioni senza articolo, le trasferte e le posizioni con una
propria categoria fiscale seguono la fattura. Se le posizioni danno regimi
fiscali diversi, la bozza lo segnala, la fattura non può essere emessa e il
controllo prima dell’invio la respinge (con «non imponibile» secondo la regola
BR-O-11): divida la fattura, una per regime fiscale. L’annullamento e la
fattura rettificativa riprendono il regime fiscale dell’originale.

## Motivi di esenzione nella fattura elettronica

Ogni categoria IVA senza imposta riporta il proprio motivo di esenzione nella
XRechnung e nel PDF ZUGFeRD, nelle fatture miste per ciascun riepilogo IVA:
inversione contabile (AE), cessione intracomunitaria (K), esportazione (G) e «non
imponibile» (O) con testo e codice; la categoria Z non ha motivo. «Esente» (E) cita
il § 19 UStG solo per le piccole imprese. Per altre esenzioni crei nella **matrice
delle regole fiscali** una regola con il tipo di aliquota «Esente» e inserisca la
norma di esenzione come testo di avviso del giustificativo, ad esempio «Cure mediche
esenti (§ 4 n. 14 UStG)»; senza regola compare solo «Operazione esente» e il
controllo prima dell'invio avvisa.

**Paesi terzi:** le merci per clienti fuori dall'UE sono esportazioni (G) con
il motivo «Cessione all'esportazione esente». Le prestazioni a imprese fuori
dall'UE non sono imponibili in Germania (§ 3a co. 2 UStG) e figurano come «non
imponibile» (O) nella fattura elettronica, nel PDF della fattura e nella
contabilità: senza partite IVA, con il codice fiscale dell'organizzazione e
senza altre categorie accanto. Senza codice fiscale il controllo prima
dell'invio si blocca. Le fatture emesse in precedenza con la categoria G vengono
ristampate senza modifiche.

Il controllo prima dell'invio segnala inoltre: partita IVA del cliente mancante nelle
cessioni intracomunitarie, partita IVA dell'organizzazione mancante nelle cessioni
verso altri paesi UE e nelle esportazioni, «non imponibile» accanto ad altre
categorie e una posizione di categoria senza imposta con aliquota superiore allo 0 %.
Con «non imponibile» la fattura elettronica non riporta partite IVA; basta il codice
fiscale. Nelle cessioni intracomunitarie la fattura
elettronica indica l'indirizzo di consegna diverso del cliente (dialogo cliente,
sezione **Indirizzo di consegna**), altrimenti il suo indirizzo, come indirizzo
di consegna con il paese di consegna (regola BR-IC-12); la XRechnung vi richiede
CAP e città, altrimenti il controllo prima dell'invio si ferma. L'indirizzo di
consegna viene congelato all'emissione; storno e fattura di rettifica riprendono
quello dell'originale. La registrazione dell'organizzazione (**Branding**, campo **Registro delle imprese**) compare nella fattura elettronica come
identificativo di registrazione del venditore (BT-30), anch'essa congelata. Le posizioni «non imponibile» compaiono
senza aliquota nella fattura elettronica.

## Indicazione di esenzione nel PDF della fattura

Le posizioni senza imposta richiedono un'indicazione anche sulla fattura stessa
(§ 14, comma 4, n. 8 e § 14a UStG). Sotto il riepilogo dell'imposta il PDF stampa
per ogni categoria fiscale lo stesso testo del motivo di esenzione della fattura
elettronica, ad esempio «cessione intracomunitaria esente» per K o la dicitura di
inversione contabile per una singola posizione con AE. Se il testo della fattura
contiene già l'indicazione — o l'indicazione automatica di questa categoria
derivante dal regime fiscale — la riga viene omessa. L'inversione contabile
dell'intero documento e il § 19 UStG mantengono la propria riga come finora. Le
fatture emesse prima di questa estensione vengono ristampate senza modifiche. La
nota di credito e la fattura di storno riprendono la categoria fiscale di ogni
posizione dell'originale.

Se sceglie una categoria senza imposta (AE, Z, E, G, K, O) su una posizione o in un
piano di fatturazione, il modulo imposta l'aliquota allo 0 %; in ogni caso viene
salvato 0 %.

## Destinatario e partita IVA sulla fattura

Una fattura emessa stampa il destinatario così com'era al momento dell'emissione:
nome, società, indirizzo e partita IVA del cliente vengono congelati con
l'emissione. Se in seguito modifica i dati del cliente, la ristampa, l'allegato
PDF, la XRechnung, lo ZUGFeRD e l'esportazione GAEB restano invariati; solo le
nuove fatture riprendono il nuovo indirizzo. Una bozza mostra sempre i dati
attuali del cliente. Il documento di storno e la fattura correttiva congelano il
destinatario alla propria emissione. L'indirizzo di recapito elettronico
(identificativo Peppol o e-mail) e i solleciti seguono invece i dati attuali del
cliente, perché vengono recapitati oggi.

Insieme al destinatario, l'emissione blocca anche i **dati
dell'organizzazione**: ragione sociale, indirizzo, codice fiscale, partita IVA,
recapiti, coordinate bancarie (blocco bancario e codice QR per il bonifico), il
regime dei piccoli imprenditori e la **lingua del documento**. La ristampa, la
XRechnung e lo ZUGFeRD di una fattura emessa restano come sono stati emessi —
anche dopo un cambio di banca, un nuovo indirizzo o un'altra lingua del documento
del cliente; le bozze e le nuove fatture usano i dati attuali. I testi della
fattura elettronica sono nella lingua del documento, non in quella della sua
interfaccia. I solleciti indicano le coordinate bancarie attuali.

Se il cliente non ha più una partita IVA registrata, la fattura di storno e la
fattura di rettifica relative a reverse charge o a una cessione intracomunitaria
riprendono la partita IVA della fattura originale e un numero di registro
mancante; il controllo prima dell'invio non si blocca più.

Se per il cliente è registrata una partita IVA, compare nell'intestazione della
fattura («Vostra partita IVA»). Per le cessioni intracomunitarie e l'inversione
contabile è obbligatoria (§ 14a UStG). Le fatture emesse prima di questa
estensione vengono ristampate senza la riga.

L'emissione fissa anche l'indicazione dei costi di manodopera secondo il § 35a
EStG: una modifica successiva della regola dell'organizzazione o del tipo di
cliente non cambia più una fattura emessa.
