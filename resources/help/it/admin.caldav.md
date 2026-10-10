---
title: "Calendario CalDAV"
topic: admin.caldav
version: 3
keywords:
    - CalDAV
    - calendario Nextcloud
    - calendario ownCloud
    - pubblicare appuntamenti
    - abbonarsi al calendario
    - turni nel calendario
    - ferie nel calendario
    - sincronizzazione bidirezionale
    - password app
    - percorso calendario
    - indirizzi privati
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - events.manage
    - planning.shifts
    - absences.manage
    - admin.import
    - admin.notification-rules
---

La pagina **CalDAV** pubblica gli appuntamenti di WorkDiary in un calendario
CalDAV esterno, per esempio in Nextcloud o ownCloud – senza account Microsoft
o Google. Se lo desidera, si aggiungono turni e ferie, e le modifiche fatte
nel calendario possono tornare come proposte. WorkDiary resta il sistema di
riferimento: gli appuntamenti annullati ed eliminati vi scompaiono e le esecuzioni ripetute
non creano mai duplicati. Trova la pagina nel menu di sistema (l’ingranaggio
**Sistema** nella barra in alto) sotto **Plugin** → **CalDAV**, non appena il
plugin è attivo.

## Prerequisiti

- Il plugin è attivato per la Sua organizzazione: **Sistema** → **Plugin** →
  **Plugin**, poi **Attiva** sulla voce CalDAV. La connessione vera e propria
  si configura nella pagina **CalDAV**, non nella finestra del plugin.
- La pagina è riservata agli amministratori.
- Servono un calendario sul server CalDAV, un account con diritto di
  scrittura su di esso e una password per app (Nextcloud: Impostazioni →
  Sicurezza → Password app).
- Il server deve essere raggiungibile pubblicamente. Se si trova nella Sua
  rete, attivi **Consenti indirizzi privati/interni** (vedi sotto).
- Per ogni organizzazione esiste esattamente una connessione CalDAV.

## Configurare la connessione

Nella sezione **Connessione** compila:

- **Etichetta**: un nome a scelta; compare anche nella scelta della fonte di
  importazione.
- **URL base DAV**: l’indirizzo DAV del server senza percorso del calendario,
  per Nextcloud …/remote.php/dav. Deve iniziare con http:// o https://.
- **Nome utente** e **Password app**: la password è obbligatoria al primo
  salvataggio e viene memorizzata cifrata; in seguito un campo vuoto mantiene
  la password memorizzata.
- **Percorso calendario (collection)**: il percorso del calendario relativo
  all’URL base, per esempio calendars/team/turni. Se incolla un indirizzo
  completo copiato da Nextcloud, WorkDiary lo accorcia da sé, purché inizi con
  l’URL base.
- **Consenti indirizzi privati/interni**: lo attivi solo se il server
  CalDAV si trova nella Sua rete (per esempio 192.168.x.x). Senza questo
  interruttore WorkDiary rifiuta gli indirizzi interni già al salvataggio.
  L’attivazione viene registrata. Se il gestore della Sua installazione ha
  bloccato questa autorizzazione, l’interruttore non ha effetto.
- **Attivo**: attiva o disattiva la connessione.
- **Bidirezionale: importa le modifiche esterne come proposte**: vedi sotto.
- **Contenuti pubblicati**: **Eventi** e/o **Turni e ferie**. Senza
  selezione valgono solo gli eventi.

**Salva** applica i dati. Quando la connessione è attiva, la pagina ne mostra
lo stato (per esempio **Stato ok**) e **Verifica connessione**.

## Che cosa viene pubblicato

- **Eventi:** gli eventi della Sua organizzazione che iniziano tra 30 giorni
  nel passato e 180 giorni nel futuro. WorkDiary rimuove dal calendario gli
  eventi annullati ed eliminati.
- **Turni e ferie:** i turni pubblicati o confermati con orari a partire da
  due mesi nel passato, e le ferie approvate terminate al massimo un anno fa.
  I turni in bozza, senza orari o annullati e le ferie non più approvate
  vengono rimossi o non vengono proprio creati.
- Le regole di notifica con il canale **Calendario** depositano anch’esse le
  notifiche di tipo appuntamento nelle connessioni con **Eventi**.
- Le voci modificate vengono aggiornate; quelle invariate non vengono inviate
  di nuovo.

## Quando avviene la pubblicazione

- Una volta al giorno (predefinito alle 04:35) WorkDiary sincronizza il
  calendario. La frequenza si modifica sotto **Attività pianificate**.
- **Pubblica ora** avvia subito la sincronizzazione in background – per
  esempio dopo la configurazione o dopo molte modifiche.
- Gli appuntamenti nuovi o modificati compaiono quindi nel calendario solo
  dopo l’esecuzione successiva. Solo le notifiche tramite il canale
  **Calendario** partono subito.

## Bidirezionale: modifiche dal calendario

La reimportazione resta disattivata finché non attiva **Bidirezionale:
importa le modifiche esterne come proposte**. Allora WorkDiary legge il
calendario ogni ora, in una finestra da 30 giorni indietro a 180 giorni
avanti:

- Le nuove voci nel calendario diventano proposte nell’Inbox di
  riconciliazione. Nulla viene creato senza chiedere.
- Gli appuntamenti ricorrenti compaiono come gruppo con le loro singole
  occorrenze nella finestra; WorkDiary tiene conto delle occorrenze spostate
  e annullate. Il gruppo può essere creato come appuntamenti o scartato in
  un colpo solo.
- Le modifiche esterne ad appuntamenti pubblicati compaiono come conflitto,
  le voci eliminate nel calendario come caso «Appuntamento eliminato nel
  calendario CalDAV». WorkDiary non elimina nulla da sé in questo caso.

L’Inbox di riconciliazione è accessibile agli amministratori e alla
contabilità.

## Il calendario come fonte di importazione

Nell’import CSV (**Trasferimento dati** → **Importazione**) può scegliere come
fonte per timbrature e tempi di progetto il calendario CalDAV invece di un
file. Vengono proposte le connessioni attive con la loro **Etichetta**;
WorkDiary legge allora le voci del periodo scelto.

## Disconnettere

**Disconnetti** disattiva la connessione. Le voci già pubblicate restano nel
calendario. Per riattivarla imposti **Attivo** e salvi.

La sincronizzazione successiva rimuove dal calendario eventi, turni e ferie
eliminati, così come quelli annullati. Le voci che escono soltanto dalla
finestra temporale vi restano.

## Errori frequenti

- «L'URL base deve iniziare con http:// o https://.»: inserisca l’indirizzo
  completo.
- «L'URL del calendario non si trova sotto l'URL base.»: il link incollato non
  corrisponde all’URL base DAV. Indichi il percorso relativo all’URL base.
- «Una nuova connessione richiede una password app.»: al primo salvataggio
  manca la password.
- **Stato difettoso** con «Server CalDAV non raggiungibile o credenziali non
  valide.»: controlli indirizzo, percorso del calendario, nome utente e
  password app. Un errore CalDAV con RuntimeException indica spesso un
  indirizzo di una rete interna senza autorizzazione.
- «L'URL di base punta a un indirizzo privato/interno.»: se il server si
  trova nella Sua rete, attivi **Consenti indirizzi privati/interni**. Se il
  gestore ha bloccato questa autorizzazione, il server ha bisogno di un
  indirizzo raggiungibile pubblicamente.
- «Nessuna connessione CalDAV attiva.» con **Pubblica ora**: la connessione è
  disattivata o incompleta.
- Nel calendario mancano i turni: in **Contenuti pubblicati** non è spuntato
  **Turni e ferie**, oppure i turni non sono ancora pubblicati.
