---
title: "Collegare DATEV Online"
topic: admin.datev-online
version: 1
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
---

L'integrazione trasferisce i lotti contabili chiusi e le immagini dei
giustificativi direttamente a DATEV Unternehmen online, senza scaricare e
caricare file a mano.

**Requisiti:** Una registrazione dell'app presso DATEV (ID client e
segreto client dal portale sviluppatori DATEV) e un utente DATEV con
accesso al mandante. Inserisca le credenziali nelle impostazioni del
plugin e registri nell'app DATEV l'indirizzo di reindirizzamento indicato
lì. Finché DATEV non ha approvato l'uso in produzione, lasci attivo «Usa
sandbox».

**Accedere e scegliere il mandante:** «Accedi con DATEV» porta all'accesso
DATEV e ritorna. Poi scelga il mandante (numero consulente-numero
mandante) dall'elenco dei mandanti abilitati per Lei.

**Lotti contabili:** I lotti chiusi dell'esportazione DATEV si possono
consegnare come importazione EXTF con «Trasferisci a DATEV». I numeri di
consulente e di mandante nel lotto devono corrispondere al mandante
connesso. DATEV elabora l'importazione in background; l'esecuzione
notturna verifica il risultato, «Verifica stato dell'importazione» lo fa
subito. Un'importazione non riuscita può essere trasferita di nuovo dopo
la correzione.

**Immagini dei giustificativi:** Se attivato, l'esecuzione notturna
trasferisce le fatture emesse come «Rechnungsausgang» e quelle ricevute
come «Rechnungseingang», ogni giustificativo esattamente una volta e solo
dalla data impostata (predefinita: il giorno dell'accesso). «Trasferisci
ora» avvia subito l'esecuzione.

**Disconnettere:** La connessione si può interrompere in qualsiasi
momento; i dati già trasferiti restano in DATEV.
