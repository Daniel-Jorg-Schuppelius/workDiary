---
title: "Registro di audit"
topic: audit.log
version: 1
audience:
    - admin
related:
    - admin.security
    - admin.handbook
    - privacy.overview
---

Il registro di audit (`/audit`) è il protocollo a prova di revisione di
modifiche e azioni nel sistema: le voci sono **append-only**, collegate
tramite una **catena di hash SHA-256** (GoBD) e non possono essere
modificate o cancellate. La lista si filtra per **azione**, **tipo** di
oggetto, **utente** e **periodo**; per ogni voce vedi data e ora, utente,
azione, oggetto, modifiche e indirizzo IP. L'integrità si verifica con
`php artisan audit:verify`, che in caso di rottura della catena termina
con exit code 1 (ideale per cron/CI); con `--chain` puoi controllare una
singola catena. Il registro è uno strumento di sola lettura e non
modifica alcun dato.
