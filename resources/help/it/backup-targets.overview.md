---
title: "Destinazioni di backup cloud"
topic: backup-targets.overview
version: 2
keywords:
    - backup offsite
    - salvataggio dati
    - Dropbox
    - OneDrive
    - Google Drive
    - backup cifrato
    - regola 3-2-1
    - ripristino
    - test di ripristino
    - chiave master
    - conservazione backup
    - disaster recovery
    - Nextcloud
    - WebDAV
    - S3
    - Seafile
audience: []
related:
    - admin.integrations
---

WorkDiary salva l'intera installazione in forma cifrata su Dropbox, OneDrive, Google Drive, Nextcloud, un server WebDAV, uno storage compatibile S3 o Seafile (copia offsite della strategia 3-2-1). Il testo in chiaro non lascia mai l'installazione — vengono caricate solo parti cifrate con un manifesto di commit firmato.

**Connessioni:** Solo il gestore della piattaforma amministra le destinazioni di backup; Dropbox, OneDrive e Google Drive ricevono ciascuno un proprio account OAuth (separato dall'ingresso documenti, scope di scrittura dedicati), Nextcloud, WebDAV, S3 e Seafile credenziali proprie. Al collegamento WorkDiary scrive, legge e cancella una volta un file di prova; solo allora la destinazione è attiva. Se manca un'autorizzazione necessaria, la destinazione è visibilmente bloccata.

**Seafile:** URL del server (solo HTTPS), token di account di un utente Seafile dedicato e nome o ID di una libreria esistente non cifrata. WorkDiary scrive solo in una propria cartella al suo interno e verifica la dimensione di ogni file caricato. Il token di account lo fornisce l’API di Seafile: POST a /api2/auth-token/ con nome utente e password, con accesso a due fattori anche con l’intestazione X-Seafile-OTP e il codice attuale.

**Chiavi:** BACKUP_MASTER_KEY (ENV, conservare offline!) è l'unico percorso regolare di decifratura; una coppia di chiavi di recupero opzionale decifra in emergenza. Senza chiave di recupero la pagina avverte in modo permanente — la perdita della chiave master rende inutilizzabili tutti i backup.

**Esercizio:** L'esecuzione notturna crea uno snapshot (dump DB + file), lo cifra, carica le parti in modo ripristinabile e applica la retention (7 giornaliere / 4 settimanali / 12 mensili; la conservazione legale protegge singole generazioni). Una verifica settimanale a campione controlla firma e hash; il test di ripristino ripristina in una directory isolata e registra RPO/RTO — fino al primo test verde una generazione vale come «salvata, ripristino non confermato».
