---
title: "Destinazioni di backup cloud"
topic: backup-targets.overview
version: 1
audience: []
related:
    - admin.integrations
---

WorkDiary salva l'intera installazione in forma cifrata su Dropbox, OneDrive o Google Drive (copia offsite della strategia 3-2-1). Il testo in chiaro non lascia mai l'installazione — vengono caricate solo parti cifrate con un manifesto di commit firmato.

**Connessioni:** Solo il gestore della piattaforma amministra le destinazioni di backup; ogni provider riceve un proprio account OAuth (separato dall'ingresso documenti, scope di scrittura dedicati). Se manca un'autorizzazione necessaria, la destinazione è visibilmente bloccata.

**Chiavi:** BACKUP_MASTER_KEY (ENV, conservare offline!) è l'unico percorso regolare di decifratura; una coppia di chiavi di recupero opzionale decifra in emergenza. Senza chiave di recupero la pagina avverte in modo permanente — la perdita della chiave master rende inutilizzabili tutti i backup.

**Esercizio:** L'esecuzione notturna crea uno snapshot (dump DB + file), lo cifra, carica le parti in modo ripristinabile e applica la retention (7 giornaliere / 4 settimanali / 12 mensili; la conservazione legale protegge singole generazioni). Una verifica settimanale a campione controlla firma e hash; il test di ripristino ripristina in una directory isolata e registra RPO/RTO — fino al primo test verde una generazione vale come «salvata, ripristino non confermato».
