---
title: "Organizzazioni e tenant"
topic: admin.tenants
version: 3
keywords:
    - gestione tenant
    - multi-tenant
    - multiaziendale
    - creare organizzazione
    - aggiungere azienda
    - eliminare organizzazione
    - disattivare organizzazione
    - cambiare organizzazione
    - esportazione dati
    - purge
    - cambio piano
    - elenco organizzazioni
audience:
    - admin
related:
    - admin.handbook
    - admin.license
    - admin.roles
    - admin.organization-settings
---

Qui gestisce le organizzazioni (tenant): ogni organizzazione è
un'unità isolata e tutti i dati appartengono a un solo tenant. Azioni
tipiche: **creare/modificare**, **disattivare/riattivare**
(reversibile), **esportare** (portabilità dei dati, art. 20 GDPR),
**eliminare definitivamente** (purge, art. 17 GDPR) e **cambiare
contesto** per gli admin globali. Il piano o la licenza
dell'organizzazione determina i moduli abilitati. Attenzione: il purge
è irreversibile — offra prima un export e verifichi gli obblighi di
conservazione; la disattivazione è l'alternativa sicura.

Approvazioni: nella sezione omonima stabilisce quale ruolo vede le fasi di
approvazione di una trattativa contrattuale in «Approvazioni», per tipo di
fase (commerciale, tecnica, risorse umane). Se lasciato vuoto vale il
predefinito: Contabilità, Capo team, Gestione del personale.
L'approvazione dalla pratica non ne è influenzata.

## Elenco delle organizzazioni e propria organizzazione

L'elenco **Organizzazioni** con tutti i tenant è riservato alla gestione della
piattaforma: nel menu di sistema (l'icona a ingranaggio **Sistema**
nell'intestazione) sotto **Organizzazione** → **Organizzazioni**. Gli operatori
della piattaforma senza un'organizzazione propria trovano inoltre nel menu di
amministrazione (icona **Amministrazione** nell'intestazione) sotto
**Personale** la voce **Dipendente**, che porta anch'essa all'elenco delle
organizzazioni. Quando un tale amministratore apre l'elenco, WorkDiary assegna
il suo account alla prima organizzazione creata – da quel momento
**Dipendente** porta alla gestione dei membri di quell'organizzazione.

Gli amministratori di un'organizzazione modificano la propria organizzazione
nel menu di sistema sotto **Organizzazione** → **Organizzazione** (finestra
**Modifica organizzazione**; dettagli nell'argomento «Organizzazione e
impostazioni»). **Piano** e stato attivo li imposta solo la gestione della
piattaforma: gli amministratori dell'organizzazione vedono il piano nella
sezione **Piano e stato** solo a titolo informativo («Il piano segue la licenza
ed è gestito dal gestore.») e non hanno un interruttore per lo stato attivo.
