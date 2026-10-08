---
title: "Webhook"
topic: admin.webhooks
version: 2
keywords:
    - URL di callback
    - notifiche eventi
    - sottoscrizione eventi
    - firma HMAC
    - chiave di firma
    - automazione
    - integrazione in uscita
    - registro consegne
    - payload
audience:
    - admin
    - geschaeftsfuehrung
related:
    - admin.notification-rules
    - admin.handbook
    - glossary.core
---

I webhook inviano notifiche di eventi in uscita a sistemi esterni (ad
es. un ERP, una piattaforma di automazione o uno strumento proprio).
Non appena si verifica un evento sottoscritto, WorkDiary consegna al
Suo URL un payload JSON firmato tramite `POST` HTTPS.

Procedura tipica:

1. **Crea webhook**: inserire etichetta e URL di destinazione (HTTPS).
2. Sottoscrivere gli **eventi** (caselle di controllo). Solo gli eventi
   selezionati attivano un invio.
3. La **Chiave di firma** viene mostrata in chiaro una sola volta — la
   copi subito. In seguito viene conservata solo in forma cifrata e
   all'occorrenza può essere ruotata.
4. Con **Invia evento di test** verificare la raggiungibilità e il
   controllo della firma.

## Payload

Il payload è volutamente minimo e povero di dati personali:

```json
{
  "event": "openIssue.assigned",
  "occurred_at": "2026-06-14T12:00:00+00:00",
  "organization": { "id": 1 },
  "data": {
    "subject_type": "OpenIssue",
    "subject_id": 42,
    "title": "..."
  }
}
```

Se necessario, arricchisca il payload con ulteriori campi tramite l'API
REST.

## Verificare la firma

Ogni consegna contiene le seguenti intestazioni:

- `X-WorkDiary-Signature: sha256=<hmac>`
- `X-WorkDiary-Timestamp: <ora-unix>`
- `X-WorkDiary-Event: <chiave-evento>`

L'HMAC viene calcolato su `<timestamp>.<body>` con la chiave di firma:

```text
expected = HMAC_SHA256(timestamp + "." + raw_body, signing_key)
```

Confronti `expected` con il valore della firma in tempo costante e
scarti le richieste con una marca temporale troppo vecchia (protezione
dal replay).

## Affidabilità

Le consegne non riuscite vengono ripetute con backoff. Dopo diversi
tentativi falliti consecutivi l'endpoint viene **disattivato
automaticamente**; per riattivarlo lo salvi come attivo. Il registro
delle consegne di ciascun endpoint mostra stato, codice HTTP e momento
degli ultimi tentativi.
