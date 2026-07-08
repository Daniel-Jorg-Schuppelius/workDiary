---
title: "Webhook"
topic: admin.webhooks
version: 1
audience:
    - admin
related:
    - admin.notification-rules
    - admin.handbook
    - glossary.core
---

I webhook inviano notifiche di eventi a sistemi esterni: al verificarsi
di un evento sottoscritto WorkDiary recapita un payload JSON firmato
via `POST` HTTPS alla tua URL. Crea il webhook con nome e URL di
destinazione, sottoscrivi gli **eventi** desiderati, copia subito il
**signing key** (mostrato in chiaro una sola volta, poi rotabile) e
verifica con **Invia evento di test**. Il payload è volutamente
minimale; la firma HMAC-SHA256 su `timestamp.body` va verificata in
tempo costante, scartando richieste con timestamp troppo vecchio. Le
consegne fallite vengono ritentate con backoff e dopo più errori
consecutivi l'endpoint viene disattivato automaticamente; il registro
di consegna mostra stato, codice HTTP e orario degli ultimi tentativi.
