---
title: "Importazione Toggl"
topic: admin.toggl
version: 4
keywords:
    - Toggl Track
    - importare tempi
    - importare registrazioni orarie
    - CSV Toggl
    - API Toggl
    - export workspace
    - migrazione da Toggl
    - mappature
    - abbinamento utenti
    - report dettagliato
    - nuovo Toggl
    - Toggl 2.0
    - Toggl Focus
    - sincronizzazione in tempo reale
audience:
    - admin
related:
    - admin.plugins
    - admin.import
    - admin.openproject
---

L'importazione Toggl trasferisce le registrazioni di tempo da Toggl
Track o dal nuovo Toggl a WorkDiary. Per impostazione predefinita legge soltanto; in
opzione si possono riscrivere le correzioni e trasferire i tempi
registrati localmente (impostazioni del plugin). Due vie:
**import API** con token e intervallo di date, oppure **import da
file** con report dettagliato CSV o archivio di export del workspace.
I clienti/progetti Toggl senza corrispondenza automatica si raccolgono
nella posta in arrivo, dove li assegna a clienti/progetti esistenti,
ne crea di nuovi o li scarta; le assegnazioni salvate valgono per gli
import futuri e si possono modificare o eliminare. Import ripetuti
dello stesso periodo possono generare duplicati se i dati di origine
sono cambiati; lo scarto delle voci è definitivo.

Toggl Track o il nuovo Toggl:

- Alla voce **Prodotto Toggl** nelle impostazioni del plugin sceglie da
  dove provengono i tempi: **Toggl Track** o il **nuovo Toggl** (già Toggl
  2.0 o Toggl Focus). Lettura, riscrittura e trasferimento riguardano solo
  il prodotto scelto.
- Per il nuovo Toggl inserisca la sua chiave API (inizia con
  `toggl_sk_`). L'ID organizzazione e l'ID spazio di lavoro sono
  facoltativi; senza di essi il plugin individua gli spazi di lavoro
  tramite il Suo account Toggl. Toggl consente una sola chiave attiva per
  utente: crearne una nuova invalida la precedente.
- Se usa la sincronizzazione in tempo reale tra Track e il nuovo Toggl,
  scelga comunque una sola fonte: l'allineamento tra i due prodotti lo
  gestisce Toggl stesso.
- Dopo un cambio, l'importazione successiva assegna le voci già importate
  alla nuova fonte (stesso utente, inizio e fine) invece di crearle di
  nuovo; i casi aperti della posta di assegnazione seguono. Ciò vale per
  il periodo del recupero. Le voci più vecchie mantengono il collegamento
  attuale e le loro correzioni non vengono più inviate a Toggl. Se
  necessario, importi una volta con un periodo più lungo
  (`toggl:import --days=365`).
- Il rilevamento delle cancellazioni considera solo le voci della fonte
  scelta.

Assegnazione utenti (MVP-509): ogni voce Toggl viene assegnata
all'utente WorkDiary corrispondente tramite l'e-mail utente del
workspace: prima le assegnazioni salvate («Gestisci associazioni»), poi
l'uguaglianza dell'e-mail. Gli utenti Toggl sconosciuti o non
consultabili non vengono mai registrati in silenzio sull'utente
principale: restano come caso aperto nella posta di assegnazione, dove
sceglie l'utente; la scelta viene memorizzata. Solo nella modalità
utente singolo attivata espressamente (impostazione del plugin) le voci
senza segnale utente vengono registrate sull'utente predefinito. Le
vecchie importazioni assegnate male si riparano con
`toggl:repair-entry-users` (prima simulazione, scrivere con `--apply`);
i tempi fatturati o firmati non vengono mai modificati automaticamente.

Per l'importazione una tantum del workspace (cartella/ZIP o API) scelga
esplicitamente l'assegnazione degli utenti: assegnare solo a utenti
esistenti (le voci sconosciute restano visibilmente non registrate e
vengono elencate per e-mail), creare gli utenti mancanti per e-mail,
oppure modalità utente singolo (tutto sull'utente predefinito
configurato — chiaramente indicato in anteprima e risultato). I singoli
indirizzi Toggl possono inoltre essere assegnati esplicitamente;
l'importazione è idempotente e può semplicemente essere rieseguita una
volta curate le assegnazioni.

Webhook (facoltativo):

- Per ricevere più rapidamente le nuove voci, la pagina di importazione
  indica nella sezione **Webhook (facoltativo)** l'indirizzo per una
  sottoscrizione webhook di Toggl. Crei la sottoscrizione per lo spazio di
  lavoro in Toggl e inserisca il segreto assegnato nelle impostazioni del
  plugin alla voce **Segreto del webhook**; lì deve essere impostato anche
  l'**ID spazio di lavoro**.
- Il webhook avvia solo la stessa importazione eseguita dal recupero
  orario. Se manca, il recupero rimedia.
- Il nuovo Toggl non offre webhook. Con la sincronizzazione in tempo reale
  attiva, una sottoscrizione in Toggl Track può comunque anticipare il
  recupero; i dati continuano a essere letti dal nuovo Toggl.
- L'utente predefinito della modalità utente singolo si sceglie nelle
  impostazioni del plugin alla voce **Registra i tempi per l’utente**,
  dall'elenco degli utenti.
