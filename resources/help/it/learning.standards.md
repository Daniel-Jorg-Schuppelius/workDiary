---
title: "Standard di apprendimento: SCORM, cmi5 e LTI"
topic: learning.standards
version: 1
audience: []
related:
    - learning.overview
    - training.overview
    - admin.integrations
---

Oltre alle proprie unità, la piattaforma riconosce i formati di scambio più
diffusi: puoi importare corsi acquistati e avviare i tuoi corsi in altri
sistemi.

**SCORM 1.2 e 2004** — Un pacchetto SCORM è uno ZIP con un manifesto. Al
caricamento viene verificato ed estratto; i file eseguibili e i percorsi che
escono dal pacchetto vengono rifiutati. Il contenuto viene eseguito su un
**host dedicato**, così il codice esterno non gira nell'origine
dell'applicazione. Avanzamento e completamento derivano dai messaggi del
pacchetto.

**cmi5 e xAPI** — I corsi cmi5 comunicano la propria attività come dichiarazioni
all'archivio di apprendimento incluso. Una sessione accetta dichiarazioni solo
entro una finestra temporale limitata; poi viene chiusa.

**LTI 1.3** — La piattaforma funziona in entrambe le direzioni: può integrare
strumenti esterni come unità di apprendimento **e** essere avviata da un altro
sistema di gestione dell'apprendimento. L'avvio usa token firmati; le chiavi
vengono ruotate con regolarità e quelle precedenti restano valide per
verificare le sessioni in corso.

**Limiti:** In tutti e tre i casi vale la regola di completamento del corso,
non quella del pacchetto. Un pacchetto può segnalare il completamento, ma se
conti lo decide la pubblicazione del corso.
