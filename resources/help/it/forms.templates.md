---
title: "Gestire i modelli di modulo"
topic: forms.templates
version: 3
keywords:
    - creare modulo
    - editor di moduli
    - form builder
    - creare checklist
    - campi del modulo
    - tipi di campo
    - menu a tendina
    - campo obbligatorio
    - attivare modulo
    - archiviare modulo
    - moduli personalizzati
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
modules:
    - module.forms
related:
    - forms.fill
    - glossary.core
---

I modelli di modulo definiscono checklist e rilevazioni senza codice –
tramite la definizione dei campi. Li trova in **Sistema** → **Regole e
processi** → **Modelli di modulo** oppure tramite il pulsante **Modelli
di modulo** nella panoramica **Moduli**.

Procedura tipica:

1. **Crea modello**: **Nome**, **Descrizione**, facoltativamente **Valido
   dal** e **Valido fino al** nonché **Assegnazione: tipo incarico** e
   **Assegnazione: cliente** (con «tutti» il modello vale ovunque).
   Seguono i **Campi**; con **Aggiungi campo** se ne aggiunge un altro.
   Per ogni campo: **Etichetta del campo**, **Tipo di campo** e
   **Obbligatorio**, a seconda del tipo le **Opzioni** (separate da
   virgola), l’**Unità** o l’**Intervallo di valori** (Min, Max),
   facoltativamente un **Testo di aiuto** e una condizione **Visibile
   se**, con cui un campo compare solo quando un altro campo ha un
   determinato valore.
2. **Attiva**: i nuovi modelli partono con lo stato «Bozza»; solo con lo
   stato «Attivo» il modello può essere compilato.
3. **Archivia**: toglie il modello dalla selezione di compilazione – i
   moduli compilati restano leggibili. Un modello archiviato può essere
   riattivato.

Tipi di campo: «Testo», «Testo su più righe», «Numero», «Casella»,
«Scelta», «Scelta multipla», «Data», «Data e ora», «Scala», «Foto»,
«File», «Firma», «Sezione» e «Misura». Non inserisce una chiave di campo
propria; ogni etichetta del campo può comparire una sola volta per
modello.

Stati importanti: «Bozza» → «Attivo» → «Archiviato».

Principio dello snapshot: ogni modulo compilato congela la definizione
dei campi al momento della compilazione. Le modifiche ai campi valgono
quindi **solo per i moduli compilati in seguito** – quelli vecchi restano
invariati e analizzabili. Anche **Elimina** su un modello non rende
illeggibili i moduli compilati.

Autorizzazioni: può creare, modificare, attivare, archiviare ed eliminare
i modelli di modulo chi dispone del permesso **Gestire i modelli di
modulo** (per impostazione predefinita i capi team).

Suggerimento: il sistema ricava l’assegnazione interna di un campo dalla
sua etichetta. Mantenga quindi invariate le etichette dei campi se
desidera confrontare moduli compilati su più versioni di un modello.
