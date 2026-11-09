---
title: "Migrazione legacy"
topic: admin.legacy-migration
version: 2
keywords:
    - vecchio sistema
    - sistema precedente
    - dati storici
    - recupero dati
    - importare dati storici
    - migrazione dati
    - passaggio di sistema
audience:
    - admin
related:
    - admin.import
    - admin.data-transfer
    - admin.handbook
---

La migrazione legacy trasferisce i dati dal sistema precedente a
WorkDiary e mostra lo stato di ripresa per area dati (**utenti**, voci
del diario, turni di reperibilità, interventi di emergenza).
Presupposto è una connessione database configurata al vecchio sistema;
se non è raggiungibile, l'area appare come «non configurata».
L'importazione si avvia per area ed esegue in background il comando
`legacy:import`; i record già importati sono collegati tramite un
identificativo legacy, quindi le esecuzioni ripetute non creano
duplicati. La scrittura dipende dalla configurazione
(`legacy_write_enabled`); la pagina è riservata alla gestione della
piattaforma, perché il database del vecchio sistema appartiene all'intera
installazione.

Il **portale assistenza** viene trasferito in un'**organizzazione di
destinazione** a scelta: gli accessi dei clienti vengono abbinati ai
clienti esistenti per ragione sociale o creati come clienti; ordini e
archivio diventano ticket di assistenza con numero KP-… e mantengono
appuntamento, tecnico, risposta e nota interna. Le password non vengono
trasferite; l'accesso al portale clienti si concede nel nuovo sistema
tramite invito. Una nuova esecuzione aggiorna i ticket già trasferiti. Gli
orari del vecchio sistema sono espressi in ora locale e vengono
convertiti nell'importazione.
