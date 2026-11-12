---
title: "SLA, contratti e livelli di servizio"
topic: sla.overview
version: 9
keywords:
    - accordo sul livello di servizio
    - tempo di risposta
    - tempo di risoluzione
    - contratto di assistenza
    - violazione SLA
    - superamento scadenza
    - escalation
    - ticket in ritardo
    - tasso di rispetto
    - report SLA
audience: []
related:
    - glossary.core
---

I contratti SLA (Service Level Agreement) memorizzano, per cliente o come
**Contratto predefinito** per tutti i clienti, i tempi di reazione e di
risoluzione concordati per priorità (**Termini per priorità**),
facoltativamente con l’**Orario lavorativo** – senza di esso le scadenze
decorrono in tempo di calendario. Trova i contratti in **Service desk** →
**Contratti SLA**. Da questi valori obiettivo WorkDiary ricava lo stato
SLA di un ticket di assistenza e documenta i superamenti in modo
inalterabile.

## Stato SLA sul ticket

Ogni ticket di assistenza con una scadenza SLA mostra il proprio stato di
risoluzione come badge:

- **SLA nei tempi**: resta tempo sufficiente fino alla scadenza di
  risoluzione.
- **SLA a rischio**: il tempo residuo è al massimo il 20 % della
  scadenza complessiva.
- **SLA violato**: la scadenza è superata (oppure il ticket è stato
  confermato o risolto troppo tardi).
- **SLA rispettato**: il ticket è stato risolto in tempo.

I ticket senza scadenza mostrano «Nessuno SLA». La scadenza di reazione
viene valutata allo stesso modo e verificata alla prima conferma.

## Registro delle violazioni e rilevamento

Le scadenze superate vengono registrate in un registro delle violazioni
– esattamente una volta per ticket e tipo («Tempo di reazione» o «Tempo
di risoluzione»). Vengono rilevate:

1. durante il controllo automatico dei ticket aperti, che per
   impostazione predefinita viene eseguito ogni cinque minuti,
2. nei cambi di stato, quando la prima reazione o la risoluzione avviene
   troppo tardi.

A ogni violazione si può assegnare una **Causa** nell’**Elenco
violazioni** del report SLA e confermarla con **Conferma**; serve il
permesso **Confermare le violazioni SLA**.

## Escalation

Il controllo automatico notifica la persona assegnata per i ticket a
rischio e violati. Se l’evento resta irrisolto, WorkDiary esegue
l’escalation secondo le **Regole di notifica** dell’organizzazione verso
il ruolo di escalation lì impostato (per impostazione predefinita i capi
team). Inoltre WorkDiary applica i livelli registrati in **Escalation**
nel contratto SLA.

Lei gestisce i livelli nel contratto, in **Livelli di escalation**: per ogni
livello i minuti dopo la scadenza della soluzione e il destinatario –
l’incaricato del ticket, un ruolo o una persona.

## Quale contratto vale

Per un nuovo ticket vale il primo contratto adatto in quest’ordine: contratto
dell’asset, contratto del progetto, contratto del cliente, **SLA
predefinito** della coda, **contratto predefinito** dell’organizzazione. Il
contratto dell’asset è l’impegno più stretto: vale per questo oggetto preciso
e si sceglie nel modulo dell’asset (tema «Asset e parco veicoli»). Vale solo se
è attivo e appartiene al cliente del ticket o non ha cliente; altrimenti vale
il resto dell’ordine. Un cambio di coda lo lascia invariato. Se il contratto non definisce
orari di servizio, valgono quelli della coda; la regione delle festività
della coda sostituisce quella dell’organizzazione. Lo stato del contratto
viene congelato sul ticket: le modifiche successive al contratto non
reinterpretano i ticket esistenti.

## Pause

In **Pausa SLA per** il contratto stabilisce in quali stati di attesa
l’orologio si ferma. Alla ripresa – anche tramite una risposta del cliente –
le scadenze aperte si spostano della durata della pausa.

## OLA

Un contratto con **OLA (obiettivo interno)** e un team non è una promessa al
cliente: non viene mai usato come SLA di un ticket e non può essere contratto
predefinito né essere legato a un cliente o a un progetto. I ticket nelle
code del team mostrano inoltre l’obiettivo interno sull’orologio SLA.

Se un ticket aperto supera l’obiettivo interno, il responsabile del team
dell’OLA riceve una sola notifica (in sua assenza la persona assegnata); poi
si applica l’escalation della regola di notifica **Obiettivo interno (OLA)
superato**. WorkDiary non tiene un registro delle violazioni per gli OLA.

## Manutenzioni obbligatorie da contratto

La pagina del contratto elenca in **Manutenzioni obbligatorie da
contratto** i piani di manutenzione collegati a questo contratto, con
l'asset, la prossima scadenza e l'azione **Alla scadenza**. I piani con
**Obbligo contrattuale** portano il contrassegno omonimo. Il
collegamento si imposta nella finestra di dialogo del piano di
manutenzione dell'asset (argomento «Asset e parco veicoli»). Con
l'azione **Crea un ticket di servizio** si crea un ticket per ogni
scadenza, a cui si applica questo contratto.

## Contingenti di tempo incluso

Sul contratto SLA, **Aggiungi contingente** fissa per periodo (mese,
trimestre, anno) le ore incluse, la **soglia di avviso** in percentuale da cui
il monitoraggio delle scadenze segnala e, come giustificativo, il **forfait** e
la **tariffa oraria aggiuntiva**. Il consumo è il tempo fatturabile delle
persone per il cliente del contratto nel periodo. La pagina del contratto e il
report SLA mostrano consumo, forfait e importo delle ore aggiuntive (ore oltre
il contingente × tariffa). workDiary non ne ricava una fattura – la fatturazione
avviene nel programma di contabilità. Modificare ed eliminare richiedono il
permesso di gestire i contratti SLA.

## Report SLA

Il **Report SLA** (**Report** → **Progetti e clienti** → **SLA**) mostra
per il periodo scelto i **Ticket con SLA**, il **Tasso di rispetto** e le
**Violazioni** – suddivise **Per tipo**, **Per priorità**, **Per
cliente** e **Per causa** – oltre a un **Elenco violazioni** con
collegamento al ticket e le **Quote di tempo incluso**. Il report è
esportabile in PDF, CSV ed Excel. Può consultarlo chi dispone del
permesso **Visualizzare stato e report SLA**; per esportarlo serve inoltre il
permesso **Esporta i report** – senza di esso i pulsanti di esportazione non
compaiono. Gli amministratori possono sempre fare entrambe le cose.

## Proposta di materiale

Se al ticket è assegnata un’attrezzatura con distinta componenti, mostra i **pezzi soggetti a usura in scadenza** (da intervallo di sostituzione e data di montaggio) come proposta per l’intervento: cosa sostituire davvero lo decide il tecnico. La stessa scheda compare negli ordini con attrezzatura.

**Ticket riservati nel rapporto:** per i ticket riservati che non può aprire,
l'elenco delle violazioni SLA non mostra né il ticket né il cliente; nella
ripartizione per cliente rientrano in «Ticket riservato».
