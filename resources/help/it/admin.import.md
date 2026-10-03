---
title: "Import CSV"
topic: admin.import
version: 3
audience:
    - admin
    - geschaeftsfuehrung
    - personalverwaltung
    - teamleitung
    - buchhaltung
    - user
    - aussendienst
schema: process
related:
    - admin.handbook
    - admin.tenants
    - contacts.manage
---

## Scopo e contesto

La procedura guidata porta le anagrafiche in WorkDiary via CSV — con
analisi **prima** della scrittura e report errori completo. È la via
più rapida per rilevare un patrimonio esistente (clienti, utenti,
progetti, team, fornitori, materiali) in modo strutturato, senza
lasciare la qualità dei dati al caso.

## Prerequisiti

- Diritti di amministrazione.
- Un file CSV per entità; l'abbinamento colonne avviene nella
  procedura.
- Per dati dipendenti: il giusto **ordine** (prima clienti/team, poi
  progetti ecc.).

## Procedura consigliata

1. **Scegliere l'entità** (clienti, utenti, progetti, team,
   fornitori, materiali…).
2. **Caricare il CSV** — l'**analisi preliminare** verifica struttura
   e contenuti senza scrivere nulla.
3. **Controllare l'anteprima:** righe riconosciute, avvisi, errori.
4. **Confermare** — l'import gira come processo in background.
5. **Scaricare il CSV errori:** tutte le righe respinte con
   motivazione; correggere e reimportare.

![Procedura di import con scelta dell’entità, modello e analisi preliminare](media/administration/import-assistent.png)
*La procedura di import: scegliere l’entità, scaricare il modello, caricare il file — l’analisi non scrive nulla.*

## Assegnare nomi di colonna propri

Se l’importazione non riconosce l’intestazione di una colonna, l’esecuzione
mostra la scheda **Assegnazione delle colonne**. Scelga la colonna di
destinazione per ogni intestazione e salvi: il file viene ricontrollato
subito. Da quel momento l’assegnazione vale per ogni altro file dello stesso
tipo di importazione, anche per l’importazione clienti dall’elenco clienti.
Una proposta dell’IA precompila soltanto la scelta. Le assegnazioni salvate
si trovano nel menu dell’elenco importazioni alla voce **Assegnazioni di
colonne salvate**, dove si possono eliminare singolarmente.

## Esempio pratico

Durante il passaggio un'azienda importa prima un file di prova con
dieci clienti, verifica anteprima e mappatura, poi carica le 800
righe complete. Dodici righe finiscono motivate nel report errori,
vengono corrette e riprese al secondo giro.

## Errori tipici

- **Caricare tutto senza file di prova** — gli errori di mappatura si
  moltiplicano inutilmente.
- **Ignorare l'ordine:** progetti prima dei loro clienti falliscono
  su riferimenti mancanti.
- **Ignorare il report errori:** le righe errate non interrompono il
  giro — ma mancano dal patrimonio finché non vengono reimportate.

## Effetti e prossimi passi

Prima della conferma non si scrive **nulla** — analisi e anteprima
sono senza rischi. La cronologia mostra tutti i giri con stato,
filtrabile per entità e condizione. Poi: controllare a campione le
anagrafiche importate e unire i duplicati.

## Dati di movimento e di misura

Oltre ai dati anagrafici, l'importazione riprende anche dati correnti:
letture contatore, consumi ESG (attività, quantità, periodo e qualità dei
dati), misure di ispezione per ispezioni esistenti e posizioni delle
attrezzature da esportazioni telematiche. Per le posizioni, WorkDiary
segnala quando un'attrezzatura noleggiata lascia il luogo d'impiego.

## Timbrature e tempi di progetto dal calendario

Per timbrature e tempi di progetto può scegliere una fonte di calendario
collegata invece di un file (CalDAV, Google Calendar, Microsoft 365). Gli
eventi del periodo scelto vengono recuperati e verificati come un file
iCal, con anteprima, filtro per categorie e risoluzione delle serie.

L'importazione delle presenze accetta la colonna facoltativa **erfasst am**
(data e ora della registrazione originale). Serve al termine di registrazione
MiLoG; senza di essa il termine non viene verificato per quelle righe.
