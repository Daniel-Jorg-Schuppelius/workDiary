---
title: "Importazione degli ordini PrestaShop"
topic: admin.prestashop
version: 1
keywords:
    - PrestaShop
    - negozio
    - importare ordini
    - negozio online
    - webservice
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

La pagina **PrestaShop** legge gli ordini del Suo negozio PrestaShop e li
mostra in WorkDiary: numero, data, acquirente, stato e importo. Gli acquirenti
vengono assegnati a clienti esistenti, così i loro ordini compaiono anche sul
cliente. WorkDiary non scrive nulla nel negozio. Trova la pagina nel menu di
sistema (l’ingranaggio **Sistema**) sotto **Plugin** → **PrestaShop**, non appena
il plugin è attivo.

## Configurare l’accesso

1. In PrestaShop attivi il webservice in **Parametri avanzati** → **Webservice**
   e crei una chiave. Serve il permesso di lettura (GET) su orders, customers,
   currencies e order_states, per i rimborsi anche su order_slip e per il fuso
   orario su configurations.
2. Sulla pagina apra **Credenziali** nel menu ⋯ e inserisca l’**indirizzo del
   negozio** (senza /api) e la **chiave del webservice**. PrestaShop fornisce gli
   orari senza fuso orario: se il negozio non funziona nel fuso orario della Sua
   organizzazione, inserisca il **Fuso orario del negozio** (ad es.
   Europe/Vienna). Se il negozio funziona nella propria rete, attivi anche
   **Consentire indirizzi privati**; ha effetto solo se il gestore lo consente.
3. Clicchi su **Verificare la connessione**. WorkDiary legge un ordine di prova e
   mostra l’indirizzo.

Finché sono rispecchiati ordini, non è possibile inserire un altro negozio.

## Anteprima e conferma dell’importazione

**Verificare l’anteprima** mostra gli ordini del periodo della prima
sincronizzazione (30 giorni di default, modificabile in **Credenziali**) e se il
loro acquirente è già assegnato a un cliente. L’anteprima non salva nulla.
**Confermare l’importazione** abilita la sincronizzazione.

I nuovi ordini annullati o rimborsati vengono acquisiti solo se
**Acquisire ordini annullati e rimborsati** è attivo. Gli ordini già acquisiti
vengono sempre aggiornati.

## Cosa viene acquisito

- Riferimento, data, **stato** e importo con valuta. Lo stato segue le
  caratteristiche fisse dello stato d’ordine di PrestaShop, non il suo nome:
  annullato ed errore di pagamento → annullato, rimborso → rimborsato,
  spedito (anche consegnato) → completato, pagato → in lavorazione, altrimenti aperto.
- La sola bolla di consegna (stato standard «Preparazione in corso») non conta
  come spedito. Con più negozi WorkDiary legge gli ordini di tutti i negozi e
  recupera una per una le valute degli altri negozi.
- I rimborsi provengono dalle note di credito (order_slip) e compaiono sotto
  l’importo. Se la chiave non può leggere order_slip, l’importo rimborsato resta
  vuoto.
- Se la chiave può leggere configurations, **Verificare la connessione** adotta
  il fuso orario del negozio; un **Fuso orario del negozio** inserito ha la
  precedenza.
- Una volta al giorno la sincronizzazione verifica se gli ordini acquisiti
  esistono ancora nel negozio. Quelli eliminati risultano **Eliminato nel
  negozio** e restano in WorkDiary; se uno ricompare, il contrassegno scompare.
- Acquirente con nome, e-mail e azienda, le righe come testo.
- Non c’è un collegamento all’amministrazione del negozio: la sua cartella è
  segreta per ogni negozio e i suoi link valgono solo con un accesso.

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

- La verifica fallisce con 401: la chiave è errata o il webservice è disattivato.
- La verifica fallisce con 403 o un’esecuzione resta **incompleta**: alla chiave
  manca il permesso di lettura su una delle quattro risorse.
- Gli orari sono sfasati di ore: il **Fuso orario del negozio** manca o è errato.
- La verifica fallisce con 401 anche se la chiave è corretta: il server web non
  inoltra l’intestazione Authorization (Apache: CGIPassAuth) oppure la riscrittura
  degli URL per /api è disattivata.
