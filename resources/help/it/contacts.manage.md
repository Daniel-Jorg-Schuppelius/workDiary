---
title: "Clienti & fornitori"
topic: contacts.manage
version: 2
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - projects.manage
    - invoices.manage
    - admin.import
    - communication.notes
---

## Scopo e contesto

Clienti e fornitori sono i dati anagrafici centrali di WorkDiary:
progetti, commesse, fatture, comunicazione, trasferte e analisi
dipendono da loro. Anagrafiche pulite decidono se i processi
successivi — dalla registrazione dei tempi alla consegna DATEV —
funzionano senza rilavorazioni.

## Prerequisiti

- Il diritto di gestire clienti o fornitori (di norma amministrazione
  o vendite).
- Per l'importazione al posto dell'inserimento manuale: la procedura
  guidata CSV.
- Identificativi esterni (numero debitore, codici delle integrazioni
  di fatturazione) se si consegnano documenti.

## Procedura consigliata

1. **Cercare prima di creare:** verifica se il partner esiste già —
   così non nascono duplicati. I duplicati esistenti si possono
   unire; la cronologia segue.
2. Crea il contatto con nome, indirizzo e referenti.
3. Completa dati di pagamento e fatturazione e gli identificativi
   esterni — guidano fatturazione e consegna contabile.
4. Collega progetti, sedi e accordi man mano che nascono.

![Elenco clienti con numeri, contatti, tariffe orarie e numero di progetti](media/kunden/kundenliste.png)
*L’elenco clienti: anagrafica, tariffa oraria e progetti collegati per partner.*

## Esempio pratico

Un fornitore IT crea «Müller GmbH» con indirizzo di fatturazione,
termini di pagamento e numero debitore dello studio. Quando più tardi
nasce il primo lotto DATEV, nessun documento è bloccato da anagrafiche
mancanti.

## Errori tipici

- **Creare duplicati** perché nessuno ha cercato prima — analisi e
  cronologia si frammentano.
- **Cancellare relazioni storiche:** meglio disattivare o archiviare i
  contatti inutilizzati; documenti e tempi restano tracciabili.
- **Cambiare i dati di fatturazione «al volo»:** le modifiche valgono
  per il futuro; i documenti già creati mantengono volutamente lo
  stato documentato.

## Effetti e prossimi passi

Le modifiche anagrafiche valgono solo in avanti — le consegne chiuse
restano invariate. Poi: creare i progetti del cliente, controllare i
dati di fatturazione e usare l'import CSV per grandi quantità.
