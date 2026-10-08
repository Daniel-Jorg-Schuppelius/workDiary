---
title: "Scorte di licenze"
topic: finance.license-stock
version: 1
keywords:
    - gestione licenze
    - chiavi di licenza
    - numero di serie
    - chiave di attivazione
    - licenze software
    - product key
    - vendere licenza
    - pacchetto licenze
    - rivendita licenze
    - scorta minima
    - importare chiavi
audience: []
modules:
    - module.reselling
related:
    - finance.resale
---

Le **scorte di licenze** gestiscono le licenze che Lei acquista in pacchetti
e rivende singolarmente, ad esempio dieci licenze VPN, ognuna composta da
più chiavi che vanno insieme. Si contano sempre licenze, mai chiavi.

## Prodotto e pacchetto

1. **Creare prodotto di licenza:** nome, produttore e le chiavi per licenza,
   ad es. «Numero di serie» e «Chiave di attivazione». La **scorta minima**
   stabilisce quando compare «Da riordinare»: vuoto = mai, 0 = solo a
   esaurimento. Facoltativamente colleghi un articolo del catalogo articoli
   (anagrafica articoli o programma di contabilità collegato).
2. **Creare pacchetto di licenze:** riferimento del pacchetto, data di
   acquisto, fornitore, quantità e, facoltativamente, il documento di
   acquisto. Vengono create tante licenze numerate quante acquistate,
   inizialmente senza chiavi. I riacquisti sono nuovi pacchetti.
3. **Registrare le chiavi:** singolarmente con «Gestire chiavi» o come CSV
   con una riga per licenza (modello nel pacchetto). L’anteprima mostra righe
   ed errori; viene applicato solo un file privo di errori e solo dopo la
   Sua conferma.

## Stato di una licenza

- **Incompleta:** manca almeno una chiave; non vendibile.
- **Disponibile:** tutte le chiavi presenti, non venduta, non bloccata.
- **Venduta:** assegnata a un solo cliente, con il set completo di chiavi.
- **Bloccata:** tolta dalla vendita con un motivo.

Le acquistate sono sempre la somma di disponibili, vendute, incomplete e
bloccate. La giacenza è attuale e non dipende dal periodo nell’intestazione.

## Vendere e correggere

- **Vendere licenza** propone la licenza disponibile più vecchia. La vendita
  viene documentata ma non crea alcuna fattura; un numero di fattura è solo
  un riferimento. Con un cliente finale come titolare, il destinatario della
  fattura resta il cliente.
- **Correggere vendita** assegna la stessa licenza senza interruzioni a un
  altro cliente; entrambi restano nello storico.
- **Ritirare vendita** blocca la licenza, perché la sua chiave potrebbe essere
  già stata usata. Può essere liberata solo con un motivo e la Sua conferma.

## Proteggere le chiavi

Le chiavi sono salvate cifrate. Elenchi, scheda cliente e moduli non le
mostrano mai; il testo in chiaro compare solo tramite «Mostrare chiavi», con
il permesso apposito «mostrare le chiavi di licenza in chiaro», che non viene
assegnato automaticamente ad alcun ruolo. Ogni accesso viene registrato,
senza il valore.
