---
title: "Helpdesk e service desk"
topic: helpdesk.overview
version: 5
keywords:
    - sistema di ticket
    - aprire un ticket
    - segnalazione guasto
    - richiesta di assistenza
    - richiesta di servizio
    - coda di supporto
    - SLA
    - tempo di risposta
    - nota interna
    - smistamento ticket
    - soddisfazione clienti
    - ITSM
audience: []
related:
    - open-issues
    - customer-portal.overview
---

L'helpdesk raccoglie guasti e richieste di assistenza come ticket —
ciascuno con numero, titolo, priorità, stato, cliente, riferimento
facoltativo a un dispositivo e persona responsabile.

**Code:** i ticket vengono gestiti in code (aree di responsabilità),
ciascuna con un team responsabile e un contratto SLA facoltativo.
Esattamente una coda è quella predefinita per i nuovi ticket in
ingresso; un cambio avviene in modo controllato. Una coda può essere
eliminata solo quando non le sono più assegnati ticket — nulla viene
riassegnato in silenzio.

**Priorità e SLA:** dal contratto SLA derivano i termini di reazione e
di risoluzione per ciascuna priorità. I termini in corso sono visibili
sul ticket; se un termine viene superato senza che la prima reazione o
la risoluzione sia avvenuta in tempo, ciò viene registrato come
violazione e confluisce nell'analisi SLA.

**Pubblico vs. interno:** le risposte al cliente e le note interne sono
due azioni distinte con permessi differenti. Una risposta pubblica è
visibile al cliente e può essere inviata via e-mail ai destinatari; una
nota interna resta esclusivamente nel team. La separazione è ancorata a
livello tecnico — la pubblicazione accidentale di annotazioni interne è
esclusa.

**Ingresso:** i ticket nascono manualmente, via e-mail (le risposte a
un ticket esistente vengono assegnate automaticamente al procedimento),
tramite il portale clienti, da punti aperti, da piani di manutenzione o
tramite l'interfaccia. La fonte resta annotata sul ticket.

**Routing:** le regole distribuiscono automaticamente i ticket in
ingresso — ad esempio in una coda, con priorità o responsabilità — e
vengono applicate in un ordine definito. Una modalità di test verifica
una regola su un ticket di esempio e ne protocolla il risultato, senza
modificare nulla.

**Approvazioni:** le fasi di approvazione aperte di richieste di servizio,
change e trattative contrattuali sono raccolte in «Approvazioni» — per
pratica solo la fase aperta successiva e solo per i responsabili: una
persona, un ruolo o, per le trattative contrattuali, il ruolo che
l'organizzazione assegna al tipo di fase (predefinito: commerciale →
Contabilità, tecnica → Capo team, risorse umane → Gestione del personale).
Approvare, respingere, chiedere chiarimenti e delegare hanno lo stesso
effetto che nella pratica; un rifiuto chiude il turno e
l'auto-approvazione resta bloccata.

**Servizi di business e mappa dei servizi:** un ticket può portare un
servizio di business (per esempio «e-mail» o «server contabilità») e
un'offerta di servizio del catalogo; i ticket si possono poi filtrare,
instradare e valutare nei report in base a essi. La mappa dei servizi mostra
come i servizi di business dipendono l'uno dall'altro: per ogni servizio, da
cosa dipende, chi ne ha bisogno e chi sarebbe coinvolto in caso di guasto —
insieme ai ticket aperti di quei servizi. Le dipendenze si curano sul servizio
di business (selezione multipla); un ciclo viene rifiutato. Sul ticket la
scheda «Impatto» mostra quali altri servizi di business colpirebbe un guasto
del servizio segnalato e quanti ticket vi sono aperti — così un'anomalia si
può prioritizzare prima che arrivino le segnalazioni successive.

**Soddisfazione e report:** dopo la chiusura, il cliente può lasciare
nel portale una breve valutazione — una per ticket. I report mostrano
il volume per coda, i tempi di reazione e di risoluzione,
l'adempimento SLA, i motivi di attesa, le quote di change, il numero di
problemi aperti e la domanda di catalogo. Alle classifiche dei singoli
operatori si rinuncia deliberatamente.

**Cambiare lo stato:** con **Cambia stato** sul ticket Lei sceglie il nuovo
stato; vengono proposte solo le transizioni consentite. Alla soluzione e alla
chiusura registra il **Codice di chiusura** e il **Riepilogo della
soluzione**. Uno stato di attesa richiede un motivo, una data di
ripresentazione e una persona responsabile; alla scadenza WorkDiary glielo
ricorda. Il contratto SLA stabilisce se l’orologio SLA si ferma. Un ticket
risolto si riapre solo con un motivo; i tempi di reazione e di soluzione
precedenti restano invariati.

**Impostazioni della coda:** per ogni coda Lei definisce i tipi di ticket
supportati, gli orari di servizio, la regione delle festività, il nome del
mittente e l’indirizzo di risposta. I nuovi ticket senza assegnazione vanno
alla coda predefinita; se questa non accetta il tipo di ticket, alla prima
coda che lo accetta. Lo SLA predefinito della coda vale se né l’asset del
ticket né il progetto né il cliente hanno un proprio contratto.

**Coda gestita esternamente:** se un sistema collegato come Zammad gestisce i
ticket di una coda, i cambi di stato e le risposte al cliente sono bloccati
in WorkDiary e il ticket mostra un avviso. Assegnazione, note interne, tempi
e fatturazione restano possibili. Quando Lei ripristina la destinazione dei
ticket dell’integrazione, WorkDiary gestisce di nuovo la coda.

**Riservatezza:** un ticket riservato è visibile solo all’incaricato, agli
osservatori, al team della coda e agli amministratori. Lei imposta il livello
alla creazione o nella classificazione.

**Ordinare un servizio:** in **Ordinare un servizio** i collaboratori
ordinano per sé voci del catalogo dei servizi. I dati del modulo d’ordine
compaiono sul ticket e nella finestra di decisione dell’approvazione; il
ticket mostra inoltre quale pratica evade l’ordine.

**Stato dell’invio:** ogni risposta inviata per e-mail indica se l’invio è in
coda, eseguito o non riuscito.

**Ticket da un punto aperto:** sul punto aperto, **Apri ticket** crea un
ticket con titolo, descrizione e riferimento del punto. Per ogni punto nasce
al massimo un ticket; il punto vi rimanda.

**Portale clienti:** quando il cliente conferma la soluzione o riapre il
ticket con un motivo, ciò avviene come un cambio di stato interno — con
cronologia e tempi di reazione e di soluzione invariati. Per le code gestite
esternamente, risposta, accettazione e riapertura sono bloccate anche nel
portale; lì il portale mostra solo lo stato.

**Cambio di coda e casella:** quando un ticket cambia coda, vale lo SLA
predefinito della nuova coda se il suo contratto proveniva finora dalla coda
o dall’organizzazione oppure mancava; un contratto dell’asset, del progetto o
del cliente e un contratto impostato espressamente restano. In **Casella in ingresso** Lei
assegna una casella alla coda — una per coda.

**Ticket riservati negli elenchi:** gli elenchi di selezione e i riferimenti
mostrano un ticket riservato solo alle persone autorizzate. Nei dettagli dei
report continua a essere conteggiato, ma agli altri appare solo come «Ticket
riservato».
