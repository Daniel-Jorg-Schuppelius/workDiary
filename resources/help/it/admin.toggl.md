---
title: "Importazione Toggl"
topic: admin.toggl
version: 2
audience:
    - admin
related:
    - admin.plugins
    - admin.import
    - admin.openproject
---

L'importazione Toggl trasferisce le registrazioni di tempo da Toggl
Track a WorkDiary. Per impostazione predefinita legge soltanto; in
opzione si possono riscrivere le correzioni e trasferire i tempi
registrati localmente (impostazioni del plugin). Due vie:
**import API** con token e intervallo di date, oppure **import da
file** con report dettagliato CSV o archivio di export del workspace.
I clienti/progetti Toggl senza corrispondenza automatica si raccolgono
nella posta in arrivo, dove li assegni a clienti/progetti esistenti,
ne crei di nuovi o li scarti; le assegnazioni salvate valgono per gli
import futuri e si possono modificare o eliminare. Import ripetuti
dello stesso periodo possono generare duplicati se i dati di origine
sono cambiati; lo scarto delle voci è definitivo.

Assegnazione utenti (MVP-509): ogni voce Toggl viene assegnata
all'utente WorkDiary corrispondente tramite l'e-mail utente del
workspace: prima le assegnazioni salvate («Gestisci associazioni»), poi
l'uguaglianza dell'e-mail. Gli utenti Toggl sconosciuti o non
consultabili non vengono mai registrati in silenzio sull'utente
principale: restano come caso aperto nella posta di assegnazione, dove
scegli l'utente; la scelta viene memorizzata. Solo nella modalità
utente singolo attivata espressamente (impostazione del plugin) le voci
senza segnale utente vengono registrate sull'utente predefinito. Le
vecchie importazioni assegnate male si riparano con
`toggl:repair-entry-users` (prima simulazione, scrivere con `--apply`);
i tempi fatturati o firmati non vengono mai modificati automaticamente.
