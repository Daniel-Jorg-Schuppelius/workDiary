---
title: "Asset e parco veicoli"
topic: assets.fleet
version: 9
keywords:
    - gestione flotta
    - gestione veicoli
    - inventario
    - attrezzature
    - consegna attrezzatura
    - prestito attrezzi
    - restituzione
    - registro carburante
    - registro ricariche
    - manutenzione programmata
    - segnalare guasto
    - ciclo di vita
audience: []
modules:
    - module.fuhrpark
related:
    - documents.manage
    - travel-expenses.manage
    - reports.overview
---

Asset e veicoli rappresentano oggetti aziendali con stato,
responsabilità, documenti e informazioni di manutenzione. I registri
di rifornimento e di ricarica completano lo storico dei consumi.

Registri i dati anagrafici e gli identificativi univoci, assegni la
sede o i responsabili e aggiorni gli intervalli di manutenzione e i
documenti rilevanti. Le modifiche di stato dovrebbero rispecchiare il
ciclo di vita effettivo.

Prima dell'eliminazione o della dismissione, verifichi se sono
collegati manutenzioni, pratiche, viaggi o documenti aperti. Lo storico
critico dovrebbe essere archiviato e non andare perso a causa di
sovrascritture.

## Categorie

Il campo «Categoria» offre le categorie informatiche generali e quelle della
sua organizzazione. I profili di settore apportano categorie adatte, ad
esempio videocamera per tubazioni e pressatrice nel settore termoidraulico o
pulsossimetro e armadio per stupefacenti nell’assistenza. Gli amministratori
creano le proprie categorie in Classificazioni, dominio «Categorie di asset».
Una categoria disattivata non viene più proposta per i nuovi asset, ma resta
su quelli che la hanno già.

## Assegnazione e restituzione

Tramite il pannello «Assegnazione / restituzione» nella pagina di
dettaglio dell'asset, un dispositivo viene assegnato a una persona o a
un team, facoltativamente con riferimento a un ordine e restituzione
prevista. Per ogni asset esiste al massimo un'assegnazione aperta; un
asset già assegnato o bloccato per un difetto non può essere assegnato
di nuovo. Con la restituzione l'asset torna disponibile. Se
un'assegnazione supera la restituzione prevista, compare un avviso di
ritardo e lo scanner delle scadenze avvisa la persona che ha preso in
prestito l'asset oppure il responsabile del team.

## Difetti e blocchi

Nel pannello «Difetti / blocchi» si possono registrare difetti con il
relativo livello di gravità. Se è impostato «Blocca asset (nessun
prelievo possibile)», il difetto aperto blocca qualsiasi ulteriore
assegnazione finché non viene risolto o stornato. Per risolverlo o
stornarlo è necessaria una nota di risoluzione.

## Piani di manutenzione

Nella scheda **Piani di manutenzione**, **Crea piano** registra
manutenzioni ricorrenti: **Denominazione**, **Intervallo** con
**Valore**, **Tolleranza (giorni)** e **Prima scadenza**. Per giorni,
settimane e mesi, **Completato** calcola la scadenza successiva
dall'intervallo; per ore di funzionamento e chilometri la inserisce Lei.
**Metti in pausa** toglie il piano dal controllo delle scadenze.

Chi può vedere i contratti SLA collega il piano a un **Contratto SLA**
(un contratto del cliente dell'asset o uno senza cliente) e imposta
**Obbligo contrattuale** se il piano rappresenta una scadenza
obbligatoria di quel contratto. Il piano compare allora sul contratto
in **Manutenzioni obbligatorie da contratto**; il contrassegno compare
anche sull'asset, nel fascicolo oggetto e nel portale clienti. Se
l'helpdesk è attivo, **Alla scadenza** stabilisce cosa fa il controllo
giornaliero delle scadenze: **Crea un ticket di servizio** crea
esattamente un ticket per scadenza con il contratto scelto, **Solo
avviso (nessun record)** annota soltanto la scadenza.

## Contratto SLA dell'asset

Chi può vedere i contratti SLA sceglie un **Contratto SLA** per l'asset in
**Contratti & SLA** nel modulo **Modifica**: un contratto del cliente salvato o
uno senza cliente. OLA, contratti legati a un progetto e contratti inattivi non
sono proposti. Il contratto vale per i ticket di assistenza di questo asset
prima dei contratti del progetto e del cliente (tema «SLA, contratti e livelli
di servizio»); compare nell'intestazione della pagina di dettaglio e nel
fascicolo oggetto come **Assegnazione diretta**. Senza il permesso di vedere i
contratti SLA il campo manca e il collegamento resta invariato al salvataggio.

## Fascicolo oggetto (ciclo di vita)

Il «Fascicolo oggetto» raccoglie l'intero ciclo di vita di un asset in
una vista coerente e stampabile: dati anagrafici, sede e locale, stato
del ciclo di vita derivato (in esercizio, sostituito o dismesso),
messa in servizio, messa fuori servizio e garanzia. Più sotto compaiono
manutenzioni, assegnazioni e restituzioni, difetti e blocchi, ordini
collegati, verbali, impieghi di materiale, punti aperti e allegati,
nonché lo storico completo del ciclo di vita.

Il fascicolo è raggiungibile tramite il pulsante «Fascicolo
dell'oggetto» nella pagina di dettaglio dell'asset e può essere
emesso come documento tramite la funzione di stampa del browser
(aggiungendo «?print=1» si apre direttamente la finestra di stampa).
Lo stato del ciclo di vita viene derivato da stato, messa fuori
servizio e garanzia – non è prevista una gestione separata.

## Dismissione

**Dismettere**, nella pagina di dettaglio dell'asset, toglie definitivamente
un asset dall'esercizio. Data e motivo sono obbligatori; entrambi compaiono
poi nell'intestazione della pagina di dettaglio e nel fascicolo oggetto.
Ritiri prima un asset ancora consegnato. L'operazione sospende i piani di
manutenzione e gli obblighi di verifica e annulla gli appuntamenti di
verifica aperti. In seguito l'asset non può più essere consegnato,
noleggiato, prenotato o utilizzato in una procedura e non compare più negli
elenchi di selezione di noleggio e ordini di stampa. Il fascicolo con tutti i
giustificativi resta conservato. L'operazione non è reversibile; lo stato
«Fuori servizio» non è proposto nel modulo «Modifica».

**Autorizzazione:** **Metti fuori servizio asset/oggetti**.

## Cambio di proprietario

**Cambiare proprietario**, nella pagina di dettaglio dell'asset, registra un
cambio di proprietà (organizzazione, cliente, esterno) e di cliente, con una
nota se lo desidera. Ogni cambio compare nel fascicolo oggetto, in «Cronologia
dei cambi di proprietario», con data, origine, destinazione, persona e nota;
le voci non sono modificabili. Scegliere un altro cliente nel modulo
«Modifica» è lo stesso cambio: viene registrato con la nota inserita lì.

Se cambia il cliente, i collegamenti a contratti del cliente precedente
decadono, sull'asset e sui suoi piani di manutenzione, lì insieme
all'**Obbligo contrattuale**; i contratti senza cliente restano. Scelga un
contratto del nuovo cliente dopo il salvataggio, nel modulo riaperto.

**Autorizzazione:** **Trasferisci la proprietà degli asset**, anche per il
cambio di cliente nel modulo «Modifica».

## Stato, blocco e condizione

Gli stati **Bloccato** e **In riparazione** bloccano un asset ovunque agisca
anche un blocco proveniente da verifiche o noleggio: consegna, noleggio,
prenotazione del veicolo, ordine di stampa e avvio di una procedura
sull'asset o su un ordine relativo a quell'asset. Un modello di procedura che
consente espressamente gli asset bloccati (ad esempio la riparazione) si
avvia comunque. A tal fine lo stato crea un blocco con il motivo «Bloccato
manualmente» o «Manutenzione». Termina quando Lei ripristina lo stato o
rimuove il blocco in **Strumento di controllo**; l'asset torna allora
**Attivo**. Le deroghe temporanee valgono per questo blocco come per ogni
altro.

La **condizione** accanto allo stato è derivata e non viene gestita a mano:
**Critico** con un blocco attivo o un difetto bloccante, **Limitato** con un
difetto aperto o con manutenzione o verifica scadute, altrimenti **In
ordine**. Viene aggiornata a ogni modifica e durante la notte.

Se modifica l'ubicazione a testo libero o il locale, il cambio compare nella
cronologia come «Ubicazione modificata» con l'ubicazione precedente e quella
nuova.

## Ritiro con condizione

**Revoca** apre una finestra di dialogo per lo **Stato alla restituzione**
e la **Nota**. Entrambi compaiono poi nella tabella dello storico del
pannello «Assegnazione / restituzione», accanto allo stato alla consegna, e
nel fascicolo oggetto.

## Cockpit

Sotto le sezioni della pagina di dettaglio, i moduli mostrano ciò che esiste per l'asset: **ticket di assistenza** aperti, **leasing e finanziamento** (contratti a cui l'asset appartiene), **reclami** aperti, **termini di garanzia** aperti da ordini e protocolli dell'asset, **punti aperti** e **procedure**. Un pannello compare solo se il modulo è attivo, se ha il diritto all’elenco corrispondente e se ci sono voci; il clic apre la voce.

**Rimettere in servizio:** un asset dismesso si rimette in servizio con l’azione omonima, con un motivo e una voce nel protocollo di audit, con lo stesso diritto della dismissione. I piani di manutenzione sospesi e gli obblighi di verifica inattivi restano disattivati e vanno riattivati uno per uno. Un asset smaltito tramite un ordine di smaltimento resta fuori servizio; il modulo di modifica non cambia questo stato.

## Distinta

La **distinta** di un apparecchio riporta ciò che è montato in questo
esemplare: articolo o testo libero, quantità, posizione, numero di serie, data
di montaggio e intervallo di sostituzione. Sostituire crea un nuovo pezzo e
lascia il vecchio nello storico con la data di smontaggio; i pezzi soggetti a
usura in scadenza compaiono come proposta. In **Materiale dagli interventi**
compaiono i consumi di materiale assegnati a questo apparecchio su un foglio
ore. **Riprendi** inserisce un materiale nella distinta con il giorno
dell'intervento come data di montaggio – con l'articolo, se il materiale ne ha
uno; ogni consumo si può riprendere una sola volta.
