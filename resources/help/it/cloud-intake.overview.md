---
title: "Ingresso documenti cloud"
topic: cloud-intake.overview
version: 2
keywords:
    - Paperless-ngx
    - Seafile
    - Dropbox
    - OneDrive
    - SharePoint
    - Google Drive
    - cartella monitorata
    - importazione documenti
    - cartella cloud
    - regola cartella
    - importazione automatica
    - collegare archiviazione cloud
audience: []
related:
    - documents.manage
    - admin.integrations
---

WorkDiary LEGGE i documenti dalle cartelle monitorate in Dropbox, OneDrive/SharePoint, Google Drive e Nextcloud nonché da Paperless-ngx e Seafile e li instrada verso le fatture in entrata o il DMS tramite regole di cartella.

**Connessioni:** per ogni provider si collega e conferma un account via OAuth; poi si scelgono container (drive/biblioteca) e cartella radice. L’import parte solo con almeno una regola valida.

**Paperless-ngx:** URL di base e token API stanno nelle impostazioni del plugin; «Collegare Paperless-ngx» verifica l’account. Come container scelga tutti i documenti oppure un tag, un corrispondente o un tipo di documento. Il percorso di un documento è tipo di documento/corrispondente/nome file («-» se non assegnato); come cartella radice basta «/». Viene acquisito il file originale, così i dati di fattura elettronica incorporati restano; l’origine sul record rimanda all’originale in Paperless. I documenti taggati in seguito vengono rilevati dall’esecuzione successiva confrontando l’elenco dei documenti. Il token legge con i diritti del suo utente: serve l’accesso in lettura a documenti, tag, corrispondenti, tipi di documento e impostazioni dell’interfaccia; viene acquisito solo ciò che questo utente può vedere.

**Seafile:** indirizzo e token API stanno nelle impostazioni del plugin; «Collegare Seafile» verifica l’account. Come container scelga una libreria e al suo interno la cartella radice; le librerie cifrate non vengono proposte. Se sotto la cartella radice nulla è cambiato dall’ultima esecuzione, WorkDiary non legge alcuna cartella. WorkDiary riconosce un file dal suo contenuto: rinominarlo o spostarlo non crea un nuovo giustificativo, un file con contenuto modificato sì. L’origine sul giustificativo rimanda al file in Seafile. Il token di account lo fornisce l’API di Seafile: POST a /api2/auth-token/ con nome utente e password, con accesso a due fattori anche con l’intestazione X-Seafile-OTP e il codice attuale.

**Regole:** modelli di percorso con * e ** più variabili come {customer_number} assegnano i file a clienti, progetti, commesse, asset o contratti esistenti — mai con creazione automatica. I casi dubbi finiscono nella inbox di integrazione.

**Sicurezza:** solo scope di lettura, token cifrati, file di origine intatti; i webhook sono solo segnali di risveglio — fa fede il ciclo delta ripristinabile.
