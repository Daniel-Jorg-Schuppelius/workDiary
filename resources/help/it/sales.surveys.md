---
title: "Sondaggi"
topic: sales.surveys
version: 1
audience: []
related:
    - contacts.manage
---

Un **motore di sondaggi** essenziale per NPS e questionari liberi — nessuna
marketing automation. Tipi di domanda: **NPS (0–10)**, scala (1–5), scelta,
testo libero. La partecipazione passa per un **link monouso** (valido 30
giorni), senza login al portale.

## Tre regole obbligatorie

- **Protezione anti-affaticamento:** al massimo un invito per indirizzo
  e-mail in 90 giorni — su **tutti** i questionari. Il trigger automatico
  salta in silenzio, l’invio manuale viene rifiutato con un messaggio.
- **Opt-out per cliente:** chi ha rifiutato non viene più invitato.
- **L’anonimato è una proprietà di salvataggio:** nei questionari anonimi la
  risposta non porta alcun riferimento all’invito e l’invito nessun orario di
  risposta — un join di re-identificazione non ha campi. Per questo
  l’impostazione non è più modificabile dopo il primo invito.

## Trigger

Manualmente per cliente — o automaticamente **dopo la chiusura del ticket**
(attivabile sul questionario). Un invito fallito non impedisce mai il cambio
di stato del ticket.

## Valutazione

**Punteggio NPS** = %promotori (9–10) − %detrattori (0–6). Senza risposte
niente punteggio — nessun valore significa «niente da calcolare», non zero.
La CSAT dei ticket (valutazione nel portale) resta indipendente.
