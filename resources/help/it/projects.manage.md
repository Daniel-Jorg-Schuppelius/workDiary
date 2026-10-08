---
title: "Gestire i progetti"
topic: projects.manage
version: 3
keywords:
    - creare progetto
    - gestione progetti
    - elenco progetti
    - milestone
    - ore di progetto
    - fatturazione progetto
    - chiudere progetto
    - riassegnare ore
    - timesheet
    - attività di progetto
    - tariffa oraria
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

- Un cliente esistente (si veda clienti & fornitori).
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

La scheda **Tempi** sopra l’elenco progetti mostra le registrazioni ore di
tutti i progetti nel periodo selezionato in alto, senza aprire ogni
progetto. Le registrazioni sono raggruppate per progetto; con «Raggruppa
per» passa a data o persona. Ogni gruppo indica il numero di registrazioni
e il totale dell’intero periodo e può essere compresso. Può filtrare per
termine di ricerca (progetto, attività, descrizione), cliente, progetto,
collaboratore, etichetta e fatturabilità. Solo amministrazione, contabilità
e chi può consultare tutte le ore vedono le registrazioni altrui; tutti gli
altri vedono le proprie. Le ore senza progetto non compaiono qui.

La stessa regola di visibilità vale nel singolo progetto (rilevazione ore,
fogli ore, ore totali), nel fascicolo di un ordine e per i valori di tempo
nella scheda cliente: senza accesso a tutte le ore, elenchi e totali
contano solo le proprie registrazioni e riportano la nota «solo le proprie
ore».

Per correggere ore assegnate in modo errato, ad esempio dopo un
import con l’utente sbagliato, usi la scheda **Tempi**: selezioni
registrazioni o interi gruppi e li assegni a un’altra persona con
«Assegna utente». Servono i diritti di amministrazione o il diritto
«Riassegna le registrazioni di tempo ad altri utenti» e vale solo per le
ore che può vedere. Le ore fatturate e firmate restano bloccate; una
selezione non viene mai salvata parzialmente.

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
