---
title: "Importare elenchi e confrontare l’elenco clienti"
topic: service-plan.import
version: 1
keywords:
    - importa elenco
    - Excel
    - CSV
    - elenco annuale
    - elenco per periodi
    - confronto clienti
    - DATEV
    - elenco mandanti
audience: []
related:
    - service-plan.overview
    - customers.deadlines
---

## Importare un elenco

Importi elenchi Excel o CSV esistenti con **Importa elenco** nel piano dei
servizi. Prima un’**anteprima** verifica ogni riga, poi importa.

- **Elenco annuale:** numero cliente, servizio, stato, nota; facoltativi
  documenti ricevuti, obbligatorio e responsabile.
- **Elenco per periodi:** numero cliente, servizio e una colonna per periodo
  (gen … dic, T1 … T4 o S1, S2) con lo stato.
- Se manca la colonna «servizio», scelga il servizio per l’intero elenco.
- Le colonne con il nome di una caratteristica delle scadenze clienti (ad es.
  periodicità IVA) riprendono le caratteristiche come nuova versione.

L’abbinamento **non tira mai a indovinare**: clienti solo tramite il numero
cliente esatto, servizi solo se corrisponde esattamente un modello o un tipo
di voce, responsabili solo tramite nome o e-mail di un membro. Una riga con un
numero cliente sconosciuto non crea nulla e compare nell’anteprima.

Vengono ripresi l’ambito di servizi mancante (quello esistente resta), le
caratteristiche, la nota e «documenti ricevuti» del periodo in corso (solo
dove non c’è ancora nulla) e «non dovuto». A richiesta lo stato del periodo in
corso diventa un ordine dove non ne esiste ancora uno.

## Confrontare l’elenco clienti

**Confronta elenco clienti** confronta la sua anagrafica con un elenco di un
altro programma, ad esempio l’esportazione mandanti DATEV (numero mandante,
nome). L’anteprima mostra proposte:

- **Nuovo:** numero sconosciuto — creare il cliente.
- **Rinominato:** numero noto, nome diverso — rinominare il cliente.
- **Manca nell’elenco:** solo a titolo informativo.

Nulla viene sovrascritto o creato prima che spunti una proposta.
