---
title: "Ingresso documenti cloud"
topic: cloud-intake.overview
version: 5
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

**Prova della regola e avvio:** ogni regola richiede un percorso di prova, ad esempio una vera cartella cliente. Al salvataggio WorkDiary verifica se il percorso corrisponde al modello, quali variabili vengono riconosciute e quale destinazione ne risulta; senza prova superata non viene salvato nulla. Un riferimento di destinazione fisso assegna tutti i file di una regola a un cliente, progetto, ordine, asset o contratto specifico. Le regole attive di una connessione non possono sovrapporsi: modelli uguali o un percorso di prova che corrisponde a due regole bloccano l’attivazione. Una connessione avvia la prima importazione solo dopo che Lei ha consultato l’anteprima e confermato «Avvia importazione»; una nuova cartella principale richiede una nuova conferma.

**Titolarità dei dati:** se, secondo la titolarità dei dati, un altro sistema gestisce i documenti, il collegamento non acquisisce file nel DMS; ogni file diventa un caso nella posta in arrivo delle integrazioni, senza prova di consegna. **Acquisisci una volta** scarica di nuovo il file e lo crea secondo le regole. Le regole per fatture in entrata, ordini e pacchetti di gara non sono interessate.

## Accesso scaduto, bloccato o disattivato

Se il fornitore rifiuta l'accesso (scaduto o revocato), la connessione mostra **Nuova autenticazione**. Se all'accesso mancano autorizzazioni di lettura, mostra **Bloccata**; dopo errori ripetuti fino alla soglia di disattivazione, **Disattivata**. In tutti e tre i casi l'importazione è sospesa e la scheda offre **Nuovo accesso**. Solo un nuovo accesso riattiva la connessione; modificare la cartella o le regole non basta.
