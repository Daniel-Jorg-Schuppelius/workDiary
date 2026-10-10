---
title: "Connessione Zammad"
topic: admin.zammad
version: 3
keywords:
    - Zammad
    - helpdesk
    - importare ticket
    - sistema di ticket
    - ticket come attività
    - associare coda
    - ID gruppo
    - webhook
    - chiudere ticket
    - ritorno di stato
    - registrazione del tempo nel ticket
    - solo gruppi associati
    - ticket di servizio
    - destinazione dei ticket
    - indirizzi privati
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - helpdesk.overview
    - admin.data-ownership
    - admin.scheduler
    - projects.manage
---

La pagina **Zammad** porta in WorkDiary come attività i ticket del sistema di
ticket Zammad, così che possa registrarvi tempi, tenere prove e fatturare.
Zammad resta il sistema di riferimento; una nuova importazione non crea mai
duplicati. Facoltativamente WorkDiary comunica al ticket le attività
completate e vi registra i tempi rilevati. Trova la pagina nel menu di sistema (l’ingranaggio **Sistema**
nella barra in alto) sotto **Plugin** → **Zammad**, non appena il plugin è
attivo.

## Prerequisiti

- Il plugin è attivato per la Sua organizzazione: **Sistema** → **Plugin** →
  **Plugin**, poi **Attiva** sulla voce Zammad. La connessione vera e propria
  si configura nella pagina **Zammad**, non nella finestra del plugin.
- La pagina è riservata agli amministratori.
- Servono l’indirizzo della Sua istanza Zammad e un token API (in Zammad sotto
  Profilo → Accesso token). Il token deve poter leggere i ticket e, se usa il
  ritorno di stato o la registrazione del tempo, anche modificarli.
- L’istanza deve essere raggiungibile pubblicamente. Se Zammad si trova nella
  Sua rete, attivi **Consenti indirizzi privati/interni** (vedi sotto).
- Per ogni organizzazione esiste esattamente una connessione Zammad.

## Configurare la connessione

Nella sezione **Connessione** compila:

- **Etichetta**: un nome a scelta.
- **URL istanza**: l’indirizzo con cui apre Zammad nel browser. Deve iniziare
  con http:// o https://.
- **Token API**: obbligatorio al primo salvataggio. Viene memorizzato cifrato;
  in seguito un campo vuoto mantiene il token memorizzato.
- **Secret webhook (facoltativo)**: segreto condiviso per le chiamate webhook
  da Zammad che avviano subito l’import. Al salvataggio un campo vuoto mantiene il
  secret memorizzato. Una volta salvata la connessione, sotto il campo
  compare l’**Indirizzo del webhook**: lo inserisca in Zammad alla voce
  Webhook come endpoint, con il secret come token di firma HMAC SHA1, e
  attivi il webhook tramite un trigger. Senza webhook i ticket arrivano con
  l’interrogazione periodica.
- **Progetto predefinito**: destinazione dei ticket il cui gruppo non è
  associato a un progetto. **— senza progetto (globale) —** li crea come
  attività globali senza progetto.
- **Ritorno di stato (stato di destinazione)**: facoltativo, vedi sotto.
- **Registrazione del tempo nel ticket**: facoltativo, vedi sotto.
- **Consenti indirizzi privati/interni**: lo attivi solo se Zammad si trova
  nella Sua rete (per esempio 192.168.x.x). Senza questo interruttore
  WorkDiary rifiuta gli indirizzi interni già al salvataggio. L’attivazione
  viene registrata. Se il gestore della Sua installazione ha bloccato questa
  autorizzazione, l’interruttore non ha effetto.
- **Attivo**: attiva o disattiva la connessione.

**Salva** applica i dati. Quando la connessione è attiva, la pagina ne mostra
lo stato (per esempio **Stato ok**) e **Verifica connessione**.

## Coda → progetto

In **Coda → progetto** associa gruppi Zammad a un progetto WorkDiary: a
sinistra l’**ID gruppo** di Zammad, a destra il progetto. Ci sono sempre tre
righe libere; per altri gruppi salvi e poi li inserisca. WorkDiary scarta al
salvataggio le righe senza ID gruppo o senza progetto. La scelta dei progetti
mostra al massimo 500 progetti.

Un ticket finisce nel progetto del suo gruppo, altrimenti nel **Progetto
predefinito**, altrimenti come attività globale.

**Solo gruppi associati** (disattivato per impostazione predefinita) limita
l’import: se è attivo, WorkDiary crea attività solo per i ticket dei gruppi
associati qui; il **Progetto predefinito** non si applica più. Se è
disattivo, arrivano tutti i ticket visibili al token API. Con l’interruttore
attivo e nessuna associazione, WorkDiary non importa nulla.

## Import e pianificazione

- Ogni 15 minuti WorkDiary interroga Zammad. La frequenza si modifica sotto
  **Attività pianificate**.
- **Importa ora** avvia un import in background.
- Con un secret webhook, un webhook di Zammad avvia inoltre subito l’import.
  Se manca, recupera l’interrogazione periodica.
- WorkDiary recupera tutti i ticket che il token API può vedere; con **Solo
  gruppi associati** le attività nascono solo dai gruppi associati. Ogni
  esecuzione legge l’elenco completo dei ticket, pagina per pagina.
- Ogni ticket aperto diventa un’attività una sola volta. Il titolo è composto
  da numero e titolo del ticket, ed è fatturabile. I ticket chiusi o uniti che
  WorkDiary non conosce ancora non vengono recuperati.
- Se un ticket già collegato viene chiuso o unito in Zammad, WorkDiary porta
  l’attività su **Completato**, senza comunicarlo al ticket. Se poi riapre
  l’attività, resta aperta. Un ticket riaperto in Zammad non modifica
  l’attività.
- WorkDiary non riprende le modifiche al titolo o al gruppo del ticket.
- Se, secondo la **Titolarità dei dati**, un altro sistema gestisce le
  attività, WorkDiary non crea un’attività ma un caso nell’Inbox di
  riconciliazione.

## Riconoscere i clienti

Se un ticket contiene l’e-mail di un cliente o un’organizzazione, WorkDiary
cerca il cliente corrispondente. Con una corrispondenza univoca sposta
l’attività in un progetto di quel cliente, preferibilmente nel suo progetto
predefinito. Altrimenti nasce un suggerimento nell’Inbox di riconciliazione,
dove conferma o sceglie il cliente.

## Ritorno di stato

Inserisca uno stato Zammad in **Ritorno di stato (stato di destinazione)**,
per esempio closed. Quando qualcuno imposta un’attività collegata su
**Completato** in WorkDiary, WorkDiary porta il ticket in quello stato e
aggiunge una nota interna «Risolto in WorkDiary.». Il trasferimento avviene in
background e viene ripetuto in caso di errore. Un campo vuoto disattiva il
ritorno. Oltre a stato, nota e – con la registrazione del tempo – tempi,
WorkDiary non scrive nulla in Zammad.

## Registrazione del tempo nel ticket

In **Registrazione del tempo nel ticket** scelga l’unità in cui il Suo Zammad
rileva i tempi: **Minuti** oppure **Ore**, in base all’unità di rilevazione
del tempo di Zammad. Quando qualcuno registra in WorkDiary un tempo su
un’attività collegata, WorkDiary lo registra nel ticket come rilevazione del
tempo; in ore arrotondato a due decimali. Ogni tempo viene registrato al
massimo una volta. Il trasferimento avviene in background e viene ripetuto in
caso di errore. WorkDiary non trasferisce i tempi modificati o eliminati in
seguito. **Disattivato** spegne la registrazione.

## Destinazione dei ticket

Nella sezione **Destinazione dei ticket**, **Attualmente** indica come
arrivano i nuovi ticket: come **Attività** (predefinito) o come **Ticket di
servizio** di una coda. Per cambiare, scelga la destinazione in **Nuovi
ticket come**, per i ticket di servizio anche la **Coda**, e clicchi su
**Cambia destinazione**; WorkDiary chiede prima conferma.

- I ticket di servizio richiedono il modulo Helpdesk. La coda si crea in
  **Service desk** → **Code**. Zammad gestisce poi i ticket di questa coda.
- I ticket già importati restano dove sono.
- Se nell’Inbox di riconciliazione ci sono conflitti aperti sulla titolarità
  dei dati, WorkDiary rifiuta il cambio finché non sono risolti.
- Ogni cambio viene registrato.
- I ticket di servizio non ricevono suggerimenti di cliente, ritorno di stato
  né registrazione del tempo; questo vale solo per le attività.

## Limiti

- **Disconnetti** disattiva soltanto la connessione. Attività e collegamenti
  restano, e in Zammad non cambia nulla. Per riattivarla imposti **Attivo** e
  salvi.
- Le attività non vengono mai eliminate, anche se il ticket scompare in
  Zammad.

## Errori frequenti

- «L'URL dell'istanza deve iniziare con http:// o https://.»: inserisca
  l’indirizzo completo.
- «Una nuova connessione richiede un token API.»: al primo salvataggio manca
  il token.
- «Nessuna connessione Zammad attiva.» con **Importa ora**: la connessione è
  disattivata o incompleta.
- «L'URL dell'istanza punta a un indirizzo privato/interno.»: se Zammad si
  trova nella Sua rete, attivi **Consenti indirizzi privati/interni**. Se il
  gestore ha bloccato questa autorizzazione, l’istanza ha bisogno di un
  indirizzo raggiungibile pubblicamente.
- **Stato difettoso** con «API Zammad non raggiungibile o token non valido.»:
  controlli indirizzo e token. Un errore API Zammad con RuntimeException
  indica spesso un indirizzo di una rete interna senza autorizzazione.
- «Scelga una coda.» con **Cambia destinazione**: manca la coda per i ticket
  di servizio.
- I ticket finiscono nel progetto sbagliato: controlli gli ID gruppo in
  **Coda → progetto** e i suggerimenti di cliente nell’inbox.
