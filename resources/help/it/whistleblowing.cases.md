---
title: "Canale di segnalazione – gestione dei casi"
topic: whistleblowing.cases
version: 1
audience: []
related:
    - whistleblowing.portal
    - whistleblowing.report
    - admin.security
    - privacy.overview
---

Qui gestisci le segnalazioni ricevute da segnalanti interni ed esterni
(`/compliance/meldungen`). I permessi del canale di segnalazione sono
volutamente **separati** dall'amministrazione: nessun bypass admin, ogni
accesso richiede permesso **e** assegnazione concreta al caso, oltre a una
autenticazione a due fattori dedicata. L'elenco mostra solo dati anagrafici
del caso, senza anteprima dei contenuti, che sono cifrati con una chiave per
caso (DEK). Nel dettaglio puoi **confermare la ricezione** (termine di 7
giorni), cambiare lo **stato** lungo il ciclo di vita, **assegnare
incaricati**, scrivere **note interne**, inviare **messaggi al segnalante**
tramite la casella anonima e scaricare gli **allegati** cifrati; conflitti
di interesse e persone coinvolte vengono bloccati per il caso, ogni passo è
tracciato in una catena hash di eventi. La cancellazione controllata a fine
conservazione avviene per **crypto-shredding** ed è irreversibile.
