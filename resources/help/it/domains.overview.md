---
title: "Gestione dei domini"
topic: domains.overview
version: 5
keywords:
    - registrare un dominio
    - rinnovo dominio
    - record DNS
    - zona DNS
    - nameserver
    - record MX
    - trasferimento dominio
    - blocco trasferimento
    - cambio intestatario
    - scadenza dominio
    - rivenditore domini
    - nome a dominio
audience: []
modules:
    - module.domain
related:
    - admin.domain-provider
    - contacts.manage
---

Il modulo gestisce i domini di un account DomainReselling collegato come un
portafoglio tracciabile: dall'assegnazione del cliente e dalla scadenza,
passando per i server dei nomi/DNS, fino a rinnovo, trasferimento e
scritture. La connessione stessa si configura in «DomainReselling» nell'area
di amministrazione.

**Portafoglio:** La panoramica elenca ogni dominio con cliente, scadenza,
modalità di rinnovo, registrar, blocco di trasferimento e attualità dei
dati. Gli indicatori in alto mostrano la scadenza entro 90 giorni, le
modalità a rischio (autoexpire/autodelete), i domini senza assegnazione
cliente e i casi di sincronizzazione/conflitto. Si filtra per nome di
dominio, TLD, attualità, modalità di rinnovo e corridoio di scadenza.

**Assegnazione cliente:** Ogni dominio può essere assegnato a un cliente
(internamente tramite il suo identificatore Sqid). I domini non assegnati
restano visibili nell'indicatore così da mantenere il portafoglio completo.

**Vista di dettaglio:** La pagina del dominio riunisce panoramica, server
dei nomi e DNS, fatture, cronologia e azioni. «Aggiorna» riconcilia lo stato
del provider per quel dominio specifico.

**DNS:** La zona viene letta su richiesta. Nell'interfaccia si possono aggiungere
ed eliminare singoli record. «Sostituisci zona» compare solo dopo la lettura
della zona: la finestra è precompilata con il suo stato e chiede conferma prima
dell'invio, perché una sostituzione completa elimina ogni record mancante nel
modulo. I record il cui tipo il modulo non può modificare restano invariati; un
modulo vuoto viene rifiutato. Dopo una scrittura il sistema rileva gli scostamenti (conflitto DNS) e
li rende visibili invece di sovrascriverli. I record MX/SRV richiedono una
priorità.

**Registrazione:** Prima della registrazione viene verificata la
disponibilità. Una registrazione richiede un cliente, un handle di contatto
proprietario, almeno due server dei nomi e una conferma esplicita del prezzo.

**Scadenza e trasferimento:** Impostare la modalità di rinnovo, rinnovare
manualmente, attivare o rilasciare il blocco di trasferimento e avviare un
trasferimento in entrata vengono eseguiti come comandi registrati con
cronologia di stato (bozza → inviato → confermato).

**Azioni ad alto rischio:** L'eliminazione, il push a un altro utente, il
trade (cambio intestatario), il trasferimento in uscita e l'assegnazione
dell'oggetto sono bloccati: richiedono di ridigitare il nome del dominio e
un'approvazione a quattro occhi. Le azioni inviate compaiono per approvazione
o rifiuto; lo stato del provider viene riconciliato dopo l'esecuzione (i
conflitti vengono segnalati).

**Scritture e report:** La vista delle scritture è un giornale in sola
lettura, non una fattura fiscale. I report riuniscono il corridoio di
scadenza, la previsione dei costi di rinnovo, l'assegnazione cliente, le
modalità a rischio e la copertura delle fatture.

**Rivenditori/subutenti:** La vista dei rivenditori mostra la gerarchia dei
subutenti con portafoglio, saldi e livello, e consente l'assegnazione cliente
per subutente. Per ogni sottoutente può inserire il numero cliente presso il
fornitore; i documenti mensili costruiti dal giornale contabile nel registro
di rivendita portano allora i numeri di fattura del portale («numero
cliente-MM-AAAA»).

**Comandi incerti:** se la risposta del fornitore resta incompleta, il comando passa a «incerto» e non viene mai ripetuto. La prossima sincronizzazione legge lo stato del dominio presso il fornitore: registrazione, modalità di rinnovo, blocco di trasferimento e rinnovo vengono così confermati, riconosciuti come falliti o segnati come conflitto. Trasferimenti, DNS e azioni ad alto rischio non hanno una richiesta di stato — li verifichi presso il fornitore e li chiuda manualmente con una motivazione nel giornale dei comandi.

**Costi e saldo:** nelle impostazioni dell’organizzazione stabilisce una soglia di costo per i rinnovi e un saldo minimo. La gestione domini segnala i rinnovi imminenti sopra la soglia e i conti fornitore il cui saldo, incluso il limite di credito, non copre i rinnovi dei prossimi giorni — senza copertura il fornitore elimina i domini alla data di mancato rinnovo.

**Modalità di rinnovo:** si possono scegliere le tre modalità documentate dal
fornitore: rinnovo automatico, scadenza automatica ed eliminazione automatica. Un
valore salvato in precedenza, «Rinnova una volta», risulta sconosciuto dopo
l'aggiornamento; la sincronizzazione successiva legge la modalità effettiva presso
il fornitore.

## Stato di sincronizzazione di un dominio

Mentre è in corso un comando verso il fornitore, il dominio mostra **In sospeso**. Se l'esito resta incerto o il fornitore diverge dopo la scrittura, mostra **Incerto** o **Conflitto** e compare nel riquadro dei problemi di sincronizzazione; una sincronizzazione non lo sovrascrive finché il comando è aperto. Dopo la chiusura – automatica o manuale – il dominio mostra **Obsoleto** finché la sincronizzazione successiva non lo rilegge.
