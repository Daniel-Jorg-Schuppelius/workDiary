---
title: "Import CSV"
topic: admin.import
version: 2
audience:
    - admin
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
