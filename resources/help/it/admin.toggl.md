---
title: "Importazione Toggl"
topic: admin.toggl
version: 1
audience:
    - admin
related:
    - admin.plugins
    - admin.import
    - admin.openproject
---

L'importazione Toggl trasferisce le registrazioni di tempo da Toggl
Track a WorkDiary in modo unidirezionale (sola lettura). Due vie:
**import API** con token e intervallo di date, oppure **import da
file** con report dettagliato CSV o archivio di export del workspace.
I clienti/progetti Toggl senza corrispondenza automatica si raccolgono
nella posta in arrivo, dove li assegni a clienti/progetti esistenti,
ne crei di nuovi o li scarti; le assegnazioni salvate valgono per gli
import futuri e si possono modificare o eliminare. Import ripetuti
dello stesso periodo possono generare duplicati se i dati di origine
sono cambiati; lo scarto delle voci è definitivo.
