---
title: "Prove: attività di audit, conformità e salario minimo"
topic: reports.compliance
version: 3
keywords:
    - analisi di audit
    - chi ha modificato cosa
    - tracciare le esportazioni
    - panoramica conformità
    - violazioni orario di lavoro
    - confermare violazioni
    - accettare una violazione
    - casi da chiarire
    - prova salario minimo
    - ispezione doganale
    - registrazione orario di lavoro
    - obbligo di registrazione
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
    - buchhaltung
related:
    - reports.arbzg-compliance
    - audit.log
    - corrections.requests
    - attendance.manage
    - reports.fleet
    - admin.organization-settings
---

Queste pagine servono come prova nei confronti di revisori e autorità: chi
ha fatto cosa nel sistema, a che punto sono le violazioni della legge tedesca
sull’orario di lavoro (ArbZG) e come sono state trattate, e la registrazione
dell’orario di lavoro secondo la legge sul salario minimo per la dogana.
Quali regole controlla la conformità dell’orario di lavoro e come appare
l’elenco dettagliato è descritto nell’argomento dedicato alla conformità
dell’orario di lavoro.

## Periodo ed esportazione

- Il periodo si sceglie con la selezione del periodo nell’intestazione. La
  **Cronologia violazioni** mostra invece tutte le violazioni salvate.
- Dove esiste un’esportazione, è indicata nella rispettiva sezione. Ogni
  esportazione viene registrata nel registro di audit.

## Attività di audit

**Report** → **Finanze e audit** → **Attività di audit** riassume le voci del
registro di audit nel periodo. La pagina si apre solo per gli
amministratori; a tutti gli altri l’accesso viene negato.

- Riquadri: **Eventi Σ** (tutte le voci del periodo), **Utenti attivi**
  (persone con almeno una voce) e **Tipi entità** (tipi di oggetto distinti).
  Anche questi due contano tutte le voci del periodo, non solo gli elenchi
  top 20.
- Grafici: **Eventi nel tempo**, **Principali attori (top 15)** e gli eventi
  nel tempo per tipo di evento.
- Tabelle: **Per evento**, **Per tipo entità (top 20)**, **Per utente (top
  20)** e **Ultimi 100 eventi** con **Momento**, **Utente**, **Evento**,
  **Tipo**, **ID** e **IP**. Eventi e tipi compaiono con il loro nome
  leggibile, se disponibile.
- Filtro: **Dipendente**.

Anche l’esportazione di report genera una voce con report, formato e
filtri; così si può ricostruire chi ha scaricato quale report. Le singole
voci con tutti i dettagli sono nel **Registro di audit**. Esportazione in
PDF, CSV ed Excel.

## Dashboard di conformità

La **Dashboard di conformità** si apre tramite la scheda **Dashboard** nella
pagina **Conformità orario di lavoro** (**Report** → **Finanze e audit** →
**Conformità orario di lavoro**). Le schede **Dashboard**, **Report
dettagliato** e **Cronologia violazioni** collegano le tre viste. Serve il
diritto **Visualizza la conformità orario di lavoro**.

La dashboard determina i rilievi del periodo dagli orari di lavoro
registrati, come il report dettagliato; se le regole sui tempi di guida sono
attive, si aggiungono i rilievi sui tempi di guida e di riposo.

- Riquadri: **Totale rilievi** (un clic apre il report dettagliato),
  **Dipendenti interessati**, **Aperto (senza correzione)** e **Con
  correzione approvata** (rilievi in giorni con una correzione dell’orario
  approvata).
- Un riquadro per tipo di violazione con il numero; un clic apre il report
  dettagliato filtrato su quel tipo.
- Grafici: rilievi aperti nel tempo e rilievi nel tempo per tipo di
  violazione.
- **Violazioni per regola e mese**: per ogni mese i rilievi di ciascun tipo
  di violazione con **Totale**.
- **Rilievi per team**: volutamente per team e non per persona. Chi
  appartiene a più team conta in ognuno; le persone senza team compaiono in
  **Senza team**.

Filtro: **Team**. La dashboard non offre esportazioni.

## Cronologia violazioni

La scheda **Cronologia violazioni** apre la pagina **Violazioni di
conformità** con le violazioni salvate e il loro stato di elaborazione. Un
controllo periodico, per impostazione predefinita una volta al giorno di
notte, salva i nuovi rilievi. Se un rilievo non viene più rilevato durante il
controllo, passa a **Risolto**; se si ripresenta, torna su **Aperto**.

- Riquadri per stato: **Aperto**, **Confermato**, **Risolto** e
  **Accettato**, contati su tutta l’organizzazione. Un clic filtra l’elenco.
- Grafico **Rilievi nuovi vs. riscontrati al mese** per gli ultimi 24 mesi
  con dati.
- Elenco: **Dipendente**, **Data**, **Tipo**, **Valore**, **Soglia**,
  **Gravità** e **Stato**; per le violazioni elaborate compaiono sotto nome,
  data e motivazione.
- Filtri: **Dipendente**, **Team**, **Stato** e **Categoria** (**ArbZG**,
  **Casi da chiarire**, **Tempi di guida**).

Ecco come elaborare una violazione nello stato **Aperto** o **Confermato**:

1. Se necessario, inserisca una motivazione nel campo **Motivazione
   (obbligatoria per «accettato»)**.
2. Scelga **Conferma** per prendere atto della violazione oppure **Accetta**
   per tollerarla consapevolmente. Per **Accetta** la motivazione è
   obbligatoria.

Ogni cambio di stato viene registrato nel registro di audit. Per i **Casi da
chiarire** un’icona aggiuntiva apre una **Richiesta di correzione** con il
giorno del rilievo. L’elenco non è limitato al periodo e mostra 50 voci per
pagina. La pagina non offre esportazioni.

## Prova MiLoG (dogana)

La **Prova MiLoG (dogana)** si scarica nella pagina **Conformità orario di
lavoro** dal menu **Esportazione**. Serve come registrazione ai sensi del
§ 17, comma 1, della legge tedesca sul salario minimo (MiLoG) e richiede il
diritto **Visualizza la conformità orario di lavoro**.

- Il file CSV contiene, per dipendente e giorno di calendario,
  **Dipendente**, **Numero personale**, **Data**, **Inizio**, **Fine**,
  **Pause (min)** e **Durata**.
- Si basa sulle timbrature concluse; le timbrature annullate e quelle ancora
  aperte non contano. **Inizio** è il primo inizio e **Fine** l’ultima fine
  della giornata, le pause vengono sommate e **Durata** è l’orario di lavoro
  al netto delle pause.
- L’ordinamento è per nome e data. Il download riprende il periodo e i
  filtri dipendente e team della pagina e viene registrato nel registro di
  audit.

La **Prova tempi di guida** dello stesso menu è descritta nell’argomento
dedicato a parco veicoli, registro viaggi e tempi di guida.
