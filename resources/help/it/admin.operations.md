---
title: "Attività operative e finestre di manutenzione"
topic: admin.operations
version: 1
audience:
    - admin
related:
    - admin.backups
    - admin.diagnostics
---

WorkDiary supporta l'esercizio corrente con due strumenti: il **centro
attività** per i controlli operativi ricorrenti e la pianificazione
delle **finestre di manutenzione**.

**Controlli operativi come scansione:** una scansione ricorrente (per
impostazione predefinita ogni ora) verifica i punti rilevanti per
l'esercizio – ad esempio certificati in scadenza, backup mancanti,
nonché date di scadenza di licenze e credenziali. I punti rilevati
compaiono come attività prioritizzate nel centro attività, ordinate
per gravità (critico prima di avviso prima di nota) e filtrabili per
stato, tipo e gravità.

**Elaborare le attività:** ogni attività può essere **completata**,
**posticipata** (snooze per un numero di giorni impostabile),
**delegata** (a una persona della propria organizzazione) o
**ignorata** (con motivazione obbligatoria). Le attività completate
possono essere riaperte. Tutti i cambi di stato vengono protocollati
in modo a prova di revisione. La visibilità è legata
all'organizzazione; le attività a livello di installazione risiedono
nell'organizzazione del gestore e sono contrassegnate di conseguenza.

**Pianificare le finestre di manutenzione:** le finestre di
manutenzione si annunciano con inizio, fine e preavviso facoltativo –
dal momento dell'annuncio gli utenti vedono un messaggio con il testo
informativo indicato. Una finestra vale a scelta **a livello di
sistema** oppure solo per l'**organizzazione** corrente.

**Svolgimento di una finestra:** dopo la pianificazione, una finestra
attraversa i passaggi annuncio, avvio, eventuale proroga e chiusura.
Durante la finestra in corso è possibile attivare facoltativamente la
**modalità di sola lettura** (gli utenti vedono i dati ma non
modificano nulla) e **bloccare l'ingresso dei dati** (le consegne
esterne vengono sospese). Se qualcosa va storto, un **rollback**
documenta l'interruzione con relative note; le finestre pianificate
possono anche essere annullate senza sostituzione. Ogni azione viene
registrata nell'audit trail.

**Raccomandazione:** pianificare le finestre di manutenzione con
sufficiente anticipo, affinché l'annuncio produca il suo effetto, ed
elaborare regolarmente il centro attività – prima le attività
critiche. Il posticipo è pensato per rinvii consapevoli, non come
stato permanente.
