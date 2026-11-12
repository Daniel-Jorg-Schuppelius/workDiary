---
title: "Smaltimento e attestati"
topic: disposal.overview
version: 1
keywords:
    - RAEE
    - rifiuti elettronici
    - vecchi apparecchi
    - certificato di distruzione
    - distruzione dati
    - triturazione dischi
    - cancellazione dati
    - DIN 66399
    - codice CER
    - formulario rifiuti
    - smaltitore autorizzato
audience: []
modules:
    - module.entsorgung
related:
    - assets.fleet
    - customer-portal.overview
---

La pratica di smaltimento gestisce lo smaltimento dei vecchi apparecchi
come processo tracciabile: ritiro presso il cliente, elenco apparecchi con
codici rifiuto (AVV/CER), trattamento dei supporti dati secondo la norma
DIN 66399, consegna all'impresa di smaltimento certificata con attestati e
chiusura con attestato cliente nel portale.

**Catena di stati:** Creata → Ritirata → In trattamento → Consegnata allo
smaltitore → Conclusa. La fase di trattamento può essere saltata se non
sono coinvolti supporti dati. L'annullamento è possibile fino alla
chiusura; è definitivo e viene registrato con motivazione nella catena di
tracciabilità.

**Elenco apparecchi:** Ogni posizione riporta categoria,
produttore/modello, numero di serie, quantità, peso e codice rifiuto
(AVV/CER). La classificazione «pericoloso» viene derivata automaticamente
dall'asterisco nel codice rifiuto — non viene mai impostata a mano. Le
posizioni sono modificabili solo fino alla consegna allo smaltitore.

**Asset sulla posizione:** nella finestra della posizione si
può scegliere un asset in esercizio; la selezione precompila denominazione, produttore,
modello e numero di serie. Pratica e attestato mostrano l’asset. La
chiusura mette fuori esercizio gli asset collegati. Se
un asset è ancora consegnato, resta in esercizio e la pratica lo segnala. Nella
scheda dell’asset, «Smaltisci» apre una nuova pratica di smaltimento con
l’asset come posizione preparata.

**Foto:** per ogni posizione apparecchio si caricano foto (JPG, PNG, WebP)
finché la pratica è modificabile. Dopo la consegna allo smaltitore le foto
non si possono più aggiungere né eliminare. Con «Mostra le foto
nell’attestato per il cliente» nella pratica, nel PDF dell’attestato
compaiono fino a due foto per posizione.

**Trattamento dei supporti dati:** Per ogni apparecchio che contiene
supporti dati viene documentato il trattamento — tipo di supporto,
procedura (ad es. cancellazione software, smagnetizzazione, triturazione o
rimozione per la distruzione), categoria di materiale DIN 66399 con
livello di sicurezza, oltre all'esecutore e a un riferimento di attestato.
La categoria di materiale viene precompilata in base al tipo di supporto.

**Consegna allo smaltitore:** Le consegne all'impresa di smaltimento
certificata vengono registrate con tipo di attestato (ad es. bolla di
presa in carico, formulario di accompagnamento, attestato di smaltimento),
numero documento, data di consegna e riferimento del certificato EfbV. Un
documento caricato viene archiviato come documento DMS.

**Chiusura:** La verifica di chiusura della pratica richiede quattro
condizioni — almeno una posizione apparecchio, la firma di presa in carico
del cliente, un trattamento documentato per ogni apparecchio con supporti
dati e, per i rifiuti pericolosi, un attestato dello smaltitore. Alla
chiusura l'attestato cliente viene generato come PDF, pubblicato nel
portale clienti e gli asset collegati vengono dismessi. Chiusura e
annullamento richiedono il permesso «Concludere e annullare le pratiche di
smaltimento».

**Report:** Il report di smaltimento valuta le pratiche concluse nel
periodo scelto — quantità smaltite per cliente, per mese e per codice
rifiuto (AVV/CER), ognuna con la quota pericolosa.
