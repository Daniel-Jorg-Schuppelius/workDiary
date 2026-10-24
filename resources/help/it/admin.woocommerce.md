---
title: "Importazione degli ordini WooCommerce"
topic: admin.woocommerce
version: 1
keywords:
    - WooCommerce
    - WordPress
    - negozio
    - importare ordini
    - negozio online
    - chiave client
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

La pagina **WooCommerce** legge gli ordini del Suo negozio WooCommerce e li
mostra in WorkDiary: numero, data, acquirente, stato e importo. Gli acquirenti
vengono assegnati a clienti esistenti, così i loro ordini compaiono anche sul
cliente. WorkDiary non scrive nulla nel negozio. Trova la pagina nel menu di
sistema (l’ingranaggio **Sistema**) sotto **Plugin** → **WooCommerce**, non appena
il plugin è attivo.

## Configurare l’accesso

1. In WooCommerce crei una chiave con permesso di **Lettura** in
   **Impostazioni** → **Avanzate** → **API REST**.
2. Sulla pagina apra **Credenziali** nel menu ⋯ e inserisca l’**indirizzo del
   negozio** (con https, senza /wp-json), la **chiave client** e il **segreto
   client**. WooCommerce accetta le chiavi solo tramite https. Se il negozio
   funziona nella propria rete, attivi anche **Consentire indirizzi privati**; ha
   effetto solo se il gestore lo consente.
3. Clicchi su **Verificare la connessione**. WorkDiary legge un ordine di prova e
   mostra il nome del negozio.

Finché sono rispecchiati ordini, non è possibile inserire un altro negozio.

## Anteprima e conferma dell’importazione

**Verificare l’anteprima** mostra gli ordini del periodo della prima
sincronizzazione (30 giorni di default, modificabile in **Credenziali**) e se il
loro acquirente è già assegnato a un cliente. L’anteprima non salva nulla.
**Confermare l’importazione** abilita la sincronizzazione.

I nuovi ordini annullati, falliti o rimborsati vengono acquisiti solo se
**Acquisire ordini annullati e rimborsati** è attivo. Gli ordini già acquisiti
vengono sempre aggiornati.

## Cosa viene acquisito

- Numero d’ordine, data, **stato** dallo stato fisso di WooCommerce (aperto, in
  lavorazione, completato, annullato, rimborsato) e importo con valuta.
- I rimborsi compaiono sotto l’importo («di cui rimborsato»); il totale
  dell’ordine resta quello del negozio.
- Una volta al giorno la sincronizzazione verifica se gli ordini acquisiti
  esistono ancora nel negozio. Quelli eliminati risultano **Eliminato nel
  negozio** e restano in WorkDiary; se uno ricompare, il contrassegno scompare.
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

- La verifica segnala `https_required`: l’indirizzo del negozio non inizia con
  https.
- La verifica fallisce con 401: chiavi errate o senza permesso di lettura.
- La verifica segnala un indirizzo interno: l’indirizzo punta alla propria rete,
  ma **Consentire indirizzi privati** è disattivato o non consentito dal gestore.
- La verifica fallisce con 401 anche se le chiavi sono corrette: dietro un proxy
  WordPress non riconosce la connessione https oppure il server non inoltra
  l’intestazione Authorization.
- La verifica segnala 404: in **Impostazioni** → **Permalink** è scelto
  «Semplice»; l’API REST richiede permalink leggibili.
