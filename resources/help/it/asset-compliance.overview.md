---
title: "Strumenti di misura e taratura"
topic: asset-compliance.overview
version: 2
keywords:
    - metrologia
    - gestione strumenti
    - certificato di taratura
    - verifica periodica
    - scadenze verifiche
    - rapporto di prova
    - verifica elettrica
    - revisione veicoli
    - blocco attrezzatura
    - ISO 17025
audience: []
modules:
    - module.asset_compliance
related:
    - rental.overview
    - asset-finance.overview
---

Il modulo gestisce apparecchi soggetti a verifica: taratura, verifiche
periodiche, revisione veicoli, controlli elettrici, manutenzione del
produttore e controlli interni — con evidenze e blocchi d'uso.

**Profili di verifica (catalogo):** i modelli globali vengono
sovrascritti dai profili dell'organizzazione con lo stesso codice
(intervallo, preavviso, tolleranza, periodo di grazia, effetto
bloccante).

**Obblighi:** l'assegnazione di un profilo a un asset crea un obbligo
con scadenza e responsabile. Le verifiche in scadenza avvisano; dopo il
periodo di grazia il sistema blocca tramite il modello di blocco
condiviso — noleggio, pianificazione e utilizzo leggono lo stesso stato.

**Protocolli e certificati:** valori misurati contro limiti congelati,
esito, validità, firma e certificato di taratura opzionale. Le evidenze
sono immutabili — correzioni versionate.

**Deroghe** limitate nel tempo, motivate e verificate. **Ispettori
esterni** tramite accesso limitato. **Matrice normativa di riferimento**
senza promessa di conformità.

## Calendario dei controlli

Menu **Strumento di controllo** → **Calendario dei controlli**; la pagina è
raggiungibile anche come scheda dalle altre pagine degli strumenti di
controllo. L'elenco mostra gli appuntamenti di controllo aperti
(**Pianificato**, **Annunciato**, **In corso**) ordinati per scadenza. Con il
filtro di stato vede anche gli appuntamenti eseguiti, mancati o annullati.
Colonne: **In scadenza** (con la data pianificata, se presente), **Asset**,
**Profilo di controllo**, **Ispettore / organismo di controllo** e **Stato**.

**Pianifica appuntamento di controllo** (in fondo alla pagina): scelga
l'**Obbligo di controllo** – l'elenco mostra asset, profilo e prossima
scadenza –, **In scadenza il** (obbligatorio), facoltativamente **Pianificato
il**, **Ispettore interno** o **Ente di controllo esterno**, poi **Pianifica
appuntamento**. Un nuovo appuntamento parte come **Pianificato**. Per gli
appuntamenti con un ente esterno, **Invita accesso** invita l'ente tramite un
accesso a tempo limitato.

**Registra controllo** è disponibile per gli appuntamenti aperti e apre il
verbale di controllo:

- **Risultato**: **Superato**, **Superato con riserve** o **Non superato**.
- **Eseguito il (vuoto = adesso)** e **Valido fino al (vuoto = intervallo)**:
  senza indicazione l'attestazione vale per un intervallo di controllo
  dall'esecuzione. Un controllo non superato non riceve una data di validità.
- Un valore misurato per ogni requisito del profilo; accanto sono indicati i
  valori limite.
- **Decisione successiva**: **Nessuna / approvazione**, **Ritaratura
  (bloccante)**, **Riparazione (bloccante)**, **Utilizzo limitato** (viene solo
  annotato, non blocca), **Blocco**, **Dismissione** (blocca anch'essa) o **Apri
  reclamo** (crea un reclamo se la Sua organizzazione usa il modulo reclami e
  garanzia), più la **Motivazione della misura**.
- **Certificato / attestato di controllo** (espandibile): numero di
  certificato, emittente (obbligatorio non appena è inserito un numero), data
  di emissione, validità, campo di misura, tolleranza e un documento di cui
  viene salvato l'hash.
- **Firma (nome)**, **Costo del controllo (netto, €)** e **Osservazione**.

**Documenta la verifica** crea un'attestazione immodificabile e chiude
l'appuntamento come **Eseguito**. Un controllo superato porta la prossima
scadenza a un intervallo di controllo dopo l'esecuzione. Superato senza misura
successiva revoca i blocchi dovuti a controlli scaduti o non superati; «Non
superato» senza misura scelta blocca l'asset. Se il profilo richiede un
certificato, un controllo superato senza numero di certificato viene
rifiutato.

**Autorizzazione:** consultazione con **Elencare obblighi di verifica e
strumenti di misura**; pianificazione degli appuntamenti con **Gestire profili
e obblighi di verifica**; registrazione dei controlli con **Eseguire verifiche
e registrare le evidenze**. **Invita accesso** richiede uno degli ultimi due
diritti.

## Ordini di verifica

Menu **Strumento di controllo** → **Ordini di verifica**. Qui affida i
controlli in scadenza a un fornitore di verifica, che non ha bisogno di un
account utente. L'elenco mostra **Denominazione**, **Fornitore di verifica**,
il numero di **Strumenti**, **Stato** e **Prezzo offerto**; **Apri** porta
all'ordine.

Ecco come si svolge un ordine:

1. **Crea ordine di verifica**: scelga **Denominazione**, **Fornitore di
   verifica** (tra i Suoi fornitori), **E-mail del fornitore** e almeno un
   appuntamento di controllo con stato **Pianificato** o **Annunciato**, poi
   **Invia ordine**. Il fornitore riceve un'e-mail con un link valido 90
   giorni. Gli appuntamenti scelti passano ad **Annunciato**, l'ordine è
   **Richiesto**.
2. Tramite il link il fornitore presenta un'offerta con prezzo, data prevista
   e nota; l'ordine passa a **Offerta ricevuta**. Nella vista dell'ordine
   sceglie **Accetta offerta** (stato **Incaricato**) o **Rifiuta offerta**
   (di nuovo **Richiesto**; il fornitore può presentare una nuova offerta).
3. Dopo l'incarico il fornitore comunica per ogni strumento risultato, data
   del controllo, validità, numero di certificato e nota, facoltativamente con
   il certificato come file. L'ordine passa allora a **Risultati comunicati**.
4. **Acquisisci risultati** crea un'attestazione di controllo per ogni
   strumento comunicato – come un controllo nel calendario dei controlli, con
   il fornitore come ispettore ed emittente e con il certificato e la relativa
   somma di controllo. L'ordine è poi **Completato**; nella tabella gli
   strumenti riportano «acquisito».

**Annulla ordine** è possibile finché non sono stati comunicati risultati;
gli appuntamenti annunciati tornano **Pianificato**. Se un profilo richiede un
certificato e un risultato superato non ha un numero di certificato,
l'acquisizione si interrompe con un messaggio.

**Autorizzazione:** consultare l'elenco e l'ordine con **Elencare obblighi di
verifica e strumenti di misura**; creare, decidere sull'offerta e annullare
con **Gestire profili e obblighi di verifica**; acquisire i risultati con
**Eseguire verifiche e registrare le evidenze**.

## Giri di verifica

Scheda **Giri di verifica**, ad esempio nel **Calendario dei controlli**. Un
giro di verifica è un elenco previsto di controlli in scadenza di una sede o
di un gruppo, che Lei svolge sul posto tramite scansione. L'elenco mostra
**Denominazione**, **Scadenza entro**, **Eseguite** (controlli eseguiti sul
totale) e **Stato** (**Aperto** o **Chiuso**).

**Crea giro**: **Denominazione**, **Scadenza entro** (preimpostata su oggi
più 30 giorni) e facoltativamente **Sede**, **Gruppo (categoria)**,
**Profilo di verifica** e **Cliente**. Il giro riprende tutti gli obblighi di
controllo attivi in scadenza entro quella data; gli asset dismessi restano
esclusi. Gli obblighi che scadono più tardi non vengono aggiunti. Se per la
selezione non scade nulla, il giro non viene creato.

Nel giro vede gli indicatori **Eseguite**, **Mancanti** e **Scadute** e tutte
le posizioni con asset, profilo, scadenza e stato (**Aperta**, **Scaduta** o
il risultato registrato).

- **Scansioni l'oggetto**: inserisca o scansioni un codice QR, il n. impianto,
  il n. inventario o il numero di serie e scelga **Apri**. Sui dispositivi con
  supporto NFC compare anche **Leggi tag NFC**. Se per l'oggetto è aperto un
  solo controllo, si apre la registrazione; se sono più di uno, sceglie il
  profilo di verifica.
- **Registra verifica** (registrazione rapida): **Risultato**, **Nota** e
  **Firma (nome)** (preimpostata con il Suo nome), poi **Salva verifica**.
  Si crea la stessa attestazione immodificabile del calendario dei controlli,
  ma senza valori misurati né certificato. «Non superato» blocca l'asset. Se
  il profilo richiede un certificato, registri un controllo superato nel
  calendario dei controlli – la registrazione lo segnala.
- **Chiudi giro**: i controlli ancora aperti restano come mancanti; in seguito
  nel giro non è più possibile registrare.

**Autorizzazione:** consultazione con **Elencare obblighi di verifica e
strumenti di misura**; creare, scansionare, registrare e chiudere giri con
**Eseguire verifiche e registrare le evidenze**.

## Giro di ispezione

Nel **Calendario dei controlli** tramite il pulsante **Giro di ispezione**.
Così pianifica come giro gli appuntamenti di controllo aperti di un ispettore
interno.

- In alto sceglie l'**Ispettore** (preimpostato: Lei stesso) e **In scadenza
  entro** (preimpostato: oggi più 14 giorni).
- La tabella mostra gli appuntamenti aperti per cui questa persona è indicata
  come ispettore interno e che non sono ancora assegnati a un intervento, con
  scadenza, asset, profilo di controllo e **Ubicazione**. Tutti gli
  appuntamenti sono preselezionati.
- Con **Data del giro** (preimpostata: domani) e **Pianifica giro** ogni
  appuntamento scelto diventa un intervento dell'ispettore presso l'ubicazione
  dell'apparecchio. L'appuntamento riceve la data del giro come data
  pianificata, la pianificazione dei giri crea il giro e ottimizza l'ordine;
  poi si apre il giro.
- Gli apparecchi contrassegnati **senza coordinate** restano nel giro, ma non
  entrano nel calcolo del percorso.
- I giri di ispezione richiedono il modulo Pianificazione. Senza questo modulo
  la pagina mostra un'indicazione e nessun pulsante per pianificare.

**Autorizzazione:** **Gestire profili e obblighi di verifica**.

## Rapporto di audit

Menu **Strumento di controllo** → **Rapporto di audit** (titolo della pagina
**Rapporto di audit del sistema di controllo**). Il periodo si sceglie nella
barra dei filtri della pagina; l'impostazione predefinita sono gli ultimi tre
mesi.

- Stato attuale, indipendente dal periodo: **Obblighi di controllo** (obblighi
  attivi), **Scaduto** (scadenza più tolleranza superata), **In scadenza**
  (entro il preavviso del profilo) e **Bloccato (controlli)** (blocchi attivi
  dovuti a controlli scaduti o non superati).
- Riferiti al periodo: **Controlli nel periodo**, **Non superato**, **Tasso di
  controllo** (quota dei controlli superati, anche con riserve),
  **Certificati**, **Costi di controllo nel periodo** e i costi di al massimo
  tre tipi di controllo.
- Tabelle: **Obblighi di controllo per tipo di controllo**, **Controlli per
  ispettore (top 10)** e **Deviazioni (non superato)** con asset, momento e
  osservazione.

**Congela lo snapshot** salva in modo immodificabile gli indicatori del
periodo scelto. Gli ultimi dieci snapshot sono elencati in **Snapshot
congelati (P2)** con periodo, data di creazione, numero di obblighi scaduti e
tasso di controllo.

**Autorizzazione:** **Elencare obblighi di verifica e strumenti di misura** –
vale anche per congelare uno snapshot.
