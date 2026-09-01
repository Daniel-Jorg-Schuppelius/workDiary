---
title: "Collegare Billbee"
topic: admin.billbee
version: 1
audience:
    - admin
modules:
    - module.lager
related:
    - admin.integrations
    - admin.plugins
---

WorkDiary collega **Billbee** come aggregatore multicanale: gli ordini di
Amazon, eBay, Otto, Kaufland, Shopify e altri confluiscono in Billbee e
vengono importati qui come **specchio ordini con origine del canale**.

**Inbox-first:** Gli acquirenti non vengono mai creati alla cieca come
clienti. Le corrispondenze univoche o gli acquirenti abituali già
assegnati vengono collegati; tutto il resto appare come proposta nella
inbox di integrazione e viene deciso lì.

**Credenziali:** chiave API (attivata dal supporto Billbee), nome utente
Billbee e password API separata — cifrate per organizzazione, gestite
tramite la scheda del plugin (Amministrazione → Plugin).

**Canale di ritorno delle giacenze:** Se l'organizzazione gestisce le
scorte in modalità «esterna» tramite Billbee, i movimenti locali vengono
trasmessi come **aggiornamenti assoluti** per SKU (nessuna deriva in caso
di ripetizioni). Richiede una mappatura SKU curata — i prodotti senza
equivalente locale restano visibili come assegnazioni aperte.

**Limite:** Billbee consente 2 richieste al secondo; la sincronizzazione
rispetta automaticamente questo limite.
