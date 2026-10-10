---
title: "Clienti & fornitori"
topic: contacts.manage
version: 4
keywords:
    - anagrafica clienti
    - anagrafica fornitori
    - creare un cliente
    - creare un fornitore
    - debitore
    - creditore
    - numero debitore
    - unire duplicati
    - importare clienti
    - rubrica
    - partner commerciale
    - CRM
    - portale clienti
    - accesso al portale
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

1. **Cercare prima di creare:** verifichi se il partner esiste già —
   così non nascono duplicati. I duplicati esistenti si possono
   unire; la cronologia segue.
2. Crei il contatto con nome, indirizzo e referenti.
3. Completi dati di pagamento e fatturazione e gli identificativi
   esterni — guidano fatturazione e consegna contabile.
4. Colleghi progetti, sedi e accordi man mano che nascono.

![Elenco clienti con numeri, contatti, tariffe orarie e numero di progetti](media/kunden/kundenliste.png)
*L’elenco clienti: anagrafica, tariffa oraria e progetti collegati per partner.*

**Comunicazione:** registri chiamate, e-mail e impegni come nota di
comunicazione sul cliente o sul fornitore. Le note compaiono nella pagina di
dettaglio e nell’elenco centrale delle note; un riscontro di accesso ai dati
relativo a un fornitore le elenca con numero e periodo.

**Accessi al portale:** nella sezione **Accessi al portale** della scheda
cliente invita i referenti nel portale clienti con **Invita accesso**; il
contatto imposta autonomamente la password tramite il link dell'invito. Finché
l'invito è aperto o scaduto, è disponibile **Invia di nuovo l'invito**. Per
gli accessi attivi, **Reimpostare l’accesso** reimposta l'accesso dopo una
richiesta di conferma: la password precedente non è più valida da subito,
tutte le sessioni vengono chiuse e il contatto riceve un nuovo invito; i
metodi a due fattori configurati restano attivi. Se il contatto ha solo
dimenticato la password, non serve: la reimposta autonomamente nella pagina di
accesso del portale tramite **Password dimenticata?**. **Disattiva**
disconnette subito l'accesso e blocca il login, **Riattiva** annulla il
blocco. Le aree visibili per un accesso sono stabilite dalla configurazione
del portale del cliente.

Se un contatto ha perso tutti i metodi a due fattori e i codici di recupero, **Reimposta il secondo fattore** rimuove tutti i metodi dopo una conferma della password e una richiesta di conferma e termina tutte le sessioni. Il contatto riceve un’e-mail al riguardo e accede poi con la propria password; se la Sua organizzazione richiede l’autenticazione a due fattori, la configura nuovamente in quel momento. Verifichi prima la sua identità, ad esempio richiamandolo.

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
