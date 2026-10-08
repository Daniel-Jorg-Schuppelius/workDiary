---
title: "Presenza, piano/effettivo e copertura"
topic: reports.attendance
version: 1
keywords:
    - report presenze
    - analizzare le timbrature
    - confronto piano effettivo
    - organico previsto ed effettivo
    - carenza di personale
    - copertura turni
    - organico minimo
    - ritardo
    - orario fisso
    - report mensile del team
    - ore per dipendente
    - giorni-persona
audience:
    - admin
    - geschaeftsfuehrung
    - personalverwaltung
    - teamleitung
related:
    - reports.overview
    - reports.my-reports
    - attendance.manage
    - planning.shifts
    - reports.utilization
    - reports.presence-emergency
---

Questi report confrontano chi era presente e quando con ciò che era previsto:
le timbrature con il modello di orario di lavoro, i turni con l'organico
previsto e il tempo pianificato degli incarichi con il tempo registrato. Ne
fanno parte **Presenza** e **Piano/effettivo** nell'area di menu **Report** →
**Personale**, nonché **Copertura** e **Mese per dipendente** in **Report** →
**Team**. Tutte le pagine mostrano solo i dati dell'organizzazione attiva. Le
correzioni si effettuano sulla timbratura, sulla registrazione di tempo, sul
modello di orario di lavoro o nel piano turni; il report non è una fonte di
dati autonoma.

## Presenza

**Report** → **Personale** → **Presenza** apre il **Report presenze** per il
periodo selezionato nell'intestazione (icona del calendario **Seleziona
periodo**).

La tabella ha una riga per persona e le colonne:

- **Giorni lavorativi** e **Previsto**: giorni e tempo previsto secondo il
  modello di orario di lavoro per giorno della settimana. Qui i giorni festivi
  e le ferie non riducono il tempo previsto – diversamente da **Bilancio
  lavoro**. Senza modello di orario di lavoro entrambi i valori sono 0.
- **Presente**: timbrature concluse al netto delle pause; le timbrature in corso
  e annullate non contano.
- **Registrato**: tutte le registrazioni di tempo del periodo, di qualsiasi
  tipo.
- **Saldo**: presente meno previsto, in rosso se negativo, in verde se positivo.

La riga **Totale** e i riquadri **Previsto**, **Presente**, **Registrato** e
**Saldo** riassumono tutte le persone visualizzate. La heatmap **Presenza per
collaboratore e giorno della settimana** mostra in quali giorni della settimana
una persona era presente e per quanto tempo; **Presenza nel tempo** mostra il
totale per giorno, o per settimana di calendario nei periodi oltre 62 giorni.
Un clic sull'intestazione di una colonna ordina la tabella.

I filtri sono disponibili solo per gli amministratori: **Area** con **Solo i
propri** o **Tutto il team** (tutte le persone dell'organizzazione) nonché
**Dipendente** e **Team**. Tutti gli altri vedono solo la propria riga.

Esportazione: **PDF** con tabella e heatmap; nel menu **Esportazione** **CSV** ed
**Excel** con giorni lavorativi, previsto, presente, registrato e saldo in
minuti per persona e una riga di totale.

## Piano/effettivo

**Report** → **Personale** → **Piano/effettivo** confronta previsto ed effettivo
in più viste, che si cambiano con le schede in alto nella pagina: **Presenza**,
**Team**, **Organizzazione**, **Turni**, **Progetti** e **Siti**. Vede solo le
schede per cui dispone dei permessi.

Qui il periodo non viene preso dall'intestazione: lo imposti nella barra dei
filtri con **Da** e **Fino a**. Senza indicazione vale il mese corrente; il
periodo resta invariato quando cambia scheda. Queste pagine non prevedono
esportazioni.

### Scheda Presenza

La pagina **Presenza piano/effettivo** mostra i Suoi giorni con i riquadri
**Piano**, **Effettivo**, **Δ** e **Avvisi** e, per ogni giorno, le colonne:

- **Piano**: tempo previsto secondo il modello di orario di lavoro per il giorno
  della settimana; «—» nei giorni senza modello di orario o senza giornata
  lavorativa.
- **Effettivo**: la presenza timbrata del giorno.
- **Δ**: effettivo meno previsto, valori negativi in rosso.
- **Inizio P/E**: inizio dell'orario fisso secondo il modello di orario di
  lavoro e prima timbratura del giorno, seguiti dallo scostamento in minuti.
- **Avvisi**: inizio in ritardo, quando la prima timbratura è più di 15 minuti
  dopo l'inizio dell'orario fisso, e scostamento delle ore, quando l'effettivo
  si discosta dal previsto di oltre il 10 %. I giorni senza previsto non
  ricevono avvisi.

Se apre la pagina per un'altra persona dalla scheda **Team** o
**Organizzazione**, in alto compare l'indicazione **Vista per** con il suo nome.

### Schede Team e Organizzazione

**Team** mostra i membri di uno dei Suoi team; se ne ha più di uno, lo scelga
nel campo **Team**. Gli amministratori e le persone con il permesso
organizzazione possono scegliere qualsiasi team non archiviato.
**Organizzazione** mostra tutte le persone dell'organizzazione in **Tutti i
dipendenti**.

Entrambe le viste sommano per persona **Pianificato (h)**, **Effettivo (h)**,
**Differenza (h)** e **Avvisi** con la stessa logica giornaliera della scheda
**Presenza**. La lente **Dettagli** apre la vista giornaliera della persona per
lo stesso periodo. Con il permesso team ciò è possibile solo per i membri dei
Suoi team.

### Schede Turni, Progetti e Siti

- **Turni**: il previsto sono i turni pubblicati e confermati del piano turni
  con la durata della loro finestra oraria (inclusi i turni notturni oltre la
  mezzanotte); l'effettivo è la sovrapposizione delle timbrature della persona
  assegnata con tale finestra. Riquadri **Piano**, **Effettivo**, **Differenza**
  e **Copertura** (effettivo rispetto al previsto; evidenziata sotto il 100 %).
  Con **Raggruppamento** sceglie **Giornaliero** o **Settimanale** per il
  grafico **Pianificato vs effettivo per giorno** o **Pianificato vs effettivo
  per settimana**. La tabella **Per tipo di turno** indica **Turni**,
  **Pianificato (h)**, **Effettivo (h)**, **Differenza (h)** e **Copertura**. I
  turni senza finestra oraria sono contrassegnati con **senza finestra
  oraria**: non hanno previsto e come effettivo conta la presenza giornaliera
  della persona.
- **Progetti**: il previsto è la somma dei minuti pianificati degli incarichi il
  cui periodo tocca il periodo scelto; l'effettivo è il tempo registrato per
  progetto. Gli incarichi ricevono minuti pianificati ad esempio con la
  conferma di appuntamenti Calendly. Riquadri **Piano**, **Effettivo**,
  **Differenza** e **Fatturabile (effettivo)**, il grafico **Progetti
  principali: pianificato vs effettivo** (i dodici progetti con più ore
  effettive) e la tabella **Per progetto** con **Progetto**, **Cliente**,
  **Incarichi (pianificati)**, **Pianificato (h)**, **Effettivo (h)**,
  **Fatturabile (h)** e **Differenza (h)**. I progetti senza incarichi
  pianificati riportano l'indicazione **senza dati previsti** – non è un
  allarme. Il tempo senza progetto compare nella riga **Senza progetto**. I
  confronti con budget di tempo e di denaro li fornisce **Redditività**.
- **Siti**: per i siti non esistono dati previsti; la vista mostra solo la
  distribuzione effettiva del tempo rilevato con la registrazione basata sulla
  posizione. Riquadri **Effettivo**, **Visite in loco** e **Persone**, grafico
  **Tempi effettivi per sede** e tabella **Per sede** con **Ubicazione**,
  **Cliente**, **Visite in loco**, **Persone**, **Effettivo (h)** e **Quota**.
  Le visite a geofence senza sede assegnata compaiono in **Senza assegnazione a
  una sede** con l'indicazione **Geofence senza sede**.

Le tabelle lunghe delle schede **Progetti** e **Siti** sono suddivise in pagine
da 50 righe ciascuna.

## Copertura

**Report** → **Team** → **Copertura** confronta l'organico previsto con quello
effettivo: i turni pianificati soddisfano l'organico previsto?

- Il previsto è il valore minimo (**Min**) dell'**Organico previsto** che Lei
  definisce nel piano di servizio per tipo di turno. Una voce per una data
  precisa prevale su una voce per il giorno della settimana, che a sua volta
  prevale su una voce generale. I tipi di turno senza organico previsto non
  compaiono.
- L'effettivo è il numero di turni pianificati per tipo di turno e giorno;
  contano tutti i turni non annullati, comprese le bozze.
- Il conteggio avviene in giorni-persona: un turno di una persona in un giorno
  corrisponde a un giorno-persona.

Riquadri: **Tipi di turno** (con il numero di giorni valutati), **Previsto
(giorni-persona)**, **Effettivo (giorni-persona)** con la differenza,
**Completamento** (effettivo rispetto al previsto) e **Giorni con copertura
insufficiente**. La heatmap **Grado di copertura per tipo di turno e giorno
della settimana** mostra in ogni cella effettivo/previsto e la percentuale;
**Giorni-persona mancanti per settimana** mostra le lacune per settimana di
calendario. La tabella **Per tipo di turno** indica **Previsto**,
**Effettivo**, **Differenza**, **Completamento** e **Giorni sotto**; sotto,
**Giorni con copertura insufficiente** elenca ogni giorno interessato con
**Data**, **Tipo di turno**, **Previsto**, **Effettivo** e **Lacuna**.

Il periodo è quello dell'intestazione, al massimo 400 giorni – un periodo più
lungo viene troncato dopo 400 giorni. Filtro **Team**: conta solo i turni dei
membri del team; il previsto resta invariato. Esportazione: **PDF** con heatmap
e giorni di copertura insufficiente, **CSV** ed **Excel** con i valori per tipo
di turno.

## Mese per dipendente

**Report** → **Team** → **Mese per dipendente** (titolo della pagina **Report
mensile del team**) mostra le ore registrate di tutte le persone nell'arco di un
anno di calendario – l'anno in cui inizia il periodo dell'intestazione.

- La tabella ha una riga per persona con registrazioni di tempo nell'anno, una
  colonna per mese, il totale annuo delle ore e il totale dei ricavi in euro;
  l'ultima riga somma ogni mese.
- Sopra la tabella si trovano i totali annui di ore e ricavi.
- Il grafico **Ore per collaboratore** mostra il totale annuo di ogni persona
  con una linea della mediana, la heatmap **Ore per collaboratore e mese** la
  distribuzione nell'anno.
- Contano tutte le registrazioni di tempo, di qualsiasi tipo.

Filtri: **Dipendente** e **Team**. Esportazione: **PDF** in orizzontale con
heatmap, **CSV** ed **Excel**.

## Chi vede che cosa

- **Presenza**: ogni persona vede la propria riga, gli amministratori l'intera
  organizzazione.
- **Piano/effettivo**: la scheda **Presenza** con i propri giorni è visibile a
  tutti. La scheda **Team** richiede il permesso **Consultare il rapporto
  presenze (team)**, le schede **Organizzazione**, **Turni**, **Progetti** e
  **Siti** il permesso **Consultare il rapporto presenze (organizzazione)**. Gli
  amministratori vedono tutte le schede. Nell'assegnazione standard il ruolo
  Capo team ha il permesso team, la Direzione il permesso organizzazione e la
  Gestione del personale entrambi.
- **Copertura** e **Mese per dipendente** sono riservati agli amministratori. Le
  altre persone vedono le voci di menu, ma all'apertura ricevono un messaggio
  di permesso mancante.
- L'area di menu **Team** esiste solo se è attivo il modulo aggiuntivo dei
  report di team.
