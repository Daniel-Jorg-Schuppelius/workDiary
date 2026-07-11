---
title: "Regole dei centri di costo"
topic: admin.cost-center-rules
version: 1
audience:
    - admin
related:
    - exports.payroll
    - org.teams
---

Le regole dei centri di costo assegnano automaticamente un **centro di
costo** alle registrazioni orarie durante l'esportazione dei tempi (ad
es. per l'ufficio paghe) – senza che nessuno debba rielaborare
manualmente ogni singola registrazione.

**Struttura di una regola:** ogni regola è composta esattamente da
**una fonte** – un utente **oppure** un team; se entrambi restano
vuoti, la regola agisce come **predefinita dell'organizzazione**. A
ciò si aggiungono il codice del centro di costo e una priorità. Le
regole sono gestite dagli amministratori nonché da contabilità/ufficio
paghe con l'apposita autorizzazione.

**Ordine di risoluzione:** durante l'esportazione, per ogni persona la
risoluzione procede dalla regola più specifica a quella più generale:

- **Regola utente** – vince sempre, se presente.
- **Regola team** – si applica se la persona è membro del team.
- **Predefinita dell'organizzazione** – la regola senza utente e senza team.
- Se nessuna regola corrisponde, il centro di costo resta **vuoto** nell'esportazione.

**La priorità come criterio decisivo:** se allo stesso livello entrano
in gioco più regole (ad es. perché una persona fa parte di più team
con una propria regola), vince la regola con la **priorità più alta**;
a parità di priorità, quella creata per prima. È quindi consigliabile
assegnare intervalli di priorità significativi (ad es. a passi di
100), in modo da poter inserire regole intermedie in un secondo
momento.

**Interazione con i dati anagrafici:** i centri di costo vengono
gestiti come dati anagrafici con codice e denominazione per ciascuna
organizzazione. Le regole memorizzano attualmente il codice come testo
– occorre quindi assicurarsi che i codici nelle regole corrispondano
ai dati anagrafici e adeguare le regole quando i centri di costo
vengono rinominati o disattivati.

**Raccomandazione:** iniziare con una regola predefinita
dell'organizzazione, aggiungere regole team per i reparti con un
proprio centro di costo e utilizzare le regole utente solo per vere
eccezioni. Dopo ogni modifica, verificare un'esportazione di prova
prima che i dati vengano trasmessi all'ufficio paghe.
