---
title: "Panoramica della gestione della protezione dei dati"
topic: privacy.overview
version: 2
keywords:
    - GDPR
    - registro dei trattamenti
    - responsabile del trattamento
    - DPA
    - misure tecniche e organizzative
    - diritti degli interessati
    - richiesta di accesso
    - violazione dei dati
    - data breach
    - notifica entro 72 ore
    - conservazione dei dati
    - legal hold
audience: []
modules:
    - module.datenschutz
related:
    - documents.manage
    - isms.overview
    - glossary.core
    - privacy.portal
---

Il modulo privacy supporta il lavoro operativo di protezione dei dati:
registro dei trattamenti (art. 30 GDPR) con versioni immutabili dopo
l'approvazione, responsabili del trattamento e contratti (art. 28),
richieste degli interessati (artt. 15–21) con **termine di 30 giorni**,
misure tecnico-organizzative e violazioni dei dati con vista sull'obbligo
di notifica di 72 ore; la notifica all'autorità e la comunicazione agli
interessati (art. 34) vengono registrate separatamente. I contenuti delle richieste e le motivazioni sono
salvati **cifrati** con una chiave per caso; i diritti privacy vengono
assegnati esplicitamente, senza bypass per gli amministratori. Dopo il
periodo di conservazione la chiave del caso può essere distrutta
(crypto-shredding): i contenuti diventano **irrecuperabili**. Le prove
(contratti, certificati) si gestiscono nel modulo **Documenti**.

Il rapporto di accesso (art. 15/20) può essere creato anche per i **soci del
club** se la Sua organizzazione usa la gestione del club: dati anagrafici con
tutori legali, indirizzi e coordinate bancarie e una panoramica dei dati del
club (periodi di iscrizione, gruppi, presenze, quote, donazioni, gradi,
prestazioni e altro) con un estratto per area. La ricerca trova i soci per
nome, e-mail o numero di socio.

Tutte le pagine seguenti si trovano nella barra laterale sotto **Protezione
dei dati**. Per consultarle basta il diritto di lettura del modulo privacy
(eccezione: portale interessati); per le modifiche esiste un diritto proprio
per ogni area. Il ruolo **Protezione dei dati** dispone di tutti questi
diritti.

## Contitolarità

**Protezione dei dati** → **Registri** → **Contitolarità**. Il **Registro
degli accordi di contitolarità** raccoglie gli accordi di contitolarità ai
sensi dell'art. 26 GDPR. L'elenco mostra **Titolo**, **Partner**, **Stato** ed
**Elementi essenziali forniti**.

**Crea nuovo accordo di contitolarità**:

- **Partner (fornitore)** dal registro dei fornitori e **Titolo**
  (obbligatori), facoltativamente **Valido dal** e **Punto di contatto
  comune**.
- **Matrice delle responsabilità**: per **Obblighi di informazione (Art.
  13/14)**, **Diritti degli interessati**, **Violazioni dei dati** e
  **Contatto dell'autorità di controllo** stabilisce ogni volta chi è
  responsabile: **Noi**, **Partner** o **Congiunto** (predefinito).
- Casella **Elementi essenziali dell'accordo di contitolarità forniti agli
  interessati**.
- Facoltativamente il **Documento contrattuale** (PDF, DOC o DOCX, fino a
  20 MB).

Un nuovo accordo parte con lo stato **Bozza**. Nell'accordo modifica la
matrice, il **Punto di contatto**, lo **Stato** (**Bozza**, **Attivo**,
**Cessato**, **Scaduto**) ed **Elementi essenziali forniti**. In
**Trattamenti collegati** spunta i trattamenti interessati del registro e
salva con **Salva collegamenti**. Il documento contrattuale si scarica tramite
il link nei dati principali.

**Autorizzazione:** consultazione con il diritto di lettura; creazione e
modifica con il diritto per fornitori e contratti di trattamento dei dati.

## Catalogo TOM

**Protezione dei dati** → **Registri** → **Catalogo TOM**. Il catalogo
raccoglie in un unico punto le misure tecniche e organizzative (art. 32
GDPR). L'elenco mostra **Misura**, **Area**, **Stato** e **Revisione in
scadenza**; se la data di revisione è superata, è evidenziata in rosso.

**Nuova misura**: **Denominazione**, **Ambito delle misure** (ad esempio
**Controllo degli accessi fisici**, **Controllo dell'accesso ai sistemi**, **Controllo dell'accesso ai dati**, **Controllo
della trasmissione**, **Controllo dell'inserimento**, **Controllo della
disponibilità**, **Ripristinabilità**, **Controllo della separazione** o
**Gestione della protezione dei dati**), **Descrizione**, **Rischi
affrontati** e **Prove (politiche, verbali, certificati …)**. La misura viene
creata con la versione 1 come bozza.

Nella misura:

- **Versioni**: salva le modifiche tramite **Nuova versione** con
  descrizione, rischi affrontati e **Nota di modifica**; le versioni
  precedenti restano conservate. **Rilascia** rende una versione la
  **Versione valida**.
- **Trattamenti assegnati**: scelga un trattamento e **Assegna**. Quando un
  trattamento viene approvato, la sua versione congela anche lo stato delle
  misure assegnate.
- **Verifiche di efficacia**: **Documenta la verifica** con **Risultato**
  (**Efficace**, **Deviazione** o **Inefficace**), facoltativamente **Misura
  di follow-up in scadenza** e **Deviazione / azione di follow-up**. La
  prossima revisione viene fissata alla data della misura di follow-up,
  oppure a un anno dopo se manca la data; questa data compare nell'elenco
  sotto **Revisione in scadenza**.
- **Prove**: carichi i file con **Carica attestazione**, facoltativamente con
  **Valido fino al (facoltativo)**. Le prove scadute sono contrassegnate;
  l'analisi delle lacune segnala le prove in scadenza o già scadute.

**Autorizzazione:** consultazione con il diritto di lettura; creare,
versionare, rilasciare, assegnare, verificare e caricare prove con il diritto
per il catalogo TOM.

## Analisi delle lacune

**Protezione dei dati** → **Incidenti e verifica** → **Analisi delle
lacune**. L'analisi verifica, in base a regole, se mancano o stanno per
scadere contratti, valutazioni e prove:

- responsabili del trattamento senza contratto di trattamento dei dati,
- contratti di trattamento in scadenza o già scaduti (per impostazione
  predefinita con 30 giorni di anticipo),
- contitolari senza accordo di contitolarità,
- trattamenti che richiedono una DPIA senza DPIA conclusa,
- trattamenti senza TOM assegnate,
- prove TOM in scadenza o già scadute.

**Esegui analisi ora** avvia un'esecuzione. Inoltre l'analisi viene eseguita
automaticamente una volta al giorno, non appena l'analisi delle lacune è
stata aperta una prima volta nella Sua organizzazione. In alto un semaforo
mostra il numero di rilievi per stato; sotto compaiono i rilievi – prima
quelli aperti – con **Requisito**, **Stato**, **Trigger** e **Riferimento**
(link al trattamento, al contratto o al fornitore).

L'analisi imposta **Mancante** o **Scade**. In **Decisione** imposta a mano
**Presente**, **In esame**, **Non applicabile**, **Deviazione accettata** o
**Riaperto**, ogni volta con **Motivazione** e **OK**. Per «Non applicabile» e
«Deviazione accettata» la motivazione è obbligatoria. Le esecuzioni
successive non modificano più un rilievo deciso a mano. Le lacune impostate
dall'analisi stessa che non si presentano più passano a **Presente**
all'esecuzione successiva.

Nel **Catalogo dei requisiti** in fondo alla pagina stabilisce quali
verifiche vengono eseguite: ogni requisito può essere rinominato e
disattivato con l'interruttore; i requisiti disattivati vengono saltati. Le
voci provenienti da un profilo settoriale riportano l'indicazione **Profilo
settoriale**.

**Autorizzazione:** consultazione con il diritto di lettura; avviare
l'analisi, decidere e gestire il catalogo con il diritto per l'analisi delle
lacune.

## Conservazione ed eliminazione

**Protezione dei dati** → **Incidenti e verifica** → **Conservazione ed
eliminazione**. Il concetto di cancellazione propone l'eliminazione dei dati
il cui periodo di conservazione è scaduto; nulla viene eliminato o
anonimizzato senza una conferma in due passaggi. In alto è indicato
l'ordinamento giuridico della Sua organizzazione (ad esempio DE), da cui
dipendono i termini.

- **Termini per area**: per ogni area di dati – ad esempio il registro di
  audit, le candidature o la piattaforma di apprendimento – la **Scadenza** in
  anni o giorni e la **Base giuridica**. Le aree con l'indicazione «solo
  censimento, senza scansione» documentano solo il termine; per esse non
  nascono proposte.
- **Scansiona ora** cerca i record con termine scaduto e crea **Proposte di
  eliminazione**. La scansione viene eseguita anche automaticamente a
  intervalli regolari (per impostazione predefinita ogni settimana). I record
  sotto blocco legale e le eccezioni di merito non ricevono proposte.
- Ogni proposta mostra **Area**, **Record**, **Termine scaduto da**,
  **Motivazione** e **Stato**.

L'eliminazione avviene in due passaggi: prima **Conferma** (stato
**confermato**) o **Rifiuta** (**rifiutata**), poi, per le proposte
confermate, **Elimina definitivamente** – singolarmente o per area con
**Elimina i confermati in …**. A seconda dell'area il record viene eliminato o
anonimizzato. Se nel frattempo è stato impostato un blocco legale, conferma ed
eliminazione vengono respinte; nell'eliminazione cumulativa questi record
vengono saltati e conteggiati nel messaggio. Ogni decisione viene registrata.

**Autorizzazione:** consultazione con il diritto di lettura; scansionare e
decidere con il diritto per l'analisi delle lacune.

## Blocco legale

**Protezione dei dati** → **Incidenti e verifica** → **Blocco legale**. Un
blocco legale è un vincolo per procedimenti in corso di un interessato o
legali: finché è attivo, nulla viene eliminato o anonimizzato riguardo alla
persona o al cliente.

L'elenco mostra prima i blocchi attivi, con **Interessato** (nome, persona o
cliente), **Numero di pratica**, **Motivo** (leggibile solo da chi ha il
diritto di decisione), **Impostato** (data e autore) e **Stato** (**attivo** o
«revocato il …»).

**Imposta blocco legale**: in **Tipo** scelga **Persona** o **Cliente** e poi
esattamente una persona dell'organizzazione o un cliente; facoltativamente un
**Numero di pratica**. Il **Motivo** è obbligatorio (almeno 10 caratteri) e
viene salvato cifrato.

Finché il blocco è attivo non nascono proposte di eliminazione; le
eliminazioni confermate, l'anonimizzazione e l'eliminazione di account o
clienti vengono respinte, e i punti di posizione grezzi della persona restano
conservati. In una fusione di clienti il blocco passa al cliente di
destinazione.

**Revoca blocco legale** richiede un **Motivo della revoca** (almeno 10
caratteri). In seguito tornano ad applicarsi il concetto di cancellazione e
le pulizie; il motivo resta come prova. Impostazione e revoca compaiono nel
registro della persona o del cliente.

**Autorizzazione:** consultazione con il diritto di lettura; impostare e
revocare con il diritto per l'analisi delle lacune – chi decide sulle
eliminazioni le blocca anche.

## Portale interessati

**Protezione dei dati** → **Incidenti e verifica** → **Portale interessati**
(titolo della pagina **Gestire il portale interessati**). Qui configura il
modulo pubblico con cui gli interessati presentano le loro richieste; il tema
sul portale di accesso descrive come si svolge per la persona.

- Finché non esiste un portale, la pagina mostra un'indicazione; il primo
  **Salva** crea il portale con un link casuale.
- **Link pubblico**: pubblichi questo link nella Sua informativa sulla
  privacy; non è deducibile dal nome dell'organizzazione. Accanto vede se il
  portale è **attivo** o **inattivo**. **Ruota il link** crea un nuovo link
  dopo una richiesta di conferma – i link già pubblicati diventano non validi.
- **Impostazioni**: **Portale attivo (raggiungibile pubblicamente)** –
  inizialmente disattivato –, **Consenti allegati**, **Testo introduttivo
  (facoltativo)** e **Lingua predefinita (facoltativa, ad es. it)**.

Le richieste in arrivo compaiono come pratica in **Richieste degli
interessati**. Lì sono contrassegnate come ingresso dal portale; i dati
sull'identità valgono come autodichiarazione non verificata.

**Autorizzazione:** un diritto proprio per gestire il portale interessati;
senza questo diritto la voce di menu non compare.
