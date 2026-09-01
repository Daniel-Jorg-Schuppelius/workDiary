---
title: "Lotto di scritture DATEV"
topic: finance.datev-bookings
version: 2
audience: []
modules:
    - module.finance
schema: process
related:
    - invoices.manage
    - finance.transfers
    - finance.reconciliation
    - roles.buchhaltung
---

## Scopo e contesto

Il lotto di scritture DATEV consegna allo studio fiscale fatture
emesse, note di credito e, in opzione, spese approvate di un periodo
chiuso come file DATEV verificabile (formato V700). Principio:
WorkDiary non crea **nessuna** contabilità, ma un lotto di consegna
pulito. Se un software di fatturazione esterno (DATEV o Lexoffice)
guida le fatture, queste **non** appartengono al lotto locale —
vengono escluse automaticamente e mostrate nella vista di controllo.

## Prerequisiti

L'amministrazione registra una volta la configurazione contabile
dell'organizzazione:

- numero consulente e mandante,
- piano dei conti (SKR03 o SKR04) e lunghezza dei conti,
- conto ricavi predefinito e conto separato per ricavi 0 % / esenti,
- la base dell'intervallo dei numeri debitore,
- l'abbinamento delle aliquote (19 %, 7 %, 0 %) alle chiavi di
  registrazione DATEV,
- il flag di blocco (GoBD) e il set di caratteri (di norma
  ISO-8859-1).

Il numero debitore può essere tenuto per cliente; se manca, viene
derivato in modo deterministico dalla base dell'intervallo e dal
numero cliente. Creare, finalizzare e scaricare i lotti spetta al
ruolo **contabilità** (e agli amministratori); la configurazione agli
amministratori.

## Procedura consigliata

1. **Creare il lotto:** scegliere il periodo, includere se serve le
   spese approvate — nasce una **bozza** con i documenti pronti.
2. **Controllare:** l'anteprima mostra la scrittura per documento —
   segno dare/avere, conto debitore e ricavi, chiave, numero
   documento, importo lordo — con il totale. Anagrafiche mancanti
   appaiono come **avviso**, chiavi mancanti come **errore**
   bloccante.
3. **Finalizzare:** solo ora nasce il file DATEV; viene registrata
   un'impronta SHA-256 e i documenti valgono come consegnati. Un
   lotto finalizzato è **immutabile**.
4. **Scaricare** e consegnare allo studio.

![Lotti di scritture DATEV con indicatori, configurazione e creazione lotto](media/buchhaltung/datev-stapel.png)
*La panoramica dei lotti: indicatori, configurazione, anagrafiche EXTF e «Crea lotto».*

## Esempio pratico

A inizio mese la contabilità crea il lotto del mese precedente: due
documenti avvisano di un numero debitore mancante — dopo la modifica
sul cliente gli avvisi spariscono, il lotto viene finalizzato e il
CSV va allo studio con la sua impronta.

## Errori tipici

- **Voler consegnare due volte la stessa fattura:** i documenti
  finalizzati sono bloccati — le correzioni passano da nota di
  credito o documento correttivo nel lotto successivo.
- **Ignorare gli avvisi:** le anagrafiche mancanti emergono
  altrimenti in studio.
- **Aspettarsi i giustificativi nel lotto:** PDF/foto non ne fanno
  parte; restano sulla pratica e vanno allo studio separatamente.

## Effetti e prossimi passi

Contano le fatture emesse e pagate con data documento nel periodo; le
note di credito diventano scritture inverse. Dopo la consegna: curare
l'abbinamento dei pagamenti ed esportare il periodo successivo solo
dopo la sua chiusura.
