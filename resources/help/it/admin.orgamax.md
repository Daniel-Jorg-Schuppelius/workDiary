---
title: "orgaMAX contabilità"
topic: admin.orgamax
version: 1
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
---

orgaMAX contabilità viene collegato come plugin per organizzazione
tramite l'OpenAPI ufficiale (non orgaMAX ERP). orgaMAX resta il sistema
guida per le capacità attivate.

Connessione:

1. **Avviare un intento di connessione** (modalità pilota privata con
   chiave/segreto API o estensione pubblicata con segreto operatore).
   WorkDiary genera un URL di callback con token di stato.
2. Registrare l'URL come URL dell'estensione in orgaMAX e aprirla — orgaMAX
   aggiunge l'`iid`. Un `iid` estraneo senza intento valido non viene mai
   associato.
3. **Confermare esplicitamente** l'account rilevato; il preflight degli
   scope blocca in caso di autorizzazioni mancanti invece di attivare
   parzialmente.

Titolarità dei dati per capacità (clienti, fornitori, articoli,
fatturazione, pagamenti, spese, documenti): guida un solo sistema; lo
standard sicuro è la revisione manuale. Le anagrafiche si abbinano tramite
la inbox di integrazione — nessun dato ombra.

Fatturazione: le consegne rilasciate (Finanze → Consegne, destinazione
orgaMAX) creano al massimo UN ordine orgaMAX (marcatore origine +
riconciliazione invece di ripetizioni cieche). Conversione in fattura,
blocco irreversibile, invio e registrazione pagamenti sono azioni separate,
con permessi propri e auditate. Numero, stato, pagamento e PDF provengono
visibilmente da orgaMAX.

Il polling è budgetato con checkpoint (orario, configurabile); «Sincronizza
ora» rispetta gli stessi limiti. La consegna spese/ricevute resta bloccata
fino alla conferma del pilot receipt.
