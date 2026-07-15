---
title: "Ingresso documenti cloud"
topic: cloud-intake.overview
version: 1
audience: []
related:
    - documents.manage
    - admin.integrations
---

WorkDiary LEGGE i documenti dalle cartelle monitorate in Dropbox, OneDrive/SharePoint e Google Drive e li instrada verso le fatture in entrata o il DMS tramite regole di cartella.

**Connessioni:** per ogni provider si collega e conferma un account via OAuth; poi si scelgono container (drive/biblioteca) e cartella radice. L’import parte solo con almeno una regola valida.

**Regole:** modelli di percorso con * e ** più variabili come {customer_number} assegnano i file a clienti, progetti, commesse, asset o contratti esistenti — mai con creazione automatica. I casi dubbi finiscono nella inbox di integrazione.

**Sicurezza:** solo scope di lettura, token cifrati, file di origine intatti; i webhook sono solo segnali di risveglio — fa fede il ciclo delta ripristinabile.
