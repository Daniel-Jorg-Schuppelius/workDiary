---
title: "Designer di procedure"
topic: procedures.designer
version: 4
keywords:
    - istruzione operativa
    - creare checklist
    - SOP
    - procedura operativa standard
    - modello di processo
    - workflow
    - passaggi obbligatori
    - principio dei quattro occhi
    - passaggio condizionale
    - pubblicare versione
audience: []
related:
    - procedures.run
---

Nel **Designer di procedure** Lei crea procedure vincolanti (istruzioni
di lavoro, checklist) che vengono poi eseguite sulle commesse. I modelli
si trovano in **Sistema** → **Regole e processi** → **Modelli di
procedura**; **Modifica** apre il designer di un modello.

## Modello e versioni

- Un **Modello** (**Nuovo modello**) ha un **Codice** univoco, un
  **Nome**, un **Ambito** facoltativo (ad es. `it`, `hvac`) e una
  **Descrizione**; nel designer si aggiunge il **Livello di rischio**.
- I passi appartengono sempre a una **Versione**. Finché una versione è
  una **Bozza**, può modificare liberamente i passi e salvarli con
  **Salva**; una **Nota di modifica** registra che cosa è cambiato.
- Con **Pubblica** la versione diventa valida e **immutabile**. Le
  correzioni richiedono una **Nuova versione** – le commesse in corso o
  passate mantengono la versione di allora.

## Passi

Con **Aggiungi passo** oppure **Inserisci dalla libreria** (dalla
**Libreria dei passaggi**) si aggiungono passi. Ogni passo ha un
**Codice**, un’**Etichetta**, una **Descrizione** facoltativa e un
**Tipo**, ad esempio «Conferma», «Testo», «Numero/misurazione»,
«Scelta», «Foto», «File», «Registrazione di backup», «Firma»,
«Inserimento materiale», «Serie di misurazioni», «Approvazione (doppio
controllo)» o «Attesa». Inoltre sono configurabili:

- **Obbligatorio**: deve avere uno stato finale prima della conclusione
  dell’esecuzione.
- **Bloccante**: blocca i passi successivi finché questo non è
  completato.
- **Quattro occhi**: richiede la controfirma di una seconda persona.
- **Prova** («Backup», «File», «Foto», «Misurazione», «Firma» o
  «Nessuno») e, facoltativamente, **Ruolo richiesto** e **Qualifica**.
- **Condizione: passo** e **Condizione: valore** (se-allora): il passo
  diventa rilevante solo se un altro passo ha un determinato valore.

## Configurazione per tipo di passaggio

A seconda del **Tipo**, un passo mostra altri campi:

- «Scelta»: **Opzioni di scelta**, separate da virgole. Senza opzioni il
  passo accetta testo libero durante l'esecuzione.
- «Numero/misurazione» e «Serie di misurazioni»: **Unità**, **Limite inferiore** e
  **Limite superiore** (tolleranza).
- «Attesa»: **Tempo di attesa fisso (secondi)**; se vuoto, la
  durata viene inserita durante l'esecuzione.
- «Registrazione di backup»: **Ambito**, **Archiviazione**, **Metodo di
  verifica**, **Dimensione minima (KB)** ed **Età massima del backup
  (minuti)**. «Scelto durante l'esecuzione» lascia l'indicazione alla
  persona esecutrice.
- Ogni altro passo: **Backup preliminare necessario** lo blocca finché un
  passo di backup della stessa esecuzione non è completato e la sua prova
  confermata, se desiderato con un'età massima.
- Passi a quattro occhi: **Ruolo della seconda persona**, **Qualifica
  della seconda persona** e **La persona esecutrice può controfirmare da
  sé** (di norma disattivato).

**Qualifica** indica la sigla di una qualifica dei dipendenti o, in
mancanza, il suo nome; si verifica se la persona la possiede oggi in corso
di validità. La **Libreria dei passaggi** offre gli stessi campi in
**Configurazione per tipo di passaggio**. Le indicazioni provenienti da
profili di settore o importazioni che il designer non mostra restano
conservate al salvataggio.

Il livello di rischio «Critica» richiede almeno un passo a quattro occhi:
senza di esso la versione non può essere pubblicata, e un'esecuzione si
conclude solo con un'approvazione a quattro occhi completata.

## Assegnazione automatica

Un modello viene proposto quando **tutti** i criteri impostati
corrispondono: **Tipi di commessa**, **Tag**, **Clienti**, **Categorie di
asset**, **Prodotti** e **Contratti SLA**. Categoria, prodotto e SLA si
riferiscono all'asset della commessa. Senza criteri il modello vale per
ogni commessa.

- Nella pagina di dettaglio della commessa i modelli pubblicati
  corrispondenti compaiono nella scheda **Procedure** sotto «Procedure
  suggerite per questa commessa:» come pulsante di avvio.
- Se il modello indica una categoria di asset, un prodotto o un contratto
  SLA, viene proposto anche nella scheda dell'asset sotto «Procedure
  previste per questo asset:».
- **Può essere eseguita su asset bloccati** consente l'avvio su un asset
  bloccato, ad esempio per le procedure di riparazione e verifica.
