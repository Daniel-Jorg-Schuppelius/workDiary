---
title: "Gestire i progetti"
topic: projects.manage
version: 2
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - contacts.manage
    - time-entries.start
    - timesheets.manage
    - finance.transfers
---

## Scopo e contesto

I progetti raccolgono tutto ciò che appartiene a un'iniziativa:
cliente, durata, responsabili, attività, tappe, tempi registrati e
regole di fatturazione. Sono la parentesi tra registrazione dei tempi
e fatturazione — ciò che è impostato bene sul progetto non va mai
corretto registrazione per registrazione.

## Prerequisiti

- Un cliente esistente (vedi clienti & fornitori).
- Il diritto di gestire i progetti.
- Per la fatturazione: regole chiarite (tariffa oraria, forfait,
  fatturabile sì/no).

## Procedura consigliata

1. Creare il progetto con **cliente e periodo**.
2. Impostare **responsabilità e stato**.
3. Pianificare **attività o ricorrenze**.
4. Registrare le prestazioni e seguire l'avanzamento nella vista di
   dettaglio.
5. Prima della chiusura controllare attività aperte, tempi, fogli ore
   e posizioni fatturabili — solo dopo chiudere.

![Elenco progetti con cliente, stato e durata](media/kunden/projektliste.png)
*L’elenco progetti: ogni progetto con cliente, stato e durata.*

## Esempio pratico

Per una migrazione server nasce il progetto «Migrazione CED» con
durata, tariffa oraria e due responsabili. I tecnici registrano i
tempi direttamente sul progetto; a fine mese la vista di dettaglio
mostra a colpo d'occhio cosa resta fatturabile.

## Errori tipici

- **Chiudere troppo presto:** un progetto chiuso non accetta più
  registrazioni — prima controllare tempi e posizioni aperte.
- **Cambiare le regole di fatturazione a posteriori** aspettandosi che
  le vecchie registrazioni seguano: le regole valgono per il futuro.
- **Registrare tutto senza progetto:** senza collegamento mancano poi
  analisi e consegna pulita alla fatturazione.

## Effetti e prossimi passi

Regole di fatturazione e stato del progetto determinano quali tempi e
materiali vanno in consegna. Poi: impostare la registrazione dei tempi
sul progetto e controllare la consegna alla fatturazione a fine
periodo.
