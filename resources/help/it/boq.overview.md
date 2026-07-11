---
title: "Computi metrici GAEB"
topic: boq.overview
version: 1
audience: []
related:
    - projects.manage
    - invoices.manage
---

I computi metrici (LV, Leistungsverzeichnisse) rappresentano in forma
strutturata le prestazioni edili — dall'interscambio dati GAEB
importato, passando per misurazioni e calcolo, fino all'esportazione
dello stato attuale.

**Import con preflight:** vengono letti file GAEB-DA-XML della
versione 3.x nelle fasi di scambio da X81 a X86 (computo metrico,
preventivo di costo, richiesta di offerta, presentazione dell'offerta,
offerta alternativa, conferimento dell'incarico). Prima della
scrittura, un preflight verifica versione, fase di scambio, struttura,
univocità dei numeri d'ordine nonché la plausibilità di quantità e
unità. I rilievi bloccanti generano soltanto un protocollo di
importazione — non viene scritto nulla. Una reimportazione in un
computo esistente si interrompe se sovrascriverebbe posizioni con
riferimenti di esecuzione o di contabilizzazione.

**Struttura e stati dei prezzi:** un computo è composto da testata,
sezioni gerarchiche con numeri d'ordine e posizioni con testo breve e
testo esteso, quantità, unità e prezzo unitario. Ogni importazione
deposita snapshot dei prezzi, cosicché gli stati di prezzo precedenti
restano tracciabili. Un computo può essere assegnato a un progetto; le
posizioni possono essere collegate ad articoli o materiali.

**Misurazioni e calcolo consuntivo:** gli avanzamenti vengono
registrati in modo additivo per posizione (quantità, fonte, nota). Le
posizioni con la prima misurazione passano automaticamente a «in
lavorazione». Il calcolo consuntivo mette a confronto il previsto
(quantità prevista × prezzo unitario), l'effettivo (quantità misurata
× prezzo unitario), la prestazione residua e il grado di avanzamento —
è un'analisi e non sostituisce la fatturazione.

**Workflow:** il computo e le singole posizioni attraversano
transizioni di stato direzionate, dalla gara d'appalto attraverso
offerta e incarico fino a esecuzione e chiusura; i salti non validi
vengono respinti. Le varianti in corso d'opera vengono gestite come
posizioni proprie; la vista della prestazione residua mostra ciò che è
ancora aperto.

**Export:** lo stato attuale del computo può essere scaricato come
GAEB-DA-XML in una fase di scambio selezionabile (predefinita:
conferimento dell'incarico). L'esportazione è deterministica e viene
protocollata con hash del contenuto — lo stesso stato produce in modo
riproducibile lo stesso hash.
