---
title: "Anagrafica articoli"
topic: articles.master
version: 6
keywords:
    - articoli
    - creare articolo
    - catalogo prodotti
    - codice articolo
    - SKU
    - varianti
    - EAN
    - GTIN
    - unità di misura
    - codice doganale
    - voce doganale
audience: []
modules:
    - module.lager
related:
    - articles.lexoffice
    - materials.manage
    - inventory.stock
    - warehouses.manage
---

L'anagrafica articoli è l'elenco centrale di prodotti, materiali e
servizi dell'azienda ed è la base per magazzino, acquisti, produzione e
vendita. Ogni articolo contiene numero, tipo, stato, unità base, GTIN e
prezzi standard di acquisto e vendita; nella vista di dettaglio gestisce
opzioni con valori (es. taglia, colore), unità aggiuntive con fattore di
conversione e varianti, che nascono dalla combinazione dei valori delle
opzioni e ricevono automaticamente una SKU. Creazione e modifica
avvengono tramite finestra di dialogo; invece di eliminare, imposti lo
stato "Retired" — l'eliminazione è possibile solo senza dati dipendenti.
Crei le varianti solo quando opzioni e valori sono completi.

Per i documenti doganali, gli articoli riportano in «Dogana ed esportazione»
il codice doganale (da 6 a 11 cifre; spazi e punti vengono rimossi), il paese
d’origine come codice ISO e il peso netto per unità.

## Magazzino e valutazione

In «Magazzino e valutazione» Lei definisce nella finestra dell’articolo un **metodo di valutazione** proprio; senza scelta vale il metodo dell’organizzazione. Il passaggio a FIFO o FEFO crea uno strato iniziale per la giacenza esistente; il ritorno alla media mobile è possibile solo quando non resta giacenza dell’articolo negli strati.

Con un **prefisso dei numeri di serie** l’articolo conta numeri di serie propri (prefisso e numero progressivo, su richiesta con cifra di controllo); senza prefisso vale la numerazione dell’organizzazione.

Le **unità** aggiuntive valgono per movimenti di magazzino, scansione, ordini e ricevimento merci: la quantità può essere inserita nell’unità, la registrazione avviene nell’unità base. La proprietà «scadenza obbligatoria» richiede lotto e data di scadenza al ricevimento merci.

Se un gestionale di magazzino gestisce i numeri articolo (pagina «Numerazioni», numerazione «Articolo»), numero articolo e GTIN sono di sola lettura nella finestra; i nuovi articoli e varianti ricevono il numero con la sincronizzazione.

**Classe fiscale:** nel modulo articolo registrate la classe fiscale (ordinaria, ridotta, maggiorata). Viene salvata con l’articolo, ripresa nell’importazione e confrontata per duplicati e unioni; l’aliquota di un documento resta fissata sulla riga.

**Luogo della prestazione:** Per le prestazioni scelga nel modulo dell’articolo
il tipo di luogo della prestazione (§ 3a UStG): **Generale**, **Prestazione del
catalogo** (non imponibile per consumatori extra UE) o **Prestazione relativa a
un immobile** (imposta nel luogo dell’immobile, per tutti i clienti). Vuoto
significa il valore predefinito dell’organizzazione. La pagina di dettaglio
mostra il tipo scelto; l’importazione CSV lo riprende nella colonna
`place_of_supply_kind` (valori `general`, `catalog`, `property`, intestazione ad
esempio «Leistungsort»). Come determina il regime fiscale è spiegato
nell’argomento di aiuto **Fatture & documenti**.

## Produzione

In «Produzione» sceglie il **ciclo di lavoro standard** dell'articolo: una
versione pubblicata di un modello di procedura attivo. Gli ordini e la
pianificazione di produzione realizzano poi l'articolo con esso, finché un
ordine non ne sceglie una propria; anche la pianificazione dei fabbisogni lo
legge. La sezione compare non appena esistono versioni di procedura pubblicate.
