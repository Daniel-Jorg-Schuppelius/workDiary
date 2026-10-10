---
title: "Connessioni di spedizione DHL, UPS e FedEx"
topic: admin.shipping-carriers
version: 2
keywords:
    - spedizione
    - DHL
    - UPS
    - FedEx
    - etichetta di spedizione
    - stampare etichetta pacco
    - etichetta di reso
    - numero di tracciamento
    - corriere pacchi
    - portale clienti business
    - sandbox
    - corriere
    - tracciamento spedizioni
    - annullare spedizione
audience:
    - admin
modules:
    - module.versand
related:
    - admin.integrations
    - admin.plugins
    - manufacturing.orders
    - claims.overview
    - admin.organization-settings
    - admin.operations
---

La pagina **Spedizione e logistica**, nel menu alla voce **Spedizione**,
memorizza le credenziali dei corrieri DHL Paket, UPS e FedEx. Con una
connessione attiva crea direttamente in WorkDiary etichette di spedizione per
le consegne ed etichette di reso per i resi e segue le Sue spedizioni. Esiste una connessione per
corriere e per organizzazione; password e chiavi vengono salvate cifrate.

## Prerequisiti

- La Sua licenza comprende il modulo Spedizione e logistica.
- Il plugin del corriere è attivato in **Plugin**: **DHL Paket**, **UPS** o
  **FedEx**. Successivamente compare la voce **Spedizione** nel menu di sistema
  (icona a ingranaggio **Sistema**), nel gruppo **Plugin**.
- Dispone di un accesso clienti business presso il corriere:
  - **DHL:** utente e password del portale clienti business DHL, una chiave API
    abilitata da DHL (dhl-api-key) e il numero di fatturazione. Per le
    etichette di reso serve inoltre l'ID destinatario resi, che crea nel
    portale clienti business.
  - **UPS:** ID client e secret client di un'app sviluppatore UPS e il Suo
    numero di conto UPS (numero spedizioniere).
  - **FedEx:** ID client e secret client di un'app sviluppatore FedEx e il Suo
    numero di conto FedEx.
- Per UPS e FedEx WorkDiary prende l'indirizzo del mittente dalle impostazioni
  dell'organizzazione, sezione **Fattura elettronica (XRechnung)**: **Nome
  azienda** (se vuoto, il nome dell'organizzazione), **Via e numero civico**,
  **CAP** e **Città**. Se manca uno di questi dati, l'etichetta non riesce.
- La pagina è riservata agli amministratori della Sua organizzazione.

## Creare o modificare una connessione

Una nuova connessione si crea nel modulo **Aggiungi connessione**:

1. **Corriere**: DHL, UPS o FEDEX.
2. **Denominazione**: un nome con cui la connessione verrà proposta in seguito
   alla creazione delle etichette, ad esempio «DHL spedizione magazzino».
3. **Utente / ID client** e **Password / secret client**.
4. **Chiave API (solo DHL: dhl-api-key)**.
5. **ID destinatario resi (solo DHL)**: necessario per le etichette di reso
   DHL.
6. **Numero di fatturazione / conto**: per DHL il numero di fatturazione, per
   UPS il numero spedizioniere, per FedEx il numero di conto.
7. **Sandbox / ambiente di test**: si collega all'ambiente di test del
   corriere; lì non nascono spedizioni reali.
8. **Attivo** e **Salva**.

Per una nuova connessione sono obbligatori utente/ID client e password/secret
client, per DHL anche la chiave API. Se in questo modulo sceglie un corriere
per cui esiste già una connessione, WorkDiary rifiuta il salvataggio con un
avviso – le connessioni esistenti si modificano solo tramite **Modifica**.

Per modificare, clicchi su **Modifica** accanto alla connessione nell'elenco
**Connessioni esistenti**. Il modulo si intitola allora **Modifica connessione
…** con il corriere nel titolo, ad esempio «Modifica connessione DHL»; il
corriere non si può cambiare.

- **Denominazione**, **Numero di fatturazione / conto**, **Sandbox / ambiente
  di test** e **Attivo** sono precompilati con i valori salvati. Ciò che
  modifica qui vale dopo il salvataggio.
- Utente, password, chiave API e ID destinatario resi non vengono mai
  mostrati. I campi lasciati vuoti mantengono il valore salvato; solo un nuovo
  inserimento lo sostituisce.
- Anche un **Numero di fatturazione / conto** lasciato vuoto mantiene il valore
  salvato.
- **Annulla** chiude la modifica senza salvare.

## Connessioni esistenti

L'elenco **Connessioni esistenti** mostra per ogni connessione il corriere, la
denominazione, la **Modalità** (**Sandbox** o **Produzione**) e lo stato
(**Attivo** o **Inattivo**). **Modifica** apre la connessione nel modulo.
**Disattiva** spegne una connessione, che non viene più proposta; le spedizioni
di questo corriere non vengono più verificate. Per riattivarla, la apra con
**Modifica**, spunti **Attivo** e salvi.

## Creare le etichette

- **Etichetta di spedizione per le consegne:** in **Ordini di produzione**, la
  pagina di dettaglio di un ordine contiene la sezione **Consegne**. Per una
  consegna con cliente scelga la connessione, indichi, se non sono registrati
  colli, il peso in grammi ed eventualmente lunghezza, larghezza e altezza in
  centimetri, e clicchi su **Spedisci**. UPS e FedEx usano le misure solo se
  sono indicate tutte e tre. I colli registrati forniscono da sé peso e misure.
  Il destinatario è il cliente della consegna. In seguito la consegna mostra lo
  stato **Etichetta creata** con corriere e numero di tracciamento. Per ogni
  consegna esiste un ordine di spedizione; uno annullato non conta. Sulla
  consegna, **Scarica etichetta** scarica di nuovo l'etichetta, **Verifica
  stato spedizione** recupera la situazione attuale e **Annulla spedizione**
  annulla l'invio – dettagli nella guida sugli ordini di produzione.
- **Etichetta di reso per i resi:** nelle **Pratiche di reclamo** scelga, per
  un reso nello stato **Annunciato**, la connessione, indichi il peso e clicchi
  su **Crea etichetta di reso**. Il mittente è il cliente; il suo indirizzo
  deve contenere via, CAP e città. **Scarica etichetta** Le fornisce il file;
  se i resi sono abilitati per il cliente nel portale clienti, anche lui può
  scaricare l'etichetta da lì.
- **Autorizzazione:** le etichette di spedizione le crea chi può modificare
  l'ordine di produzione; le etichette di reso chi dispone del diritto
  **Controllare e stoccare i resi**.

UPS fornisce l'etichetta come immagine (GIF), FedEx come PDF. Se il corriere
rifiuta l'ordine, WorkDiary scarta la bozza e Lei può riprovare dopo la
correzione.

## Tracciamento delle spedizioni

Nell'impostazione standard WorkDiary verifica ogni ora presso il corriere le
spedizioni aperte – stato **Etichetta creata**, **In transito** o **Problema di
consegna**. Ogni spedizione viene interrogata al massimo ogni tre ore e solo
fino a 60 giorni dalla sua creazione; dopo non è più considerata tracciabile.
La verifica riprende stato e cronologia della spedizione finché questa non
risulta **Consegnato**.

- Se una spedizione passa a **Problema di consegna**, WorkDiary invia la
  notifica **Problema di consegna di una spedizione**.
- L'ora dell'ultima verifica compare passando con il mouse sullo stato nella
  consegna (**Ultima verifica: …**).
- La verifica avviene solo tramite una connessione attiva. Se la richiesta al
  corriere non riesce, conta come gli altri errori di connessione di quella
  connessione (veda «Problemi tipici»).

## Limiti

- Una connessione per corriere e per organizzazione.
- Per impostazione predefinita le spedizioni DHL partono come DHL Paket
  nazionale; solo il gestore dell'installazione può impostare un altro
  prodotto.
- I documenti doganali per le spedizioni fuori dall'UE li crea separatamente
  sulla consegna (vedi la guida sugli ordini di produzione).

## Problemi tipici

- **«Per una nuova connessione sono obbligatori utente/ID client e
  password/secret client (DHL in più: chiave API).»** Completi le credenziali
  mancanti.
- **Nessuna connessione da scegliere:** non esiste una connessione attiva,
  oppure la consegna non ha un cliente o ha già un ordine di spedizione.
- **«Esiste già una connessione per questo corriere. La modifichi tramite
  «Modifica».»** Nel modulo **Aggiungi connessione** ha scelto un corriere già
  collegato. Apra la connessione nell'elenco con **Modifica**.
- **«Nessuna connessione attiva configurata per il corriere selezionato.»** La
  connessione è stata disattivata nel frattempo.
- **«Impossibile annullare la spedizione: …»** oppure **«Impossibile verificare
  lo stato della spedizione: …»** Il corriere ha rifiutato la richiesta o non
  era raggiungibile, oppure la connessione è inattiva. Quando la spedizione è
  già in transito, l'annullamento non è più possibile.
- **«Impossibile creare l'etichetta di spedizione: …»** Verifichi le
  credenziali, il numero di fatturazione o di conto, l'opzione **Sandbox /
  ambiente di test** e l'indirizzo del destinatario. Per UPS e FedEx manca
  spesso l'indirizzo del mittente nelle impostazioni dell'organizzazione; per
  le etichette di reso DHL l'ID destinatario resi.
- **«L'etichetta di reso richiede l'indirizzo del cliente (via, CAP,
  città).»** Completi l'indirizzo nella scheda del cliente.
- **Verificare le credenziali:** il controllo di stato del corriere lo esegue
  in **Plugin**. Se una connessione non riesce, WorkDiary segnala la
  connessione disturbata in **Attività operative**.
