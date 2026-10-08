---
title: "Installazione"
topic: install.wizard
version: 3
keywords:
    - procedura guidata
    - configurazione iniziale
    - prima installazione
    - setup
    - requisiti di sistema
    - configurare database
    - creare amministratore
    - impostazioni SMTP
    - server di posta
    - notifiche push
    - VAPID
audience: [admin]
related:
    - admin.tenants
    - auth.login
---

L’assistente di installazione La guida passo dopo passo nella prima
configurazione di WorkDiary. Ogni passaggio salva subito i propri valori,
così un’interruzione può essere ripetuta in qualsiasi momento senza
rischi. Con **Avanti** passa al passaggio successivo, con **Indietro** a
quello precedente. Non appena l’installazione è completata, l’assistente
viene bloccato e non è più richiamabile.

I passaggi in sintesi:

- **Requisiti**: verifica se il server soddisfa tutti i requisiti per il
  **Driver del database** scelto. Dopo aver risolto i punti segnalati,
  verifichi di nuovo con **Aggiorna**.
- **Applicazione**: **Nome applicazione**, **URL applicazione**,
  **Ambiente**, **Lingua** e **Fuso orario**. Se manca ancora una chiave
  dell’applicazione, viene generata automaticamente; una chiave esistente
  resta invariata.
- **Database**: **Driver** e dati di connessione. **Connetti e migra**
  verifica la connessione, configura il database e crea ruoli e
  autorizzazioni. Attivi l’opzione per svuotare il database prima della
  migrazione solo se il database deve essere vuoto o se un tentativo
  precedente è stato interrotto.
- **Amministratore**: creazione della prima organizzazione (**Nome
  dell’organizzazione**) e dell’account amministratore con **Crea
  amministratore**.
- **E-mail**: canale di invio (**Mailer**) e mittente delle e-mail. Con
  «log» le e-mail vengono solo registrate e non inviate; con «smtp»
  inserisce **Host SMTP**, porta, credenziali e **Crittografia**, oltre a
  **Indirizzo mittente** e **Nome mittente**.
- **Integrazioni**: accessi facoltativi come la **Chiave API di
  Lexoffice** e la coppia di chiavi per **Push web (VAPID)**, che **Genera
  chiave** crea automaticamente. Tutto può essere completato in seguito.
- **Completamento**: **Completa l'installazione** blocca l’assistente,
  scarta le impostazioni memorizzate nella cache affinché i nuovi valori
  valgano subito, e porta all’accesso. L’amministratore accede poi di
  nuovo normalmente.
