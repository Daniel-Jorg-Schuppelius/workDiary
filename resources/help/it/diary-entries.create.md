---
title: "Creare una commessa"
topic: diary-entries.create
version: 2
audience: []
schema: process
related:
    - protocols.create
    - time-entries.start
    - projects.manage
    - reports.entry-type-analysis
---

## Scopo e contesto

Le voci di commessa sono il registro commesse di WorkDiary: ogni
manutenzione, guasto o montaggio riceve una voce con cliente, tipo e
stato. La voce àncora verbali, tempi e la fatturazione successiva — e
i suoi passaggi di stato tracciano il ciclo di vita della commessa.

## Prerequisiti

- Un **cliente** esistente (obbligatorio), opzionale un **progetto**.
- **Tipi di voce** adeguati (manutenzione, guasto, montaggio…) — li
  cura l'amministrazione.
- Il diritto di creare voci di commessa.

## Procedura consigliata

1. Apri **«Nuova voce»** nella barra superiore o l'azione rapida in
   dashboard.
2. Registra il **cliente** (obbligatorio) ed eventualmente il
   **progetto**.
3. Scegli il **tipo di voce** e descrivi il **contenuto** in una o due
   frasi.
4. Facoltativo: una **durata prevista** in minuti.
5. I passaggi di stato avvengono poi nella **finestra di dettaglio** —
   nessun aggiornamento di massa dalla lista.

![Lista di lavoro del registro commesse con contatori di stato e voci](media/auftraege/arbeitsliste.png)
*La lista di lavoro: contatori di stato in alto, sotto le commesse con stato e azioni.*

## Esempio pratico

Arriva un guasto per telefono: il back office crea in meno di un
minuto una voce di tipo «guasto» con cliente e breve descrizione. Il
tecnico trova la commessa nella sua lista, ci avvia il tempo e allega
più tardi il verbale.

## Errori tipici

- **Aspettarsi cambi di stato di massa:** i passaggi avvengono
  volutamente uno a uno nella finestra di dettaglio — la traccia di
  audit resta pulita.
- **Usare un cliente «varie»:** senza vero collegamento cliente
  mancano poi analisi e fatturazione.
- **Scrivere romanzi:** una o due frasi bastano — i dettagli vanno nel
  verbale.

## Effetti e prossimi passi

Con la voce esiste l'àncora per tutto il resto: registrarci il tempo,
creare se serve un verbale e portare lo stato fino alla chiusura.
L'analisi per tipi mostrerà poi dove va davvero il tempo
dell'azienda.
