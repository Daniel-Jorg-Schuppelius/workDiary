---
title: "Designer di procedure"
topic: procedures.designer
version: 3
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

## Assegnazione automatica

Tramite **Tipi di commessa** e **Tag** Lei stabilisce per quali commesse
il modello viene proposto automaticamente. Nella pagina di dettaglio
della commessa i modelli pubblicati corrispondenti compaiono nella scheda
**Procedure** sotto «Procedure suggerite per questa commessa:» come
pulsante di avvio.
