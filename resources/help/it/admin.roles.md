---
title: "Ruoli & permessi"
topic: admin.roles
version: 2
audience:
    - admin
schema: process
related:
    - admin.handbook
    - admin.security
    - org.members
    - roles.admin
---

## Scopo e contesto

La gestione dei permessi stabilisce chi può vedere e fare cosa in
WorkDiary. Si divide in quattro aree: **permessi** (catalogo in sola
lettura di diritti granulari nello schema `risorsa.azione`, p. es.
`month.approve`), **ruoli** (pacchetti di permessi, adattabili per
organizzazione), **gruppi** (puro raggruppamento di visualizzazione,
senza effetto funzionale) e **membri** (assegnazione dei ruoli).

## Prerequisiti

- Diritti di amministrazione dell'organizzazione.
- Un account di prova senza diritti admin per verificare davvero i
  ritagli.
- Chiarezza sui profili di lavoro (esterni, caposquadra,
  contabilità…).

## Procedura consigliata

1. **Creare o copiare un ruolo** — partire da un ruolo esistente
   evita tentativi falliti.
2. **Ritagliare i permessi:** meglio un ruolo stretto in più che un
   diritto omnibus (principio del minimo privilegio).
3. **Assegnare ai membri.**
4. **Verificare con l'account di prova** prima di estendere il ruolo.

![Gestione dei ruoli con ruoli di sistema e numero di permessi](media/administration/rollen.png)
*La gestione dei ruoli: i ruoli di sistema dell’organizzazione con il numero dei permessi.*

## Esempio pratico

Per una nuova impiegata il ruolo «back office» viene copiato da
«caposquadra», privato dei diritti di approvazione e assegnato. Il
test con l'account di controllo mostra: approvazioni mensili
invisibili, creazione commesse funzionante — esattamente come voluto.

## Errori tipici

- **Assegnare un ruolo admin globale:** un ruolo senza legame con
  un'organizzazione agisce **su tutta la piattaforma**, su tutti i
  tenant. Spetta esclusivamente al gestore e non va mai assegnato via
  diritti delegabili o interfaccia dell'organizzazione — rischio di
  escalation.
- **Aspettarsi un bypass admin:** i moduli sensibili (protezione
  dati, whistleblowing) richiedono assegnazione esplicita — anche
  agli admin. È voluto.
- **Lasciar proliferare ruoli omnibus:** comodi, ma quasi
  irriducibili in seguito.

## Effetti e prossimi passi

Le modifiche ai ruoli hanno effetto immediato su tutti i membri
assegnati — anche su menu, contenuti di aiuto e accesso ai moduli.
Poi: curare le assegnazioni in «Membri» e leggere le note di
sicurezza nel manuale di amministrazione.
