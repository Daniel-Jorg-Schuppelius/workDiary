---
title: "Integrazioni Lexware: tariffa, matrice delle funzioni e consegna"
topic: lexware.supplements
version: 1
audience: []
modules:
    - module.vertrieb
related:
    - invoices.manage
    - articles.lexoffice
---

Sotto **Fatturazione → Integrazioni Lexware** registra la tariffa Lexware Office sottoscritta (S, M, L, XL oppure «Sconosciuta / contratto speciale») con fonte, data di conferma e — per gli accessi di prova — data di scadenza e tariffa successiva confermata. La pagina funziona senza connessione API.

**Matrice delle funzioni:** Per ogni funzione vede se nella Sua tariffa è **inclusa in Lexware**, se workDiary la offre come **integrazione** o se è **pianificata** (estensione). Con una tariffa sconosciuta non c'è alcuna indicazione certa su Lexware; le funzioni locali restano utilizzabili secondo i propri requisiti. Nel primo pacchetto workDiary integra per S fatture standard/elettroniche, preventivi e solleciti dall'esistente e per S/M/L le **fatture ricorrenti** tramite i piani di fatturazione.

**Attivare un'integrazione:** Solo le integrazioni attivate consapevolmente nel profilo tariffario risultano «Disponibile in workDiary». I requisiti sono il modulo «Vendite e fatturazione», la sovranità di fatturazione **workDiary** (un cliente fatturato esternamente non riceve una serie locale: il passaggio è un processo separato con data di efficacia) e il diritto di visualizzare le fatture. La tariffa è un orientamento, non un'autorizzazione; una tariffa superiore non toglie nulla.

**Elenco di consegna:** Sotto «Elenco di consegna Lexware» trova i documenti emessi dei clienti fatturati localmente nel periodo d'intestazione scelto, con stato fattura, invio e consegna separati. **Esporta** scarica l'originale congelato di ogni documento come PDF con SHA-256 e un elenco di assegnazione (CSV) come pacchetto: un download per Lei, non un presunto formato di importazione Lexware. **Conferma manualmente** convalida la consegna con utente, orario e nota. «Esportato» o «confermato» non significa mai «contabilizzato» o «pagato»; storno e nota di credito restano documenti propri con riferimento all'originale.

**Consegna automatica:** Il canale «automatico» richiede una chiave API propria (tariffa XL) e un canale di consegna verificato; fino ad allora l'esportazione manuale resta la via standard. Le consegne aperte restano visibili in caso di cambio tariffa.

**Diritti:** Le pagine sono visibili a chi ha «Elencare le fatture»; solo «Configurazione finanziaria» modifica il profilo tariffario; esportazione e conferma richiedono «Esportare le fatture».
