---
title: "Contabilità locale"
topic: accounting.overview
version: 3
keywords:
    - libro mastro
    - contabilità generale
    - tenuta contabile
    - configurare contabilità
    - partita doppia
    - contabilità di cassa
    - data inizio contabilità
    - sostituire software contabile
    - contabilità integrata
    - piano dei conti
    - SKR03
    - SKR04
audience:
    - admin
    - geschaeftsfuehrung
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

1. Apra **Vendite e fatturazione** → **Contabilità** → **Configurazione** e
   scelga il profilo.
2. Imposti valuta base, esercizio e inizio delle scritture.
3. Esegua il **preflight**: verifichi che l'organizzazione possa
   scrivere senza lacune dalla data di taglio.
4. **Attivi** la contabilità locale solo quando nessun punto è più
   rosso.
5. Da lì le scritture passano dal giornale (si veda «Scritture»), la
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

## Piano dei conti

I conti della contabilità locale si gestiscono da **Vendite e fatturazione** →
**Contabilità** → **Piano dei conti**. La voce compare non appena la Sua
organizzazione tiene o ha tenuto la contabilità locale.

- **Piano dei conti da modello:** scelga in **Modello** un estratto dello SKR03
  o dello SKR04 e clicchi su **Applicare il modello**. Vengono creati conti,
  codici IVA e regole di registrazione corrispondenti, così la posta contabile è
  subito utilizzabile; conti e regole esistenti restano invariati. Il modello è
  un punto di partenza per la Germania – scelta dei conti e corrispondenza
  fiscale vanno verificate prima della prima registrazione.
- **Creare conto** e **Modificare conto:** **Conto** (il numero di conto, unico
  per organizzazione), **Denominazione**, **Tipo di conto**, **Sezione del
  saldo** (precompilata dal tipo di conto), **Conto DATEV** (solo per
  l'esportazione), le caratteristiche **Partite aperte**, **Banca**, **Cassa**,
  **Transitorio** e **Centro di costo obbligatorio**, per la contabilità per
  cassa **Riga entrate-uscite** e **Quota deducibile (%)**, oltre a una
  **Descrizione**. Le registrazioni su conti con la caratteristica **Partite
  aperte** compaiono nell'elenco delle partite aperte.
- **Disattivare** invece di eliminare: un conto disattivato conserva le sue
  registrazioni, ma non è più selezionabile per quelle nuove. L'elenco mostra
  per impostazione predefinita **solo attivi**; la ricerca (numero,
  denominazione) e il filtro per tipo di conto restringono ulteriormente.
- **Importa piano dei conti:** un file CSV con riga di intestazione e le
  colonne `number`, `name` e `type`, facoltative `normal_balance`,
  `is_open_item`, `datev_account`, `euer_category` e `deductible_percent`. I
  numeri esistenti vengono aggiornati, i nuovi conti creati, non viene
  eliminato nulla; le righe errate vengono ignorate e contate.
- **Codici IVA:** se esistono codici IVA, la pagina li elenca con i rispettivi
  campi della dichiarazione IVA tedesca. Con **Modifica** Lei assegna un campo
  a **Base imponibile** e uno a **Imposta** – un ausilio di riconciliazione,
  non il modulo.

**Autorizzazione:** consultare con **Consultare la contabilità**; modello,
importazione e ogni modifica a conti e codici IVA con **Configurare la
contabilità**.
