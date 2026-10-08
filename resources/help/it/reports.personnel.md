---
title: "Personale: ferie, malattia, qualifiche, sicurezza"
topic: reports.personnel
version: 1
keywords:
    - tasso di assenza
    - ferie residue
    - report ferie
    - giorni di malattia
    - continuazione della retribuzione
    - certificato di malattia
    - matrice qualifiche
    - certificati in scadenza
    - analizzare gli infortuni
    - quasi infortunio
    - fabbisogno formativo
    - allarmi precoci
audience:
    - admin
    - geschaeftsfuehrung
    - personalverwaltung
    - teamleitung
related:
    - reports.overview
    - absences.manage
    - reports.absence-calendar
    - time-accounts.flex
    - catalog.qualifications
    - safety.overview
    - learning.overview
---

Questi report riassumono dati personali: ferie e orario flessibile, malattia e
continuazione della retribuzione, qualifiche con date di scadenza, eventi di
sicurezza nonché problemi ricorrenti e fabbisogno formativo. Li trova in
**Report** → **Team** (**Ferie e flex**, **Malattie**, **Qualifiche**,
**Sicurezza sul lavoro**) e in **Report** → **Progetti e clienti** (**Problemi e
formazione**). Si tratta di dati particolarmente sensibili: trasmetta i valori
solo alle persone che ne hanno bisogno per il proprio compito. Le correzioni si
effettuano sulla richiesta di ferie, sul certificato di malattia, sulla
qualifica della persona o sull'evento di sicurezza.

## Ferie e flex

**Report** → **Team** → **Ferie e flex** mostra assenze e orario flessibile per
persona nel periodo selezionato nell'intestazione. Si contano giorni
lavorativi: da lunedì a venerdì esclusi i festivi, limitati al periodo.

Colonne per persona:

- **Ferie**, **Speciale** e **Non pagato**: giorni lavorativi da richieste
  approvate del rispettivo tipo.
- **Malattia**: giorni lavorativi dai congedi per malattia; quelli annullati non
  contano.
- **In sospeso**: giorni lavorativi da richieste non ancora decise, evidenziati
  a colori.
- **Diritto** e **Residuo** con l'anno: il conto ferie dell'anno in cui termina
  il periodo. Il diritto comprende il diritto base, le ferie aggiuntive e il
  riporto utilizzabile; il residuo detrae solo i giorni approvati e diventa
  rosso se negativo. Senza diritto registrato compare «–».
- **Flex Δ**: variazione del saldo dell'orario flessibile nei mesi del periodo
  (effettivo meno previsto secondo i valori mensili del conto ore).
- **Saldo flessibile**: l'ultimo valore mensile fino alla fine del periodo.

Riquadri: **Dipendente**, **Ferie (giorni lavorativi)** con i giorni in
sospeso, **Malattia**, **Speciale / non retribuito** e **Variazione flessibile
Σ**. Il grafico **Giorni di assenza al mese per tipo** impila ferie, malattia,
speciale e non pagato; a seconda della durata del periodo è calcolato al
giorno, a settimana o per trimestre. **Ferie residue per collaboratore (top
15)** mostra i residui più alti.

I filtri sono disponibili solo per gli amministratori: **Area** (**Solo i
propri** o **Tutto il team** per tutte le persone dell'organizzazione),
**Dipendente**, **Team** e **Stato**. Con **Stato** contano solo le richieste
**In sospeso** o **Approvato**. Esportazione: **PDF** con grafico, **CSV** ed
**Excel**.

## Malattie

**Report** → **Team** → **Malattie** apre il **Report malattia** per il periodo
selezionato nell'intestazione. I congedi per malattia annullati non contano.

Colonne per persona con congedi per malattia nel periodo:

- **Giorni lavorativi** e **Giorni cal.**: giorni di malattia nel periodo, una
  volta senza fine settimana e festivi, una volta come giorni di calendario.
- **Casi**: numero di congedi per malattia; **Seguito**: di cui certificati di
  prosecuzione.
- **Con certificato**: congedi per malattia con un certificato caricato,
  rispetto a tutti i casi.
- **Continuazione della retribuzione**: giorni utilizzati rispetto al diritto
  come barra – verde, arancione dal 75 %, rossa quando esaurito.
- **Stato**: **Esaurito** con la data in cui termina il diritto, altrimenti i
  giorni liberi residui oppure **OK**. Sotto il nome, **Catena da** indica
  l'inizio della catena di malattia in corso.

Come si calcola la continuazione della retribuzione: nell'impostazione standard
il diritto è di sei settimane, cioè 42 giorni di calendario per catena di
malattia. Un nuovo congedo prosegue la catena esistente se è collegato come
certificato di prosecuzione o se dalla fine della catena sono trascorsi meno di
sei mesi; solo dopo almeno sei mesi il diritto ricomincia. Le diagnosi non
vengono confrontate. Si considerano utilizzati i giorni di calendario
dall'inizio della catena alla sua fine, per una malattia in corso fino a oggi.
Le colonne **Continuazione della retribuzione** e **Stato** mostrano la
situazione di oggi, indipendentemente dal periodo scelto. I valori sono un
orientamento, non una verifica giuridica.

Riquadri: **Dipendente**, **Giorni lavorativi di malattia** con i giorni di
calendario, **Casi di malattia** con i certificati di prosecuzione, **Con
certificato** e **Diritto esaurito**. Grafici: **Giorni di malattia al mese**
con linea della mediana (al giorno, a settimana o per trimestre a seconda del
periodo) e la heatmap **Giorni di malattia per collaboratore e mese**.

Filtri come in **Ferie e flex**: **Area**, **Dipendente** e **Team**, solo per
gli amministratori. Questa pagina non prevede esportazioni.

## Qualifiche

**Report** → **Team** → **Qualifiche** mostra la **Matrice qualifiche**: una
riga per persona con almeno una qualifica, una colonna per ogni qualifica del
catalogo (sigla, nome completo al passaggio del mouse).

- Ogni cella mostra la data di scadenza, oppure ✓ se la qualifica vale senza
  data di scadenza.
- Colori: verde **valido**, arancione **scade tra 30 giorni**, rosso
  **scaduto**, grigio **nessuna assegnazione**. La legenda si trova sotto la
  matrice.
- Riquadri: **Dipendente**, **Qualifiche**, **Assegnazioni**, **In scadenza
  (≤30 g)** e **Scaduto**.
- Grafici: **Titolari per qualifica (top 15)** e **Assegnazioni per qualifica
  secondo lo stato** per le dodici qualifiche più frequenti.

La data di riferimento è sempre oggi; il periodo dell'intestazione non modifica
la matrice. Filtri: **Dipendente** e **Team**. Esportazione: **PDF** in
orizzontale, **CSV** ed **Excel** con una riga per persona e, per ogni
qualifica, la data di scadenza o un'indicazione di validità. Le qualifiche si
gestiscono nel catalogo e sulla persona, non nel report.

## Sicurezza sul lavoro

**Report** → **Team** → **Sicurezza sul lavoro** analizza tutti gli eventi di
sicurezza avvenuti nel periodo dell'intestazione.

- Riquadri: **Eventi totali**, **Aperti** (tutti quelli non chiusi), **Chiusi**
  e **Critici** (gravità critica).
- Grafici: **Eventi al mese** con la seconda serie **di cui chiusi** ed **Eventi
  al mese per stato**, impilati secondo **Segnalato**, **In indagine**,
  **Misure definite** e **Chiuso**; al giorno, a settimana o per trimestre a
  seconda del periodo.
- **Per tipo** conta **Infortunio**, **Quasi infortunio**, **Pericolo** e
  **Difetto**, **Per gravità** conta **Bassa**, **Media**, **Alta** e
  **Critica**.

Filtri: **Dipendente** e **Team** – si riferiscono alla persona che ha
segnalato l'evento. Non è prevista un'esportazione; i singoli eventi si
gestiscono nel registro degli eventi di sicurezza.

## Problemi e formazione

**Report** → **Progetti e clienti** → **Problemi e formazione** apre l'**Analisi
direzionale**. Mostra la situazione attuale, senza periodo, filtri o
esportazione.

La scheda **Problemi ricorrenti** raccoglie gli allarmi precoci dei moduli
utilizzati dalla Sua organizzazione, raggruppati per tipo:

- **Rilavorazioni per cliente**: clienti la cui quota di rilavorazioni negli
  ultimi 90 giorni non rispetta il valore obiettivo. Senza valore obiettivo
  registrato (veda «Valori obiettivo (report)») non viene generato alcun
  allarme.
- **Difetti ricorrenti**: oggetti con difetti ripetuti negli ultimi dodici mesi.
- **Schemi di reclamo**: reclami insolitamente frequenti.
- **Ticket ricorrenti**: clienti o oggetti con molti ticket nella finestra
  temporale; soglia e finestra sono impostazioni dell'organizzazione, di norma
  tre ticket in 90 giorni.
- **Carenze di personale**: team il cui fabbisogno pianificato supera la
  capacità nelle prossime quattro settimane.

Ogni voce indica il riscontro, un dettaglio e una raccomandazione; dove
possibile, il titolo porta al cliente, all'oggetto o al report interessato.
Senza riscontri compare **Nessuna anomalia.**

La scheda **Fabbisogno formativo** elenca per **Competenza** le **Persone con
lacuna**, la **Lacuna media (livelli)** e i **Corsi adatti** (corsi pubblicati
che trasmettono la competenza). Si basa sui requisiti di competenza per ruolo
della piattaforma di apprendimento; le attestazioni scadute non contano. Senza
piattaforma di apprendimento o senza requisiti di competenza la tabella resta
vuota.

## Chi vede che cosa

- **Ferie e flex**, **Malattie** e **Qualifiche**: ogni persona vede solo i
  propri dati. La vista su tutte le persone dell'organizzazione è legata al
  ruolo di amministratore – il solo permesso **Visualizza i congedi per
  malattia** non la sblocca qui.
- **Sicurezza sul lavoro**: voce di menu e pagina solo con il permesso
  **Visualizzare il registro degli eventi di sicurezza** o **Modificare /
  chiudere gli eventi di sicurezza**; gli amministratori le vedono sempre.
  Nell'assegnazione standard Capo team e Direzione hanno il permesso di lettura.
- **Problemi e formazione**: solo con il permesso **Visualizza i report** o come
  amministratore. Nell'assegnazione standard lo hanno, tra gli altri, Direzione,
  Capo team e Gestione del personale.
- Le aree di menu **Team** e **Progetti e clienti** esistono solo se è attivo il
  modulo aggiuntivo dei report di team.
