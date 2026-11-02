---
title: "Helpdesk e service desk"
topic: helpdesk.overview
version: 3
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
