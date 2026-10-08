---
title: "Registrare e posta contabile"
topic: accounting.posting
version: 2
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
  subito la registrazione speculare e annulla anche le partite aperte nate
  dall'originale.

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
per **Bozza creata** **Mostra registrazione** porta alla bozza), **Modelli**
(**Denominazione**, **Tipo**, **Ritmo**, **Prossima scadenza**,
**Responsabile**, **Stato** con numero di versione) e **Piani di
fatturazione**: piani di fatturazione attivi solo per panoramica, modificati da
**Apri i piani**.

**Creare modello:** **Tipo**, **Denominazione**, **Ritmo** (**Mensile**,
**Trimestrale**, **Semestrale**, **Annuale**), **Giorno di scadenza** (1–28,
così ogni mese ha quel giorno), **Atteso**, **Inizio** e facoltativamente
**Fine**, per i modelli di registrazione anche **Dare** e **Avere**, oltre a una
**Nota**. Un modello di registrazione senza entrambi i conti e l'importo non
viene salvato. In modifica la finestra mostra le prossime scadenze; ogni
modifica salva una nuova versione, e le operazioni già create restano
invariate.

**Svolgimento e regole:**

- Un'elaborazione giornaliera crea le operazioni scadute finché la contabilità
  locale tiene il libro mastro alla data di riferimento. Per modello e periodo
  nasce al massimo un'operazione.
- **Esegui ora** crea subito l'operazione per la prossima scadenza, senza
  attendere l'elaborazione giornaliera.
- Se una bozza non può essere creata, ad esempio perché per la data non esiste
  un periodo, l'operazione compare come **Bloccato** con il motivo.
- Se un'operazione con **Documento atteso** o **Bozza creata** è scaduta,
  WorkDiary lo segnala una sola volta tramite le notifiche.
- **Sospendere** ferma un modello; **Riprendere** continua con la prossima
  scadenza da oggi in poi, senza recuperare quelle perse. **Terminare** chiude
  il modello definitivamente; le operazioni già create restano.

**Autorizzazione:** consultare con **Consultare la contabilità**; creare,
modificare, sospendere, riprendere e terminare modelli con **Configurare la
contabilità**; **Esegui ora** con **Preparare le registrazioni**.
