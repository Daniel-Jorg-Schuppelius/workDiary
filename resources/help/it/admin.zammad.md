---
title: "Connessione Zammad"
topic: admin.zammad
version: 1
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
completate. Trova la pagina nel menu di sistema (l’ingranaggio **Sistema**
nella barra in alto) sotto **Plugin** → **Zammad**, non appena il plugin è
attivo.

## Prerequisiti

- Il plugin è attivato per la Sua organizzazione: **Sistema** → **Plugin** →
  **Plugin**, poi **Attiva** sulla voce Zammad. La connessione vera e propria
  si configura nella pagina **Zammad**, non nella finestra del plugin.
- La pagina è riservata agli amministratori.
- Servono l’indirizzo della Sua istanza Zammad e un token API (in Zammad sotto
  Profilo → Accesso token). Il token deve poter leggere i ticket e, se usa il
  ritorno di stato, anche modificarli.
- L’istanza deve essere raggiungibile pubblicamente. WorkDiary rifiuta gli
  indirizzi di una rete interna.
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
  secret memorizzato. La pagina non mostra l’indirizzo del webhook;
  senza webhook i ticket arrivano con l’interrogazione periodica.
- **Progetto predefinito**: destinazione dei ticket il cui gruppo non è
  associato a un progetto. **— senza progetto (globale) —** li crea come
  attività globali senza progetto.
- **Ritorno di stato (stato di destinazione)**: facoltativo, vedi sotto.
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

## Import e pianificazione

- Ogni 15 minuti WorkDiary interroga Zammad. La frequenza si modifica sotto
  **Attività pianificate**.
- **Importa ora** avvia un import in background.
- Con un secret webhook, un webhook di Zammad avvia inoltre subito l’import.
  Se manca, recupera l’interrogazione periodica.
- WorkDiary recupera i ticket che il token API può vedere – non solo i gruppi
  associati.
- Ogni ticket diventa un’attività una sola volta. Il titolo è composto da
  numero e titolo del ticket, ed è fatturabile. I ticket chiusi o uniti
  arrivano come attività completate.
- WorkDiary non riprende le modifiche successive al ticket (titolo, stato,
  gruppo); l’attività resta com’è stata creata.
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
ritorno. WorkDiary non scrive altri dati in Zammad.

## Limiti

- Un’esecuzione recupera solo la prima pagina dell’elenco dei ticket, al
  massimo 100 ticket.
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
- **Stato difettoso** con «API Zammad non raggiungibile o token non valido.»:
  controlli indirizzo e token. Un errore API Zammad con RuntimeException
  indica spesso un indirizzo di una rete interna.
- I ticket finiscono nel progetto sbagliato: controlli gli ID gruppo in
  **Coda → progetto** e i suggerimenti di cliente nell’inbox.
