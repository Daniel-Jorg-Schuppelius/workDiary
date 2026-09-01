---
title: "Ricezione fatture elettroniche"
topic: finance.incoming-invoices
version: 1
audience: []
modules:
    - module.vertrieb
related:
    - invoices.manage
    - finance.datev-bookings
---

L'area riceve le fatture elettroniche in ingresso, le verifica e le
conduce attraverso un processo di approvazione documentato — senza
intaccare la sovranità di fatturazione del programma di contabilità o
di fatturazione titolare.

**Ricezione:** le fatture elettroniche arrivano tramite upload di file
o attraverso la ricezione e-mail — come XRechnung (XML) oppure
ZUGFeRD/Factur-X (PDF con XML incorporato). Tutti i canali
attraversano esattamente la stessa elaborazione. Il documento viene
archiviato nel DMS come documento di tipo fattura; l'originale
invariato resta l'unica fonte, la pagina di dettaglio lo rilegge a ogni
apertura. Non viene creata alcuna fattura locale.

**Duplicati:** un contenuto di file identico viene registrato una sola
volta per organizzazione — anche tra canali diversi (un upload dopo una
precedente ricezione via e-mail resta un duplicato).

**Validazione e coerenza:** ogni ricezione viene validata rispetto allo
schema XML e, se configurato, rispetto alle regole di verifica KoSIT
(EN 16931); viene indicato in modo trasparente se le verifiche erano
disponibili. Inoltre, il controllo delle divergenze avvisa in modo
visibile — mai in silenzio — in caso di numero di fattura già
registrato dello stesso emittente, di totali contraddittori (netto +
imposta ≠ lordo) e di esposizione dell'imposta senza identificativo
fiscale dell'emittente.

**Proposte:** per l'assegnazione, il sistema propone fornitori (tramite
partita IVA o somiglianza del nome), ordini (tramite il riferimento
d'ordine) e progetti (tramite riferimento progetto/acquirente) — come
candidati motivati. L'acquisizione resta al revisore; i dati
anagrafici non vengono mai creati o modificati automaticamente.

**Workflow di verifica:** una ricezione viene approvata, corredata di
richiesta di chiarimento oppure respinta (il rifiuto solo con
motivazione). Solo dopo l'approvazione di merito è possibile
l'autorizzazione al pagamento. Ogni decisione viene registrata
nell'audit trail con persona e momento.

**Consegna alla contabilità:** vengono consegnate solo le ricezioni
approvate o autorizzate al pagamento. La consegna è idempotente — una
seconda esecuzione non modifica nulla e non genera evidenze duplicate.

**Download XML:** l'XML della fattura può essere estratto in qualsiasi
momento in modo deterministico dall'originale (per ZUGFeRD
dall'allegato PDF). Ogni prelievo viene protocollato come evidenza con
checksum.
