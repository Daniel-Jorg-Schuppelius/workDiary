---
title: "Importazione degli ordini Shopware"
topic: admin.shopware
version: 1
keywords:
    - Shopware
    - Shopware 6
    - negozio
    - importare ordini
    - negozio online
    - integrazione
    - assegnare acquirenti
    - caso da chiarire
audience:
    - admin
related:
    - admin.plugins
    - admin.integration-inbox
    - admin.billbee
    - privacy.overview
    - admin.scheduler
---

La pagina **Shopware** legge gli ordini del Suo negozio Shopware 6 e li
mostra in WorkDiary: numero, data, acquirente, stato e importo. Gli acquirenti
vengono assegnati a clienti esistenti, così i loro ordini compaiono anche sul
cliente. WorkDiary non scrive nulla nel negozio. Trova la pagina nel menu di
sistema (l’ingranaggio **Sistema**) sotto **Plugin** → **Shopware**, non appena
il plugin è attivo.

## Configurare l’accesso

1. In Shopware crei un’integrazione in **Impostazioni** → **Sistema** →
   **Integrazioni**. Serve il permesso di
   lettura su ordini, clienti dell’ordine, clienti, righe, transazioni di
   pagamento, valute e stati, oppure l’opzione **Amministratore**.
2. Sulla pagina apra **Credenziali** nel menu ⋯ e inserisca l’**indirizzo del
   negozio** (senza /api), l’**ID chiave di accesso** e la **chiave di accesso
   segreta** dell’integrazione. Se il negozio funziona nella propria rete, attivi
   anche **Consentire indirizzi privati**; ha effetto solo se il gestore lo
   consente.
3. Clicchi su **Verificare la connessione**. WorkDiary accede, legge un ordine di
   prova e mostra l’indirizzo e la versione di Shopware.

Finché sono rispecchiati ordini, non è possibile inserire un altro negozio.

## Anteprima e conferma dell’importazione

**Verificare l’anteprima** mostra gli ordini del periodo della prima
sincronizzazione (30 giorni di default, modificabile in **Credenziali**) e se il
loro acquirente è già assegnato a un cliente. L’anteprima non salva nulla.
**Confermare l’importazione** abilita la sincronizzazione.

I nuovi ordini annullati vengono acquisiti solo se
**Acquisire ordini annullati e rimborsati** è attivo. Gli ordini già acquisiti
vengono sempre aggiornati.

## Cosa viene acquisito

- Numero d’ordine, data, **stato** dallo stato tecnico dell’ordine in Shopware
  (aperto, in lavorazione, completato, annullato) e importo con valuta. Shopware
  registra i rimborsi nello stato del pagamento: se l’ultimo pagamento è
  rimborsato, l’ordine risulta **rimborsato**; un rimborso parziale non cambia lo
  stato.
- Acquirente con nome, e-mail e azienda, le righe come testo.
- Il numero d’ordine porta alla gestione degli ordini del negozio.

## Assegnare i clienti e casi da chiarire

Se l’acquirente è già assegnato a un cliente o chiaramente riconoscibile,
WorkDiary collega l’ordine a quel cliente. Altrimenti nasce un **caso da
chiarire** nella inbox di integrazione: lì assegni l’acquirente a un cliente, e
la sincronizzazione successiva collega anche i suoi altri ordini. L’importazione
non crea mai nuovi clienti. Gli ordini degli ospiti vengono riconosciuti
dall’indirizzo e-mail.

## Quando avviene la sincronizzazione

Ogni 15 minuti WorkDiary recupera gli ordini nuovi o modificati dall’ultima
esecuzione e una volta al giorno l’intero periodo. Modifichi la cadenza in **Job
pianificati**; **Sincronizzare ora** avvia subito. Un’esecuzione interrotta
risulta **incompleta**; la successiva riprende dall’ultimo stato completo.

## Scollegamento e conservazione

**Scollegare** ferma la sincronizzazione; ordini e assegnazioni dei clienti
restano. La regola di conservazione **Dati acquirenti degli ordini marketplace**
rimuove nome, e-mail e azienda degli acquirenti dopo il periodo di conservazione
commerciale; importi e collegamento al cliente restano.

## Errori frequenti

- La verifica fallisce con 401: l’ID chiave di accesso o la chiave segreta sono
  errati.
- La verifica fallisce con 403: all’integrazione manca il permesso di lettura
  sugli ordini.
- La verifica segnala un indirizzo interno: l’indirizzo punta alla propria rete,
  ma **Consentire indirizzi privati** è disattivato o non consentito dal gestore.
- La verifica o un’esecuzione fallisce con 403 anche se gli ordini sono
  leggibili: all’integrazione manca uno dei permessi di lettura indicati sopra,
  ad esempio sulle transazioni di pagamento.
