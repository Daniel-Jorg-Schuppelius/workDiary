---
title: "Fatturazione a contatore"
topic: metering.billing
version: 1
keywords:
    - fatturazione a consumo
    - pay per use
    - costo copia
    - fatturazione copie
    - franchigia copie
    - prezzi a scaglioni
    - canone base
    - ore di funzionamento
    - addebito per consumo
audience: []
related:
    - invoices.manage
    - assets.fleet
    - contacts.manage
---

La fatturazione a contatore liquida i contratti legati al consumo — copie
sulla multifunzione, ore di esercizio, kWh.

**Accordo:** Per ogni contatore si stabilisce quale cliente viene fatturato,
con quale cadenza, con quale canone base, quale quota inclusa e quale prezzo
unitario. Sono possibili prezzi a scaglioni; lo scaglione vale per il
consumo del periodo.

**Consumo:** Si fattura la differenza tra l’ultima lettura *precedente* al
periodo e l’ultima lettura *nel* periodo — non la somma dei consumi
registrati, che comprenderebbe anche il tempo precedente. Se manca una
lettura, il periodo non viene fatturato anziché stimato.

**Esecuzione:** L’esecuzione mensile crea bozze di fattura, non fatture
inviate — l’approvazione resta alla persona. Ogni periodo viene fatturato
una sola volta; una seconda esecuzione non genera duplicati.

## Scaglioni, stato e progetto

**Scaglioni di quantità:** la finestra dell'accordo offre righe con **da
quantità** e **prezzo per unità**. Il primo scaglione parte da 0; le righe
vuote vengono ignorate. Con gli scaglioni vale il prezzo di ciascuno scaglione
al posto del prezzo unitario, e il calcolo è progressivo sul consumo al netto
della quantità gratuita: un'unità in più non rende mai la fattura meno cara.
L'elenco mostra allora «Scaglioni» al posto del prezzo unitario.

**Stato:** *Attivo* viene fatturato; l'elaborazione salta *In pausa* e
*Terminato*, e la fatturazione immediata è offerta solo per gli accordi
attivi. Dopo una pausa l'elaborazione recupera a partire dalla data di
**prossima fatturazione**: se il periodo di pausa non va fatturato, sposti in
avanti la data prima di riattivare.

**Progetto:** il progetto scelto viene assegnato alle bozze di fattura
dell'elaborazione.
