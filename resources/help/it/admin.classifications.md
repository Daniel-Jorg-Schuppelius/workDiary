---
title: "Classificazioni e regole obbligatorie"
topic: admin.classifications
version: 2
keywords:
    - liste valori
    - categorie
    - menu a tendina
    - tabelle di riferimento
    - elenchi predefiniti
    - tipi di ordine
    - tipi di guasto
    - cause
    - campi obbligatori
audience:
    - admin
    - geschaeftsfuehrung
    - personalverwaltung
    - teamleitung
    - user
    - aussendienst
    - callcenter
    - support
related:
    - catalog.entry-types
    - diary-entries.create
    - admin.import
    - glossary.core
---

Le classificazioni sono liste di valori a livello di organizzazione per
dominio (tipi di commessa, attività, tipi di errore, cause, esiti,
priorità ecc.), ciascuna con codice, denominazione e, facoltativamente,
colore, icona e ordinamento. I valori predefiniti della piattaforma
possono essere sovrascritti, integrati con valori propri, riordinati o
disattivati per la propria organizzazione; con l'importazione CSV crea
o aggiorna molti valori in una volta (colonne obbligatorie: dominio,
codice, denominazione). Le regole obbligatorie collegano un tipo di
commessa a un dominio richiesto e stabiliscono da quale fase la
compilazione è necessaria — alla creazione, prima della chiusura o
prima della firma — e se la regola blocca o è solo un avviso.

Altri domini arrivano con i profili di settore, ad esempio tipi di
prestazione, gradi di non autosufficienza e canali di comunicazione
(assistenza), tipi di evento (catering) e supporti di carico (spedizioni). Il
dominio **Categorie di asset** integra le categorie informatiche generali del
modulo degli asset: un valore dell’organizzazione con codice proprio vi
compare in aggiunta, uno con il codice di una categoria generale ne sostituisce
la denominazione e uno disattivato la nasconde. Una categoria usata da un
asset può solo essere disattivata, non eliminata.
