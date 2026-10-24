---
title: "Importazione di attività e tempi da Redmine"
topic: admin.redmine
version: 1
keywords:
    - Redmine
    - importare ticket
    - importare registrazioni di tempo
    - chiave API
    - self-hosted
    - associazione utenti
    - importazione di attività
    - caso da verificare
audience:
    - admin
related:
    - admin.plugins
    - admin.integration-inbox
    - admin.data-ownership
    - projects.manage
    - admin.scheduler
---

La pagina **Redmine** importa i ticket di progetti Redmine selezionati come
attività in progetti WorkDiary e, se desiderato, le registrazioni di tempo
inserite lì come tempi registrati. L’importazione legge soltanto: WorkDiary non
scrive nulla in Redmine. Trova la pagina nel menu di sistema (l’ingranaggio
**Sistema**) sotto **Plugin** → **Redmine**, non appena il plugin è attivo.

## Configurare l’accesso

1. In Redmine attivi il servizio web REST in **Amministrazione** →
   **Impostazioni** → **API** e copi la **Chiave di accesso API** in **Il mio
   account**.
2. Sulla pagina apra **Credenziali** nel menu ⋯ e inserisca **URL di base** e
   **Chiave API**. Per un’istanza nella propria rete attivi anche **Consentire
   indirizzi privati**. Lì decide anche se i tempi importati sono fatturabili
   (**Importare i tempi come fatturabili**) e quanti giorni indietro legge ogni
   esecuzione (**Finestra temporale (giorni)**).
3. Faccia clic su **Verificare la connessione**. Finché esistono associazioni,
   non è possibile inserire un’altra istanza.

L’account della chiave necessita in ogni progetto associato di un ruolo che
possa vedere i ticket e – per l’importazione dei tempi – il tempo impiegato.
Imposti la visibilità di ticket e registrazioni di tempo di questo ruolo su
**tutti**; altrimenti WorkDiary acquisisce solo ciò che l’account ha creato o
che gli è assegnato.

## Associare progetti e controllare l’anteprima

1. **Associare un progetto** sceglie un progetto Redmine e il progetto WorkDiary.
   I sottoprogetti non sono inclusi, né per i ticket né per i tempi; li
   associ separatamente se necessario.
2. L’**Anteprima** mostra i ticket aperti e cosa farebbe la prima
   sincronizzazione; non salva nulla. Colleghi attività esistenti tramite
   **Collegare l’omonima** o **Collegare un’attività esistente**.
3. **Confermare l’importazione** abilita l’associazione. Solo allora vengono
   create attività dai ticket aperti; mai da quelli chiusi.

## Cosa viene importato

- **Titolo**, **descrizione** come testo e **scadenza**.
- **Stato**: un ticket con stato chiuso (contrassegnato come chiuso in Redmine)
  diventa **Completato**, altrimenti **Aperto**; i nomi degli stati non contano.
  Un **In corso** locale resta.
- Nella finestra dell’attività, **Apri in Redmine** porta al ticket.

## Registrazioni di tempo

1. Nella sezione **Registrazioni di tempo** attivi **Importare i tempi** per
   ogni associazione.
2. Redmine conosce solo giorno e ore. WorkDiary registra quindi una durata senza
   orario nel progetto di destinazione; se la registrazione appartiene a un
   ticket collegato, appartiene all’attività di quel ticket.
3. I tempi vengono registrati solo per utenti Redmine associati. In
   **Associazione utenti** compaiono gli utenti Redmine con registrazioni in
   attesa; scelga l’utente corrispondente e faccia clic su **Associare**. La
   prossima sincronizzazione registra quelle in attesa.
4. Ogni esecuzione legge l’intera finestra temporale. Le ore modificate vengono
   aggiornate, le registrazioni eliminate in Redmine spariscono anche qui – tranne
   i tempi già fatturati: in quel caso la posta in arrivo segnala un conflitto e
   il tempo resta invariato.

## Quando avviene la sincronizzazione

Ogni 15 minuti WorkDiary recupera i ticket modificati e le registrazioni di tempo
della finestra, una volta al giorno tutti i ticket. Modifichi la cadenza in **Job
pianificati**; **Sincronizzare ora** nel menu ⋯ avvia subito.

## Conflitti, casi da verificare e scollegamento

Modifiche diverse su entrambi i lati generano un conflitto nella posta in arrivo;
nulla viene riscritto in Redmine. Ticket non più visibili, dati gestiti altrove e
stati sconosciuti vengono segnalati come casi da verificare. **Scollegare** ferma
la sincronizzazione e mette in pausa le associazioni; attività e tempi importati
restano. Revochi la chiave in Redmine o la elimini in **Credenziali**.

## Errori frequenti

- La verifica fallisce con 401 o 403: la chiave API è errata o il servizio web
  REST è disattivato in Redmine.
- I tempi non compaiono: **Importare i tempi** è disattivato, l’utente Redmine
  non è ancora associato o la registrazione è fuori dalla finestra temporale.
- La sincronizzazione si interrompe con 401: Redmine tratta una chiave non
  valida come un visitatore non autenticato e mostra solo i dati pubblici.
  Perciò WorkDiary verifica l’account prima di ogni esecuzione e con una chiave
  non valida non legge nulla.
