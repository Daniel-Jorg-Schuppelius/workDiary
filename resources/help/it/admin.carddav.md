---
title: "Collegare una rubrica CardDAV"
topic: admin.carddav
version: 1
keywords:
    - CardDAV
    - collegare una rubrica
    - contatti Nextcloud
    - Radicale
    - Baïkal
    - importare contatti
    - confrontare contatti
    - password per app
    - vCard
    - sincronizzazione contatti
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - contacts.manage
    - admin.scheduler
---

La pagina **CardDAV** collega WorkDiary a una rubrica che si trova sul Suo
server CardDAV, ad esempio Nextcloud, Radicale o Baïkal. WorkDiary legge
soltanto i contatti e Le propone di abbinarli ai Suoi clienti. Non scrive mai
nulla nella rubrica, non unisce record di propria iniziativa e non crea
clienti. Non Le serve un account Microsoft o Google.

## Prerequisiti

- Il plugin **CardDAV** è attivato per la Sua organizzazione in **Plugin**.
  Successivamente compare la voce **CardDAV** nel menu di sistema (icona a
  ingranaggio **Sistema**), nel gruppo **Plugin**.
- Conosce l'indirizzo del server CardDAV, un nome utente e una password. Se sul
  server è attivo l'accesso in due passaggi (frequente con Nextcloud), Le serve
  una password per app, che crea nel Suo account sul server.
- La pagina è riservata agli amministratori della Sua organizzazione. Ogni
  organizzazione ha esattamente una connessione CardDAV.

## Configurare la connessione

1. Compili i campi della sezione **Connessione**:
   - **Denominazione**: un nome per la connessione, ad esempio «Nextcloud
     ufficio».
   - **URL di base DAV**: per Nextcloud l'indirizzo fino a /remote.php/dav
     incluso, per Radicale e Baïkal la radice del server. L'indirizzo deve
     iniziare con http:// o https://.
   - **Nome utente** e **Password per app**. La password viene salvata
     cifrata e non viene più mostrata. Se per una connessione esistente lascia
     vuoto il campo, resta valida la password salvata.
   - **Consenti indirizzi privati/interni**: lo attivi solo se il server si
     trova nella Sua rete (ad esempio 192.168.x.x). Senza questa opzione
     WorkDiary rifiuta gli indirizzi interni. L'attivazione viene registrata.
   - **Attivo**: attiva o disattiva la connessione.
2. Clicchi su **Salva**.
3. Clicchi in alto su **Cerca rubriche**. WorkDiary interroga il server ed
   elenca le rubriche trovate nella sezione **Rubrica**.
4. Selezioni una rubrica e clicchi su **Usa questa rubrica**. Da questo momento
   è la sorgente di sincronizzazione; la pagina la mostra come «Sorgente di
   sincronizzazione attuale».

Si possono scegliere solo rubriche dell'ultima ricerca che si trovano sullo
stesso server dell'URL di base. Non è possibile inserire un indirizzo
qualsiasi come sorgente.

## Cosa viene sincronizzato e quando

- **Direzione:** solo dal server CardDAV verso WorkDiary.
- **Contenuto:** nome, azienda, indirizzo e-mail, numeri di telefono, cellulare
  e fax, nota e indirizzo postale con paese. Se ci sono più indirizzi e-mail o
  numeri, WorkDiary preferisce quelli contrassegnati come di lavoro.
- **Momento:** la sincronizzazione viene eseguita automaticamente ogni ora.
  Modifica la frequenza in **Attività pianificate**. **Sincronizza ora** la
  avvia subito; viene poi eseguita in background e al termine la pagina mostra
  «Ultima sincronizzazione …».
- **Solo modifiche:** WorkDiary salta i contatti invariati. Vengono elaborate
  solo le schede nuove o modificate.

## Abbinamento ai clienti

- Se un contatto corrisponde in modo univoco a un solo cliente, WorkDiary
  collega i due. Se in seguito un contatto collegato cambia e i suoi dati
  differiscono da quelli del cliente, nasce un conflitto di campo
  nell'**Inbox di riconciliazione**: i dati del cliente non vengono mai
  sovrascritti in silenzio.
- Tutti gli altri contatti (nessun cliente corrispondente o più candidati)
  arrivano come proposte nell'**Inbox di riconciliazione**. Lì assegna il
  contatto a un cliente, lo crea come nuovo record o lo scarta.
- Se un contatto viene eliminato nella rubrica, WorkDiary scarta la sua
  proposta ancora aperta. Le assegnazioni già effettuate restano valide.
- L'**Inbox di riconciliazione** è accessibile alle persone autorizzate a
  gestire la fatturazione.

## Modificare, disconnettere, ricominciare

- Se modifica l'**URL di base DAV**, WorkDiary scarta la rubrica scelta e lo
  stato di sincronizzazione precedente. Cerchi e scelga poi di nuovo la
  rubrica.
- Se sceglie un'altra rubrica, la sincronizzazione riparte da zero.
- **Disconnetti** rende inattiva la connessione. Le proposte già create vengono
  conservate. Per riprendere, riattivi **Attivo** e clicchi su **Salva**.

## Problemi tipici

- **Indirizzo interno rifiutato:** il messaggio «L'URL di base punta a un
  indirizzo privato/interno» compare quando il server si trova nella Sua rete.
  Attivi **Consenti indirizzi privati/interni**.
- **La ricerca non riesce:** «Ricerca delle rubriche non riuscita» significa
  che il server non è raggiungibile o che le credenziali non sono corrette.
  Verifichi URL di base, nome utente e password per app.
- **Nessuna rubrica:** «Nessuna rubrica trovata sul server»: l'account non ha
  una rubrica, oppure l'URL di base punta al livello sbagliato.
- **Rubrica estranea:** «L’indirizzo non appartiene al server CardDAV
  configurato»: la rubrica scelta si trova su un server diverso da quello
  dell'URL di base.
- **Nessuna sincronizzazione:** se manca **Sincronizza ora** o WorkDiary
  segnala «Sincronizzazione non possibile», la connessione è inattiva, non è
  stata scelta una rubrica, oppure è stata sospesa dopo ripetuti errori
  consecutivi. La pagina mostra l'ultimo errore in alto.
- **Verificare lo stato:** accanto al titolo della pagina compare l'ultimo
  stato verificato della connessione. **Verifica connessione** lo controlla
  subito.
