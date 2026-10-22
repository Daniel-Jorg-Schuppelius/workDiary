---
title: "Automazioni"
topic: admin.automations
version: 4
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
  spese inviata» o «Fattura in entrata ricevuta»), **Azione** (ad es. «Approvare le spese»),
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

## Regole di posta in entrata (fatture in entrata)

Il trigger «Fattura in entrata ricevuta» controlla ogni nuovo documento della
casella delle fatture. Oltre alle colonne della fattura in entrata, le
condizioni possono usare questi campi: `seller_name_key`, `buyer_name_key`, `party_name`, `party_name_key`, `sender_domain`, `mail_subject`, `attachment_names`, `file_name`, `country`, `kind_hint`. Le chiavi del nome sono in
minuscolo e senza forma giuridica, così «Muster GmbH» e «MUSTER» contano
allo stesso modo; `kind_hint` indica il tipo di documento riconosciuto (ad
esempio `reminder` per un sollecito). Operatori: `=`, `!=`, `<`, `<=`, `>`,
`>=`, `in`, `not_in`, `contains`, `not_contains`, `starts_with`,
`ends_with`, `matches`. Esempio:
`{"all": [{"field": "sender_domain", "op": "=", "value": "esempio.it"}]}`.
Azioni possibili: «Archiviare la fattura in entrata come «non è una
fattura»», «Assegnare la fattura in entrata» e «Scartare la fattura in
entrata». La pagina delle regole delle fatture in entrata mostra queste
regole alla contabilità; lì si possono attivare, disattivare ed eliminare, e
ogni azione resta nel registro di audit.
