---
title: "Registrare e posta contabile"
topic: accounting.posting
version: 10
keywords:
    - registrazione contabile
    - registrare documenti
    - prima nota
    - imputazione conti
    - proposta di registrazione
    - regole contabili
    - storno
    - stornare registrazione
    - principio dei quattro occhi
    - approvazione
    - valuta estera
    - tasso di cambio
    - libro giornale
    - partite aperte
    - registrazione ricorrente
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.finance
related:
    - accounting.overview
    - accounting.closing
---

La **posta contabile** è il punto di ingresso: mostra documenti, spese,
movimenti di cassa e pagamenti del periodo con il loro stato. Gli elementi
bloccati stanno in cima.

**Proposta prima della registrazione.** Anche una proposta univoca diventa solo
una bozza verificata. Con il principio dei quattro occhi chi prepara non
registra da solo.

**Principio dei quattro occhi per le registrazioni dirette.** Alcune operazioni
creano da sé la propria registrazione: **Sconto** e **Stralcio** tramite
**Compensare** nelle partite aperte, **Registrare su conto transitorio** nella
riconciliazione bancaria, il **Giroconto interno**, **Importare i saldi
iniziali**, **Registra l'acconto speciale** e **Stornare** nel giornale. Senza
principio dei quattro occhi vengono registrate subito. Se è attivo, nasce una
bozza verificata: un messaggio e un'avvertenza nella finestra di dialogo lo
indicano, e la posta contabile la elenca con il suo tipo (**Sconto/stralcio**,
**Scrittura in sospeso**, **Giroconto interno**, **Saldi iniziali**, **Acconto
speciale**, **Storno**) e lo stato **Pronto** – indipendentemente dal periodo
nell'intestazione, finché è scelto **Tutte le origini**. L'operazione diventa
efficace solo quando una seconda persona la registra con **Registrare** o
**Accettare e registrare tutto**: solo allora la partita aperta è compensata,
il movimento bancario registrato e l'acconto speciale computato nell'ultima
liquidazione dell'anno. Nel giornale **Registrare subito** resta bloccato per
la persona che crea la registrazione.

**Scartare la bozza.** Qualsiasi bozza – nata da una di queste operazioni, da
una proposta della posta contabile, da un modello di registrazione o inserita a
mano – si elimina con **Scartare la bozza** nella posta contabile o nella sua
pagina di dettaglio (con conferma). Le bozze delle operazioni citate le scarta
chi può **Registrare definitivamente**; per tutte le altre basta **Preparare
registrazioni**. Il passaggio viene registrato nel protocollo e l'operazione
torna aperta: la partita si può di nuovo compensare, il movimento bancario, i
saldi iniziali e l'acconto speciale si possono registrare di nuovo, la
registrazione di uno storno scartato si può stornare di nuovo, un giroconto interno viene eliminato
insieme al collegamento dei suoi documenti, un movimento bancario messo da parte
con **Registra su un conto** torna aperto e il
documento di una proposta ricompare non registrato nella posta. L'operazione di
un modello di registrazione risulta **Saltata**; il periodo successivo procede
normalmente. Le registrazioni definitive non si possono scartare.

**Modificare la bozza.** Finché una registrazione non è definitiva, la si
modifica nella sua pagina di dettaglio con **Modifica bozza**: data di
registrazione e del documento, testo, documento, controparte e le righe con
conto, importo, codice imposta, centro di costo e testo di riga. Vale per le
registrazioni manuali, le bozze dei modelli e le bozze delle proposte. Chi
modifica diventa chi ha preparato – con il principio dei quattro occhi registra
definitivamente un'altra persona. La modifica viene registrata nel protocollo.
Le bozze nate da sconto, storno crediti, conto transitorio, giroconto,
movimento bancario senza documento, saldi di apertura, acconto speciale e
storno seguono la loro operazione e non si possono riscrivere; su ogni bozza,
però, **Centri di costo** permette di completare il centro di costo per riga –
ad esempio quando un conto con **Centro di costo obbligatorio** impedisce la
registrazione definitiva. Le registrazioni definitive restano invariate.

**Bloccato invece che indovinato.** Se manca una regola, la proposta indica
ruolo e criteri. Un conto predefinito indovinato emergerebbe solo nelle analisi.

**Correzione solo con contro-registrazione.** Una registrazione definitiva è
immutabile; lo storno crea una contro-registrazione con motivazione
obbligatoria.

**Documenti in valuta estera.** Fatture attive e passive e note spese in valuta
estera vengono convertite al cambio mensile del loro mese (§ 16 c. 6 UStG).
Gestisca i cambi in «Tassi di cambio» (accanto alle regole di contabilizzazione),
singolarmente o riga per riga, ad es. dalla pubblicazione del Ministero delle
Finanze. Senza cambio il documento resta nella posta con un'indicazione. Cambio
e importo originale figurano nella prova di registrazione. Pagamenti, cassa e
cespiti in valuta estera continuano a non essere registrati; le differenze di
cambio al saldo si registrano a mano.

**Fatture in entrata per aliquota.** Se una fattura in entrata riporta più
aliquote – nel riepilogo IVA della fattura elettronica o come righe nella
finestra **Inserire i valori** –, la proposta registra costo e IVA a credito per
aliquota, ciascuna con il codice IVA a credito di quell’aliquota. Così il rigo
66 e lo sconto per aliquota restano corretti. Se il riepilogo non dà il lordo,
resta una sola riga dai valori di testata.

**Inversione contabile (debitore il destinatario).** Se le fatture in entrata
indicano come **caso fiscale** il § 13b UStG o un acquisto intracomunitario, la
fattura è al netto. La proposta registra il costo contro il debito e in più
l’IVA a debito autoliquidata in avere e la stessa IVA a credito in dare – con
il codice di quel caso fiscale: righi 46/47 per i servizi da un altro paese UE,
84/85 per gli altri casi del § 13b, 89 o 93 per l’acquisto, l’IVA a credito in
67 o 61. L’aliquota è quella del documento, allo 0 % quella ordinaria. Se il
documento riporta comunque l’imposta o manca il codice, resta bloccato con un
avviso. Uno sconto su una tale fattura è al netto: riduce la base imponibile,
IVA a debito e IVA a credito diminuiscono dello stesso importo (§ 17, comma 1,
frase 5 UStG); se il conto sconti è un conto automatico DATEV, lo sconto riduce
il conto di costo del documento. Le fatture in entrata registrate il cui
documento indica l’inversione contabile ma la cui registrazione non riporta
imposta compaiono nel rapporto **Qualità contabile**; nulla viene
registrato automaticamente.

**Fatture in uscita secondo il § 13b.** Se un cliente nazionale è registrato
come destinatario secondo il § 13b (p. es. lavori edili a imprese edili), la
fattura parte senza IVA e con la dicitura «Inversione contabile». La
registrazione porta il codice del rigo 60, con il modello di piano dei conti
sul conto ricavi 8337 (SKR03) o 4337 (SKR04); le cessioni intracomunitarie
vanno su 8125 o 4125.

## Giornale

Il **Libro giornale** si apre da **Vendite e fatturazione** → **Contabilità** →
**Giornale**. Mostra tutte le registrazioni preparate e definitive del periodo
scelto nell'intestazione. Le voci **Giornale**, **Partite aperte** e
**Ricorrenti** compaiono non appena la Sua organizzazione tiene o ha tenuto la
contabilità locale; se attualmente un altro sistema tiene il libro mastro, un
avviso sopra l'elenco lo segnala.

- **Elenco:** **N.**, **Data di registrazione**, **Descrizione**, **Conti**,
  **Importo** e **Stato** (**Bozza**, **Verificata**, **Registrata**,
  **Stornata**). La ricerca trova descrizione e documento, il filtro di stato
  mostra un solo stato. WorkDiary assegna il numero di giornale solo alla
  registrazione definitiva, in modo progressivo e senza lacune.
- **Inserire una registrazione:** la finestra registra un numero qualsiasi di
  righe. Sono obbligatori la **Data di registrazione**, il **Testo** e almeno
  due righe con **Conto** (numero del conto, con elenco di suggerimenti) e un
  importo in **Dare** o in **Avere**; **Data documento**, **Documento**,
  **Controparte**, **Codice imposta**, **Centro di costo** e **Testo di riga**
  sono facoltativi. Le righe vuote vengono ignorate. Con il **Codice imposta**
  si inserisce l'importo netto; la riga d'imposta sul conto d'imposta del
  codice viene creata al salvataggio e la registrazione compare nei righi
  della dichiarazione. La **Controparte** (cliente o fornitore) vale per le
  righe su conti con partite aperte e per le righe con codice imposta – così
  la partita aperta riporta il cliente. Si possono scegliere solo conti attivi
  e codici imposta validi alla data di registrazione. Senza **Registrare
  subito** nasce una bozza.
- **Visualizzare una registrazione:** la pagina di dettaglio mostra
  **Testata** e **Righe** con i totali Dare e Avere, oltre ad avvisi su uno
  storno e su budget mensili superati (senza blocco). Bozze e registrazioni
  verificate si registrano qui con **Registrare**.
- **Stornare:** una registrazione definitiva si corregge con **Stornare**: la
  **Motivazione** è obbligatoria, la **Data della contro-registrazione**
  facoltativa. Se resta vuota vale il giorno originale finché il suo periodo è
  aperto, altrimenti la data odierna. **Creare contro-registrazione** registra
  la registrazione speculare e annulla anche le partite aperte nate
  dall'originale. Con il principio dei quattro occhi attivo la
  contro-registrazione nasce come bozza: la registrazione resta definitiva e
  le partite aperte invariate finché una seconda persona non registra lo
  storno. Fino ad allora non è possibile un secondo storno della stessa
  registrazione; la sua pagina di dettaglio rimanda alla bozza con **Mostra la
  bozza in attesa**. Lo storno automatico quando si annulla un'assegnazione
  nella riconciliazione bancaria viene sempre registrato subito.

Alla registrazione definitiva WorkDiary verifica che: per la data di
registrazione esista un periodo aperto e la contabilità locale tenga il libro
mastro in quel giorno; Dare e Avere coincidano; tutti i conti siano attivi e un
conto con **Centro di costo obbligatorio** abbia un centro di costo. Con il
principio dei quattro occhi attivo, chi ha creato la registrazione non può
renderla definitiva – nemmeno con **Registrare subito**.

**Autorizzazione:** consultare con **Consultare la contabilità**, inserire con
**Preparare le registrazioni**, registrare e stornare con **Registrare
definitivamente**.

## Partite aperte

**Vendite e fatturazione** → **Contabilità** → **Partite aperte** mostra
crediti e debiti da registrazioni definitive non ancora compensati – a
prescindere dal periodo nell'intestazione. Una partita aperta nasce quando una
registrazione viene resa definitiva su un conto con la caratteristica
**Partite aperte**; i pagamenti la compensano tramite la riconciliazione
bancaria.

- Le schede **Credito** e **Debito** separano le due direzioni.
- I riquadri sommano gli importi aperti per anzianità dalla scadenza: **Non
  scaduto**, **1–30 giorni**, **31–60 giorni**, **61–90 giorni** e **oltre 90
  giorni**.
- L'elenco, ordinato per scadenza, mostra **Documento**, **Controparte**,
  **Data documento**, **Scadenza** (con l'indicazione «scaduto da … giorni»),
  **Originale**, **Aperto** e **Stato** (**Aperto**, **Parzialmente
  compensato**, **Contestato**). **Mostra registrazione** apre la
  registrazione di origine.
- **Compensare** registra una detrazione senza pagamento: **Sconto**,
  **Ritenuta** o **Storno**, con **Importo** e **Nota** facoltativa. L'importo
  non può superare il residuo aperto. Per sconto e storno WorkDiary registra
  contestualmente una contropartita nel giornale – sul **conto sconti** o sul
  **conto di storno** delle impostazioni DATEV (vuoto = valore predefinito del piano dei conti: 8736 e 2400 in SKR03, 4736 e
  6930 in SKR04). Un debito usa conti propri: sconti ottenuti (3736 o 5736) e,
  per lo storno, altri ricavi (2709 o 4839).
  Lo sconto riduce anche l'imposta (§ 17 UStG): WorkDiary ripartisce l'importo
  secondo l'aliquota del documento in netto (conto sconti, con codice imposta)
  e quota d'imposta (conto IVA a debito o a credito) – in proporzione se le
  aliquote sono più d'una, ciascuna sul conto sconti della propria aliquota
  finché non è impostato un conto proprio. I righi della dichiarazione e
  l'anteprima IVA mostrano la riduzione. Se la registrazione della partita non
  ha imposta (piccolo imprenditore, esente, saldo di
  apertura), lo sconto resta indiviso; la finestra indica che cosa vale. Lo
  stesso vale per lo sconto dalla riconciliazione dei pagamenti. Gli sconti
  registrati in passato senza rettifica d'imposta sono indicati nel report
  **Qualità delle registrazioni** con la quota d'imposta calcolata; nulla
  viene registrato automaticamente. Se questo
  conto manca nel piano dei conti, il pareggio viene rifiutato con un
  messaggio e la partita resta aperta; la finestra avvisa già prima. Una
  ritenuta non crea alcuna registrazione. Con il principio dei quattro occhi
  la contropartita nasce come bozza nella posta contabile e la partita resta
  aperta finché una seconda persona non la registra definitivamente. Fino ad
  allora l'elenco mostra **Bozza in attesa di approvazione**, **Mostra bozza
  in attesa** porta alla registrazione e ogni ulteriore compensazione della
  partita – anche una ritenuta – viene rifiutata. Se la partita viene
  compensata diversamente prima dell'approvazione, la registrazione definitiva
  fallisce perché l'importo supera il residuo aperto. I pareggi per sconto o
  storno precedenti, chiusi senza registrazione, sono indicati nel report
  **Qualità delle registrazioni**; vanno registrati manualmente.
- **Contrassegna come contestata** segnala una partita aperta come
  **Contestata**, con motivo obbligatorio. Resta aperta e compensabile, anche
  dopo un pareggio parziale. Sulla fattura emessa WorkDiary imposta un blocco
  dei solleciti se non ce n'è già uno – la partita non viene sollecitata né
  singolarmente né nel ciclo dei solleciti – e, nel riepilogo e nella
  previsione di liquidità, un credito contestato non conta come incasso atteso. La fattura ricevuta di
  un debito contestato resta bloccata nella proposta di pagamento (**Partita
  aperta contestata**) finché il contrassegno non viene ritirato. **Ritira la contestazione** rimuove il contrassegno e il blocco dei
  solleciti che ha creato; un blocco impostato a mano resta. Il filtro sopra
  l'elenco mostra **Aperta**, **Parzialmente compensata** o **Contestata**.
  Entrambi i passaggi risultano nel registro delle modifiche.

**Autorizzazione:** consultare con **Consultare la contabilità**, compensare con
**Registrare definitivamente**.

## Ricorrenti

Da **Vendite e fatturazione** → **Contabilità** → **Ricorrenti** (pagina
**Operazioni ricorrenti**) Lei pianifica ciò che si ripete regolarmente.
Esistono due tipi di modello:

- **Attesa di documento:** per un documento che deve arrivare regolarmente, ad
  esempio affitto o leasing. Non crea né documento né registrazione, ma alla
  scadenza un'operazione aperta con stato **Documento atteso** – così resta
  visibile che l'originale manca ancora.
- **Modello di registrazione:** alla scadenza crea una bozza di registrazione
  con le righe del modello, datata al giorno di scadenza.
  Non registra mai da solo; lo fa Lei a mano nella posta contabile o nel
  giornale.

La pagina si divide in **Operazioni aperte** (**Modello**, **Periodo**,
**Scadenza**, **Atteso**, **Stato**; per **Bloccato** il motivo compare sotto,
per **Bozza creata** **Mostra registrazione** porta alla bozza, per
**Documento atteso** **Assegnare il documento** assegna l'originale), **Modelli**
(**Denominazione**, **Tipo**, **Ritmo**, **Prossima scadenza**,
**Responsabile**, **Stato** con numero di versione) e **Piani di
fatturazione**: piani di fatturazione attivi solo per panoramica, modificati da
**Apri i piani**.

**Creare modello:** **Tipo**, **Denominazione**, **Ritmo** (**Mensile**,
**Trimestrale**, **Semestrale**, **Annuale**), **Giorno di scadenza** (1–28,
così ogni mese ha quel giorno), **Atteso**, **Inizio** e facoltativamente
**Fine**, per i modelli di registrazione anche le righe di registrazione, oltre a
**Responsabile** e una **Nota**. Un modello di registrazione senza almeno due
righe con conto e importo non viene salvato. In modifica le righe salvate sono
precompilate e la finestra mostra le prossime scadenze; ogni modifica salva
una nuova versione, e le operazioni già create restano invariate.

**Svolgimento e regole:**

- Un'elaborazione giornaliera crea le operazioni scadute finché la contabilità
  locale tiene il libro mastro alla data di riferimento. Per modello e periodo
  nasce al massimo un'operazione.
- **Esegui ora** crea subito l'operazione per la prossima scadenza, senza
  attendere l'elaborazione giornaliera.
- Se una bozza non può essere creata, ad esempio perché per la data non esiste
  un periodo, l'operazione compare come **Bloccato** con il motivo.
- **Assegnare il documento** soddisfa un'attesa di documento: Lei sceglie la
  fattura ricevuta nel campo **Fattura elettronica in entrata**. Sono proposte
  le fatture di **Fatture elettroniche in entrata** che Lei può vedere, non
  rifiutate e non ancora assegnate a un'operazione – una fattura soddisfa al
  massimo un'operazione. L'operazione passa poi allo stato **Soddisfatto**.
- Quando la bozza di un modello di registrazione viene registrata, anche la
  sua operazione passa allo stato **Soddisfatto**.
- Se un'operazione con **Documento atteso** o **Bozza creata** è scaduta,
  WorkDiary lo segnala una sola volta tramite le notifiche, di fabbrica alla
  contabilità e alla persona indicata in **Responsabile**.
- **Sospendere** ferma un modello; **Riprendere** continua con la prossima
  scadenza da oggi in poi, senza recuperare quelle perse. **Terminare** chiude
  il modello definitivamente; le operazioni già create restano.

**Righe del modello.** Un modello di registrazione accetta le stesse righe
della registrazione manuale: più righe, **Codice imposta**, **Centro di costo**
e **Controparte**. Dare e avere devono quadrare, altrimenti il modello non
viene salvato. Se **Atteso** resta vuoto, vale il totale della registrazione.
Se cambia l'aliquota di un codice imposta, salvi di nuovo il modello. Se scarta
la bozza di un modello, la sua operazione risulta **Saltata**.

**Autorizzazione:** consultare con **Consultare la contabilità**; creare,
modificare, sospendere, riprendere e terminare modelli con **Configurare la
contabilità**; **Esegui ora** e **Assegnare il documento** con **Preparare le
registrazioni**.

## Vendite senza imposta e spese

I modelli del piano dei conti hanno conti ricavi propri per i codici RC21 (rigo
21), AUSF (rigo 43) e NSTB (rigo 45); i piani creati dal modello li ricevono con
l'aggiornamento. Le **spese** registrano l'IVA a credito all'aliquota del
giustificativo. Se per quell'aliquota non esiste un codice IVA a credito — di solito
IVA estera, non detraibile —, la proposta si ferma: registri la spesa al lordo senza
aliquota. Le fatture con inversione contabile vanno nelle fatture in entrata, che
conoscono il regime fiscale.
