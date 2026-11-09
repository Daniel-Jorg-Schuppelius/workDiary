---
title: "Gestione delle emergenze e delle crisi"
topic: crisis.overview
version: 2
keywords:
    - unità di crisi
    - piano di emergenza
    - continuità operativa
    - BCM
    - allerta di crisi
    - quadro della situazione
    - obbligo di notifica
    - NIS2
    - violazione dei dati
    - ripristino
    - esercitazione di crisi
audience: []
modules:
    - module.crisis_management
related:
    - documents.manage
---

Il fascicolo di crisi mantiene uno stato di direzione coordinato sopra
i procedimenti specialistici collegati (ticket di assistenza, incidenti
di sicurezza/privacy, eventi di sicurezza sul lavoro, esecuzioni di
playbook) — i moduli specialistici restano l'elemento primario.

**Unità di crisi e allertamento:** ruoli con persona, sostituto e
reperibilità. L'allertamento prevale deliberatamente sui periodi di
riposo (allarme di crisi); ogni membro conferma la ricezione del
proprio allarme, gli allarmi non confermati vengono escalati
manualmente al sostituto. La nomina nell'unità di crisi costituisce al
tempo stesso l'accesso di emergenza al fascicolo, registrato
nell'audit trail.

**Quadro della situazione e decisioni:** i rapporti sulla situazione
sono versionati e append-only; il protocollo delle decisioni registra
momento, decisione e motivazione.

**Termini di notifica:** i template dei termini per categoria (DSGVO
72 h, NIS2 24 h/72 h/1 mese, KRITIS senza indugio) sono dati di
catalogo — l'organizzazione può sovrascriverli; le scadenze concrete
decorrono dall'attivazione.

**Scartare un falso allarme:** se una crisi segnalata si rivela un falso
allarme prima che qualcuno abbia valutato la situazione, «Scarta come
falso allarme» chiude il fascicolo con una motivazione obbligatoria. La
motivazione resta nel registro di audit, il fascicolo rimane in sola
lettura. Dopo la valutazione non si scarta più, si dichiara il cessato
allarme.

**Comunicazione:** bozza, approvazione (mai da parte dell'autore) e
invio documentato restano tracciabili separatamente.

**Ripristino e follow-up:** processi critici con RTO/RPO, workaround e
processi sostitutivi; dopo il cessato allarme segue il follow-up
(obbligatorio prima della chiusura). Le esercitazioni si svolgono
separatamente e non falsano i fascicoli reali.
