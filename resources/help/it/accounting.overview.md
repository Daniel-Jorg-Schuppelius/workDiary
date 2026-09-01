---
title: "Contabilità locale"
topic: accounting.overview
version: 2
audience:
    - admin
    - buchhaltung
modules:
    - module.finance
schema: process
related:
    - accounting.posting
    - accounting.closing
    - finance.datev-bookings
---

## Scopo e contesto

La contabilità locale tiene un proprio libro mastro in WorkDiary —
per organizzazioni senza software contabile separato. Non sostituisce
né i plugin contabili né la loro titolarità dei dati. Tre domande
restano rigorosamente separate: **titolarità di fatturazione** (chi
emette le fatture?), **titolarità delle anagrafiche** (chi tiene
clienti e fornitori?) e **titolarità delle scritture** (chi tiene il
mastro?) — per periodo guida WorkDiary oppure esattamente un sistema
esterno.

## Prerequisiti

- Ruolo **contabilità** o amministrazione.
- La scelta di un profilo: contabilità semplificata (EÜR) o partita
  doppia.
- Valuta base, esercizio e inizio delle scritture (data di taglio).
- Nessun sistema esterno con titolarità delle scritture nello stesso
  periodo.

## Procedura consigliata

1. Apri **Finanze → Configura contabilità** e scegli il profilo.
2. Imposta valuta base, esercizio e inizio delle scritture.
3. Esegui il **preflight**: verifica che l'organizzazione possa
   scrivere senza lacune dalla data di taglio.
4. **Attiva** la contabilità locale solo quando nessun punto è più
   rosso.
5. Da lì le scritture passano dal giornale (vedi «Scritture»), la
   chiusura dalla pagina di chiusura.

![Configurazione della contabilità locale con scelta del profilo e preflight](media/buchhaltung/buchhaltung-einrichtung.png)
*La configurazione: profilo contabile a sinistra, preflight a destra — si attiva solo senza punti rossi.*

## Esempio pratico

Un piccolo artigiano disdice il software contabile a fine anno: a
dicembre configura il profilo EÜR, completa il preflight e fissa
l'inizio delle scritture al 1° gennaio. I documenti di dicembre
restano nel vecchio sistema — da gennaio scrive WorkDiary.

## Errori tipici

- **Voler scrivere retroattivamente:** i documenti prima della data
  di taglio restano storia e non vengono ricontabilizzati.
- **Doppia titolarità delle scritture:** scrivere in parallelo nel
  vecchio sistema e in WorkDiary crea due verità — il preflight lo
  impedisce di proposito.
- **Forzare l'attivazione con punti rossi** — le lacune si presentano
  alla prima chiusura.

## Effetti e prossimi passi

Con l'attivazione WorkDiary diventa il mastro guida dalla data di
taglio: giornale, partite aperte e chiusura vi si appoggiano. Poi:
conoscere la logica di scrittura e l'ingresso documenti («Scritture»)
e pianificare la prima chiusura mensile.
