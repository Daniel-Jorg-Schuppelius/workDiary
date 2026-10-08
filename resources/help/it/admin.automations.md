---
title: "Automazioni"
topic: admin.automations
version: 3
keywords:
    - workflow
    - regole
    - regola se allora
    - trigger
    - motore di regole
    - azione automatica
    - automatizzare processi
    - creare regola
    - flusso di lavoro
    - automazione
audience:
    - admin
related:
    - admin.handbook
    - admin.notification-rules
    - admin.webhooks
---

Le automazioni sono flussi basati su regole secondo lo schema
**evento → condizione → azione**. Quando si verifica un evento di
attivazione definito e le condizioni impostate corrispondono, viene
eseguita l'azione associata. Le regole valgono per singola
organizzazione e sono rigorosamente limitate al proprio tenant. Ogni
valutazione viene registrata nel registro di audit.

La panoramica mostra tutte le regole con **Prio**, **Nome**,
**Trigger**, **Azione/i** e **Attivo**, ordinate per impostazione
predefinita per priorità. Sono disponibili le seguenti azioni:

- **Crea nuova regola (JSON)**: apre la finestra di dialogo **Nuova
  regola di automazione** con **Nome**, **Attivatore** (ad es. «Nota
  spese inviata»), **Azione** (ad es. «Approvare le spese»),
  **Priorità** e **Condizioni (JSON)**. L'azione deve corrispondere
  all'attivatore; una condizione vuota vale sempre. **Crea regola**
  salva la regola.
- **Disattiva**/**Attiva**: le regole disattivate vengono conservate,
  ma non attivano più alcuna azione.
- Vista di dettaglio (clic sul nome): mostra attivatore, condizioni e
  azioni nonché il **Registro di audit (ultimi 50)** con **Momento**,
  **Soggetto**, **Decisione** e **Registro**.
- **Elimina**: rimuove la regola in modo definitivo.

La **Priorità** determina l'ordine quando più regole riguardano lo
stesso attivatore (il valore più basso per primo, predefinito 100).
Viene eseguita solo la prima regola corrispondente; una regola scatta
al massimo una volta per record. Un JSON non valido nelle condizioni
viene rifiutato.

Autorizzazione: le automazioni sono gestite dagli amministratori
dell'organizzazione.

Nota: per le semplici notifiche le **Regole di notifica** sono spesso
la scelta più semplice; per i sistemi esterni consulti i **Webhook**.
