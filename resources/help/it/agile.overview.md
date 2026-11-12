---
title: "Gestione agile dei progetti"
topic: agile.overview
version: 4
keywords:
    - Kanban
    - Scrum
    - sprint
    - pianificazione sprint
    - backlog
    - story point
    - burndown
    - velocity
    - bacheca Kanban
    - user story
    - criteri di accettazione
audience: []
modules:
    - module.agile_projects
related:
    - projects.manage
    - work.overview
---

Per ogni progetto è possibile attivare una board agile — a scelta per
il lavoro continuo (Kanban) o per gli sprint (Scrum). Principio di
fondo: l'attività resta l'elemento primario. Ogni scheda sulla board è
una normale attività con responsabili, registrazioni orarie e
collegamenti; il modulo agile aggiunge rango, story point e
assegnazione allo sprint, ma non sostituisce nulla. Gli story point
non vengono mai, per scelta deliberata, convertiti in tempo o denaro —
il tempo registrato e i punti restano affiancati e separati sulla
scheda.

**Product backlog:** per ogni progetto una classifica di tutti gli
elementi di lavoro. I nuovi elementi nascono direttamente nel backlog
o dall'acquisizione di attività esistenti. Ogni elemento porta un tipo
(ad esempio story o difetto), story point, criteri di accettazione e,
se necessario, un contrassegno di blocco. L'ordine si gestisce con lo
spostamento su/giù; i filtri per tipo, blocchi e termine di ricerca
mantengono leggibili anche i backlog di grandi dimensioni.

**Criteri di accettazione:** l'icona elenco nella riga di un elemento apre i
suoi criteri: aggiungere, spuntare uno per uno, rimuovere. Il numero accanto
mostra i criteri soddisfatti ed esistenti; prima di spostare in una colonna
«fatto», la board indica i criteri non soddisfatti.

**Definizione di completato:** le impostazioni del board contengono la
definizione di completato del progetto, una riga per punto. Quando una
scheda viene spostata in una colonna di completamento, il board apre una
finestra di dialogo con questi punti e i criteri di accettazione ancora
aperti. La scheda viene spostata solo quando ogni punto è confermato. Chi
dispone dell'autorizzazione alla forzatura può spostarla nonostante i punti
aperti indicando una motivazione; lo stesso vale per un limite WIP
raggiunto. Ogni forzatura compare nella cronologia e conta nella serie
qualità dei report, che mostra anche il tasso di scostamento DoD per
settimana.

**Board:** le colonne rappresentano il flusso di lavoro; le schede si
spostano da sinistra a destra. Con uno sprint selezionato, la board
mostra esclusivamente gli elementi di quello sprint.

**Sprint:** pianificazione tramite assegnazione di elementi del
backlog, poi avvio, chiusura o interruzione. Alla chiusura, ogni
elemento non completato richiede una decisione esplicita — ritorno nel
backlog o passaggio allo sprint successivo. Nulla prosegue in modo
tacito.

**Protezione dai conflitti:** modifiche di rango, impostazioni della
board e azioni sugli sprint sono protette contro l'elaborazione
parallela: il primo salvataggio vince, la seconda persona riceve un
avviso e riparte dallo stato attuale. La sovrascrittura silenziosa non
esiste.

**Cronologia:** ogni modifica rilevante — rango, colonna, assegnazione
allo sprint, punti — viene protocollata come evento agile ed è
tracciabile come cronologia.

**Report:** il burndown mostra per uno sprint in corso la serie
giornaliera dei punti rimanenti, la velocity i punti completati per
ciascuno sprint concluso di una board. Una panoramica di management
riassume tutte le board: sprint attivo, mediana della velocity, lavoro
non pianificato, blocchi e una previsione empirica — quest'ultima
compare solo quando sono disponibili abbastanza settimane
confrontabili, invece di simulare una precisione fittizia. La
panoramica mostra solo i progetti che la persona che la consulta può
comunque vedere.

**Completata fuori dalla board:** se il compito di una scheda viene completato altrove (elenco attività, importazione), la scheda segue nella colonna completata. I criteri di accettazione aperti e la definition of done non confermata compaiono allora come deviazione nella cronologia della board e contano nel tasso di deviazione DoD.
