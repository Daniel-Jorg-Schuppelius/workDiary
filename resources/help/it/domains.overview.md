---
title: "Gestione dei domini"
topic: domains.overview
version: 1
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

**DNS:** La zona viene letta su richiesta; i record possono essere sostituiti
o modificati in modo mirato. Dopo una scrittura il sistema rileva gli
scostamenti (conflitto DNS) e li rende visibili invece di sovrascriverli. I
record MX/SRV richiedono una priorità.

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
subutenti con portafoglio, saldi e livello, e consente l'assegnazione
cliente per subutente.
