---
title: "Regole di maggiorazione"
topic: admin.surcharge-rules
version: 3
keywords:
    - maggiorazione notturna
    - indennità notturna
    - maggiorazione domenicale
    - maggiorazione festiva
    - maggiorazione weekend
    - indennità di turno
    - voce retributiva
    - esportazione paghe
    - DATEV
    - Lexware
    - lavoro notturno
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.lohn
related:
    - exports.payroll
    - finance.transfers
    - admin.handbook
    - glossary.core
---

Le regole di maggiorazione definiscono le maggiorazioni notturne, del
fine settimana, dei giorni festivi e per fasce orarie personalizzate,
nonché le maggiorazioni per reperibilità in sede, reperibilità
telefonica e straordinari. Durante l'esportazione dei tempi, i tempi
vengono valutati di conseguenza e riportati su righe separate per
ciascuna voce retributiva.

Procedura tipica:

1. **Crea** apre la finestra di dialogo **Crea regola di
   maggiorazione**. In **Dati di base** inserisca **Codice** (univoco,
   ad es. «night»), **Denominazione** (ad es. «Maggiorazione
   notturna»), **Tipo** e **Maggiorazione (%)** (0–999,99).
2. Scegliere il **Tipo**: **Notte** (fascia oraria, anche oltre la
   mezzanotte, ad es. 22:00–06:00), **Sabato**, **Domenica**, **Giorno
   festivo** (festività legali in automatico), **Personalizzato**
   (fascia oraria libera), **Reperibilità in sede**, **Reperibilità
   telefonica** oppure **Straordinari**. Per Notte e Personalizzato
   definisce la **Fascia oraria** con **Fascia da** e **Fascia a**.
3. In **Trasferimento paghe** indicare facoltativamente la **Voce
   retributiva** per DATEV/Lexware (ad es. «2010») e la **Priorità**.
   Con **Esente fino a (%)** e **Voce salariale quota imponibile**
   suddivide una maggiorazione oltre il limite esente su due voci.
4. In **Validità** impostare facoltativamente **Valida dal**/**Valida
   fino al** e attivare **La regola è attiva**; in **Condizioni** limita
   la regola a **Team**, **Sedi** o **Tipi di turno**.

Regole importanti:

- In caso di regole sovrapposte dei tipi Notte, Sabato, Domenica,
  Giorno festivo e Personalizzato prevale la **percentuale più alta** –
  le maggiorazioni non si sommano. A parità decide la priorità.
- **Reperibilità in sede** valuta le ore delle reperibilità registrate,
  **Reperibilità telefonica** le voci di tempo del tipo Reperibilità e
  **Straordinari** le ore oltre il monte ore mensile previsto. Questi
  tre tipi non richiedono una fascia oraria, non vengono compensati con
  le altre maggiorazioni e compaiono su righe proprie. Per essi
  l'esportazione attualmente non valuta né la validità né le
  condizioni.
- Le condizioni limitano una regola: vuoto = vale per tutti; più
  condizioni sono collegate con E logico, all'interno di una lista
  basta una corrispondenza. La sede viene riconosciuta tramite le
  timbrature al terminale — senza un contesto determinabile, una regola
  condizionata non si applica. Le sedi possono avere una propria
  regione di festività (maggiorazione festiva presso il luogo di
  intervento).
- Le modifiche hanno effetto sulle **esportazioni future**; le
  esportazioni già generate restano invariate (correzione tramite
  nuova esportazione). Solo un ricalcolo sottoposto ad audit, eseguito
  dalla gestione tecnica, rivaluta i periodi passati — mai una modifica
  silenziosa delle regole.

Autorizzazioni: **Vedere le regole di maggiorazione** mostra l'elenco;
creare, modificare ed eliminare regole possono solo le persone con il
diritto **Gestire le regole di maggiorazione**.
