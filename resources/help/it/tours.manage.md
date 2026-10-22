---
title: "Pianificare i giri"
topic: tours.manage
version: 3
keywords:
    - pianificazione percorsi
    - proposta di giro
    - ottimizzare il percorso
    - itinerario
    - tappe
    - ordine delle visite
    - dispatching
    - vista mappa
    - giro di consegne
    - pianificazione interventi
    - visite clienti
    - viaggi nel libro di bordo
audience: []
modules:
    - module.planung
related:
    - contacts.manage
    - planning.shifts
    - travel-expenses.manage
---

I giri raggruppano tappe e interventi in una sequenza pianificata; la vista
mappa aiuta a verificare e ottimizzare il percorso.

**Coordinate:** gli interventi hanno bisogno di coordinate per essere
pianificabili. Vengono ricavate dall'indirizzo dell'intervento uscendo dai
campi dell'indirizzo; senza indirizzo proprio vale l'indirizzo del cliente.
Le tappe senza coordinate restano nel giro, in fondo, e sono contrassegnate.

**Proposta invece di automatismo:** «Calcola proposta» determina una
sequenza con orari di arrivo e partenza per ogni tappa. Considera tempo di
guida, finestre orarie degli interventi, finestra di lavoro da turno o
disponibilità, pause obbligatorie e qualifiche. La proposta mostra il
confronto con lo stato attuale, attese, pause e interventi non
pianificabili con il motivo. Solo «Adotta» scrive sequenza e orari nel
giro; «Rifiuta» richiede un motivo. Ogni proposta resta tracciabile nello
storico del giro.

**Ora e punto di partenza:** l'ora di partenza del giro e il punto di
partenza (casa, sede o un indirizzo) determinano gli orari di arrivo.

**Viaggi nel libro di bordo:** da un giro avviato le tratte possono essere
riprese come viaggi. I chilometri stradali derivano dal percorso calcolato;
senza percorso vale la linea d'aria e il viaggio è contrassegnato di
conseguenza. Per un veicolo in modalità libro di bordo viene richiesto il
chilometraggio e i chilometraggi dei viaggi si susseguono senza
interruzioni. La ripresa è possibile una sola volta per giro.

**In viaggio:** la vista conducente del giro mostra la prossima fermata con la
navigazione, «Avvia giro», per ogni fermata «Arrivato», «Completato» o «Non
trovato» e «Termina giro»; gli orari diventano il consuntivo delle fermate.
«Ripianifica il resto» su un giro in corso mantiene le fermate concluse e
ripianifica solo quelle aperte a partire dall'ultima conclusa.

**Piano e consuntivo:** il report «Giri previsto/effettivo» confronta arrivo e
permanenza previsti ed effettivi per fermata, con gli scostamenti per
conducente e tipo di ordine; ne derivano valori di esperienza per la durata
del servizio nelle proposte future.

Concordi con le persone coinvolte le modifiche ai giri in corso.
