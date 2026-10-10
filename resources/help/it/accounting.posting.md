---
title: "Registrare e posta contabile"
topic: accounting.posting
version: 5
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

**Scartare la bozza.** Una bozza in attesa nata da queste operazioni si
elimina con **Scartare la bozza** nella posta contabile o nella sua pagina di
dettaglio (autorizzazione **Registrare definitivamente**, con conferma). Il
passaggio viene registrato nel protocollo e l'operazione torna aperta: la
partita si può di nuovo compensare, il movimento bancario, i saldi iniziali e
l'acconto speciale si possono registrare di nuovo, la registrazione di uno
storno scartato si può stornare di nuovo e un giroconto interno viene
eliminato insieme al collegamento dei suoi documenti. Le registrazioni
definitive e le bozze nate da proposte non si possono scartare.

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
- **Nuova registrazione:** la finestra registra un importo da un conto in
  **Dare** a un conto in **Avere**. Sono obbligatori **Data di registrazione**,
  **Descrizione**, i due conti e **Importo**; **Data documento**, **Documento**
  e – se sono gestiti centri di costo – un **Centro di costo** per entrambe le
  righe sono facoltativi. Sono selezionabili solo conti attivi. Senza
  **Registrare subito** nasce una bozza.
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
  **Ritenuta** o **Stralcio**, con **Importo** e una **Nota** facoltativa.
  L'importo non può superare il residuo aperto. Per sconto e stralcio
  WorkDiary registra contemporaneamente una contro-registrazione definitiva nel
  giornale – sul conto sconti o stralci delle impostazioni DATEV, purché quel
  conto esista nel piano dei conti. Una ritenuta non crea alcuna
  registrazione.
  Con il principio dei quattro occhi attivo, la contro-registrazione nasce
  come bozza nella posta contabile e la partita resta aperta finché una
  seconda persona non la registra. Fino ad allora l'elenco mostra **Bozza in
  attesa di approvazione**, **Mostra la bozza in attesa** porta alla
  registrazione e ogni ulteriore compensazione della partita – anche una
  ritenuta – viene respinta. Se nel frattempo la partita è stata compensata in
  altro modo, la registrazione non riesce perché l'importo supera il residuo
  aperto. Ritenuta e compensazione senza conto di contropartita esistente non
  creano registrazioni e valgono subito.

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
  con conto Dare, conto Avere e importo atteso, datata al giorno di scadenza.
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
**Fine**, per i modelli di registrazione anche **Dare** e **Avere**, oltre a
**Responsabile** e una **Nota**. Un modello di registrazione senza entrambi i
conti e l'importo non viene salvato. In modifica i conti salvati sono
preimpostati e la finestra mostra le prossime scadenze; ogni modifica salva
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

**Autorizzazione:** consultare con **Consultare la contabilità**; creare,
modificare, sospendere, riprendere e terminare modelli con **Configurare la
contabilità**; **Esegui ora** e **Assegnare il documento** con **Preparare le
registrazioni**.
