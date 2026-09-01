---
title: "Collegare Etsy"
topic: admin.etsy
version: 1
audience:
    - admin
modules:
    - module.lager
related:
    - admin.integrations
    - admin.plugins
---

WorkDiary collega direttamente il **negozio Etsy** dell'organizzazione
(Open API v3): gli ordini appaiono come **specchio ordini**, le segnalazioni
di spedizione con tracking tornano a Etsy e le commissioni e i versamenti
del ledger Etsy sono disponibili per l'analisi.

**Seller app propria:** Ogni organizzazione registra la propria seller app
su etsy.com/developers (approvazione in pochi minuti) e salva **keystring**
e **shared secret** nella scheda del plugin. La redirect URI dell'app deve
essere esattamente l'URL di callback mostrata nel pannello (HTTPS, senza
scostamenti). Poi «Collega a Etsy» — il negozio viene rilevato
automaticamente; un negozio può essere legato a **una sola** organizzazione.

**Inbox-first:** Gli acquirenti non vengono mai creati ciecamente come
clienti. Le corrispondenze univoche o gli acquirenti abituali già assegnati
vengono collegati; tutto il resto appare come proposta nella inbox delle
integrazioni. Gli acquisti ospite senza account Etsy restano nello specchio
senza proposta.

**Webhook (opzionale):** Nel portale webhook di Etsy registrare l'URL
mostrato nel pannello con i quattro eventi order.* e salvare il secret
`whsec_…` nella scheda del plugin — i nuovi ordini appaiono subito. Senza
webhook tutto passa dalla sincronizzazione periodica (che resta sempre la
fonte affidabile).

**Segnalare la spedizione:** L'azione dello specchio trasmette numero di
tracking e corriere a Etsy (Etsy avvisa l'acquirente). I corrieri
sconosciuti vengono inviati come «other». Ogni ordine viene segnalato al
massimo una volta.

**Attenzione alla scadenza:** Il refresh token di Etsy scade 90 giorni dopo
l'ultimo utilizzo; il controllo di salute avvisa in tempo, dopo aiuta solo
ricollegarsi. Etsy non offre un ambiente di test — i test avvengono sul
negozio reale secondo l'API testing policy di Etsy (le tariffe sono
addebitate davvero).

*The term "Etsy" is a trademark of Etsy, Inc. This application uses the
Etsy API but is not endorsed or certified by Etsy, Inc.*
