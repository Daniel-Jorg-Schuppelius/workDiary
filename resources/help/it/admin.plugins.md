---
title: "Plugin"
topic: admin.plugins
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.toggl
    - admin.openproject
    - admin.lexoffice
    - admin.remote-support
---

Qui gestisci i plugin e le integrazioni installate; attivazione,
impostazioni, stato di salute ed errori valgono **per organizzazione**.
La lista mostra stato e health con le azioni per configurare,
attivare/disattivare, eseguire subito l'health check o riattivare dopo
una disattivazione automatica. Nella configurazione un campo password
vuoto lascia invariato il valore esistente e **Testa connessione**
esegue un health check senza salvare. In caso di errori ripetuti il
plugin viene disattivato automaticamente solo per l'organizzazione
interessata; il registro errori conserva ogni voce con fase, messaggio
e stacktrace e permette di marcarla come confermata. Un plugin
disattivato sospende import, export e health check finché non viene
riattivato.
