---
title: "Importazione CSV"
topic: admin.import
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.tenants
---

Il wizard di importazione porta i dati anagrafici in WorkDiary via CSV,
con analisi prima della scrittura e rapporto errori completo. Flusso
tipico: scegli l'entità (clienti, utenti, progetti ecc.), carichi il
CSV e l'**analisi preflight** verifica struttura e contenuti senza
scrivere nulla, controlli l'anteprima e confermi; l'importazione gira
come job in background. Le righe errate non interrompono l'esecuzione
ma finiscono nel **CSV degli errori**, da correggere e reimportare.
Consiglio: importa prima un piccolo file di prova e rispetta l'ordine
— prima clienti/team, poi i dati dipendenti come i progetti.
