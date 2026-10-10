---
title: "Integrazione OpenProject"
topic: admin.openproject
version: 4
keywords:
    - gestione progetti
    - pacchetti di lavoro
    - work package
    - registrazioni ore
    - importare ore
    - esportare ore
    - sincronizzazione tempi
    - sincronizzazione progetti
    - mappature
    - OpenProject ospitato in proprio
    - consenti indirizzi privati
audience:
    - admin
related:
    - admin.plugins
    - admin.toggl
    - admin.import
    - admin.integration-inbox
---

L'integrazione OpenProject collega WorkDiary a OpenProject in modo
**bidirezionale**: i tempi vengono importati **e** i tempi registrati
possono essere riportati su OpenProject. Credenziali e opzioni si
impostano nelle impostazioni del plugin (tra cui **URL dell'istanza**,
**Token API** e **Finestra di sincronizzazione (giorni)**).

WorkDiary rifiuta un’istanza OpenProject ospitata in proprio nella Sua rete
(per esempio 192.168.x.x) finché non attiva **Consenti indirizzi privati**
nelle impostazioni del plugin. La modifica viene registrata. Se il gestore
della Sua installazione ha bloccato questa autorizzazione, l’interruttore non
ha effetto; l’istanza ha allora bisogno di un indirizzo raggiungibile
pubblicamente.

Sincronizzare (pagina **Sincronizza OpenProject**):

- **Sincronizza struttura + tempi** con **Sincronizza ora**: allinea
  progetti e work package e poi importa le voci di tempo nella finestra
  impostata.
- **Confronta solo la struttura** con **Allinea struttura**: associa
  progetti, work package e utenti di OpenProject ai progetti, alle
  attività e agli utenti di WorkDiary. Se **Crea progetti/attività
  mancanti** è attivo, l'allineamento crea automaticamente le voci
  mancanti.

Voci di tempo non assegnate:

- Ciò che non può essere assegnato automaticamente finisce nella
  **Inbox di riconciliazione** centrale; **Alla inbox di
  riconciliazione** vi conduce e mostra il numero delle voci aperte.
- Lì assegna un gruppo a un cliente e a un progetto (oppure ne indica
  uno nuovo) e lo registra, oppure lo scarta. Le importazioni future
  vengono assegnate automaticamente in base alle associazioni salvate.

Riporto (**Riporta i tempi**):

- Riporta su OpenProject i tempi non esportati dei progetti associati
  a un progetto OpenProject; le attività vengono registrate come work
  package, se associate. **Periodo (facoltativo)** delimita
  l'esecuzione (vuoto = tutte le voci aperte), **Riporta ora** la avvia
  e **Ultimo riporto** ne mostra l'esito. Le voci già
  riportate vengono saltate.
- Prerequisito: nelle impostazioni del plugin deve essere indicato
  l'**ID attività OpenProject (registrazione)** – altrimenti il riporto
  non è possibile.

Associazioni (**Gestisci assegnazioni**):

- La pagina **OpenProject – associazioni** elenca i collegamenti
  memorizzati per progetti, work package e utenti. Con **Riallocare**
  modifica la destinazione, con **Rimuovi** elimina un'associazione.

Rischi: il riporto modifica dati nel sistema OpenProject collegato.
Prima della prima esecuzione verifichi le associazioni e l'ID attività,
per evitare registrazioni errate.
