---
title: "Importare l'elenco chiamate FRITZ!Box"
topic: admin.fritzbox
version: 2
keywords:
    - FRITZ!Box
    - elenco chiamate
    - registrare telefonate
    - chiamate come tempo
    - report telefonico
    - timbratura telefonica
    - timbrare con una chiamata
    - importazione CSV chiamate
    - AVM
    - assegnare un numero
    - fatturare il tempo al telefono
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - time-entries.edit
    - attendance.manage
    - contacts.manage
    - foreign-customers
---

La pagina **Importazione FRITZ!Box** acquisisce le telefonate dall'elenco
chiamate di una FRITZ!Box come voci di tempo. WorkDiary registra da sé le
chiamate di clienti e clienti finali noti; se una chiamata si sovrappone a un
tempo già registrato per lo stesso cliente, ad esempio una teleassistenza,
viene fusa con quel tempo invece di essere fatturata due volte. I numeri
sconosciuti vengono raccolti nell'**Inbox di riconciliazione**. Inoltre i
dipendenti possono timbrare entrata e uscita chiamando uno dei Suoi numeri.

Per farlo WorkDiary non si collega alla FRITZ!Box. Legge l'elenco chiamate
esportato, come file caricato oppure come report telefonico via e-mail. Non
servono credenziali di accesso alla box.

## Prerequisiti

- Il plugin **Elenco chiamate FRITZ!Box** è attivato in **Plugin**. Successivamente
  compare la voce **Importazione FRITZ!Box** nel menu di sistema (icona a
  ingranaggio **Sistema**), nel gruppo **Plugin**.
- I numeri di telefono dei Suoi clienti e clienti finali sono registrati nei
  loro dati anagrafici (telefono o cellulare). È così che WorkDiary riconosce
  chi chiama.
- La pagina è riservata agli amministratori della Sua organizzazione;
  l'**Inbox di riconciliazione** alle persone autorizzate a gestire la
  fatturazione.

## Impostazioni del plugin

In **Plugin** apra la finestra **Configura** di **Elenco chiamate FRITZ!Box**:

- **Registrare le telefonate come fatturabili** (predefinito: attivo): se
  disattivato, le telefonate importate non vengono mai contrassegnate come
  fatturabili.
- **Registra i tempi per l’utente**: l'utente per cui vengono registrate le
  telefonate; lo sceglie dall'elenco. Senza selezione, WorkDiary registra sul
  titolare dell'organizzazione o sul primo utente.
- **Durata minima (minuti)** (predefinito: 2): le chiamate più brevi vengono
  saltate.
- **Finestra di anticipo (minuti)** (predefinito: 15): se una chiamata termina
  al massimo questo numero di minuti prima di un tempo registrato dello stesso
  cliente, viene fusa con esso.
- **Solo numeri propri**: elenco separato da virgole dei Suoi numeri le cui
  chiamate devono essere importate, ad esempio solo la linea principale
  dell'azienda. Se vuoto, si importa tutto. Inserisca i numeri esattamente come
  compaiono nella colonna «Eigene Rufnummer» (numero proprio) dell'elenco
  chiamate.
- **Considerare il tipo 3 come in uscita**: solo per elenchi di versioni più
  vecchie di FRITZ!OS, che esportano le chiamate in uscita come tipo 3.
- **Confronta contatti esterni** (predefinito: attivo): i numeri sconosciuti
  vengono confrontati anche con le rubriche collegate, come Lexoffice e
  Microsoft 365.
- **Numero timbratura: entrata**, **Numero timbratura: uscita** e **Numero
  timbratura: entrata/uscita**: i Suoi numeri per la timbratura telefonica
  (vedi sotto).

## Caricare l'elenco chiamate

1. Esporti l'elenco chiamate nella FRITZ!Box: FRITZ!Box → Telefonia →
   Chiamate → Salva (CSV).
2. Nella pagina, scelga il file nella sezione **Caricare l'elenco chiamate**
   (estensione .csv o .txt, al massimo 20 MB) e clicchi su **Importa**.
3. Un messaggio riassume il risultato: registrate, fuse, timbrate, aperte
   (inbox), saltate, filtrate e bloccate.

Può caricare di nuovo lo stesso elenco senza rischi: WorkDiary salta le
chiamate già importate.

Il riquadro **Confronto contatti** mostra quali fonti di contatti esterne sono
collegate al momento. Senza una fonte esterna, WorkDiary confronta comunque con
i Suoi clienti e clienti finali.

## Report telefonico via e-mail

Invece di caricare il file, la FRITZ!Box può inviare il proprio elenco chiamate
come report telefonico via e-mail. A tal fine configuri in **Ricezione e-mail**
una casella che riceve queste e-mail e vi attivi **Casella dei report
telefonici: trasferire gli elenchi chiamate FRITZ!Box (CSV) all'importazione
dell'elenco chiamate**. La ricezione e-mail controlla le caselle ogni cinque
minuti per impostazione predefinita; gli elenchi chiamate riconosciuti
confluiscono nella stessa importazione di un caricamento. I report recapitati
due volte non generano registrazioni doppie. Quando una tale casella è
collegata, il controllo di stato del plugin segnala «Pronto — ricezione del
report telefonico via e-mail collegata.»

## Cosa succede a ogni chiamata

- **Filtrate:** chiamate con numero nascosto, chiamate perse e rifiutate,
  chiamate tramite numeri propri non presenti in **Solo numeri propri**, nonché
  i numeri ignorati nell'inbox.
- **Saltate:** chiamate già importate e chiamate sotto la durata minima. Se
  abbassa la durata minima, una nuova importazione recupera tali chiamate.
- **Fuse:** per un numero noto, WorkDiary cerca un tempo registrato dello
  stesso utente per lo stesso cliente che la chiamata sovrappone o che inizia
  al più tardi entro la finestra di anticipo dopo la chiamata. La chiamata viene
  allegata a quel tempo come prova, e il suo inizio viene anticipato all'inizio
  della chiamata.
- **Registrate:** se non esiste un tale tempo, viene creata una voce di tempo
  propria sul progetto predefinito del cliente o del cliente finale (creato se
  necessario). La descrizione riporta direzione, nome e numero; la
  fatturabilità segue l'impostazione.
- **Bloccate:** se la chiamata cade in un mese chiuso, WorkDiary non crea alcuna
  voce. I tempi già esportati non vengono mai modificati.
- **Aperte (inbox):** i numeri sconosciuti e quelli contrassegnati come
  condivisi finiscono nell'**Inbox di riconciliazione**.

Nel riconoscimento hanno la precedenza i numeri memorizzati, seguiti dai dati
anagrafici; se un numero corrisponde a un cliente finale, prevale il cliente
finale come destinazione più precisa. Se in una rubrica collegata un numero è
già assegnato a un cliente, WorkDiary registra direttamente.

## Assegnare i numeri sconosciuti

La sezione **Inbox di riconciliazione** indica il numero di gruppi di
importazione aperti; **Alla casella** porta lì. Le chiamate di uno stesso numero
vi compaiono come gruppo, spesso già con un cliente proposto:

- Scelga un cliente o un cliente finale e clicchi su **Assegna e registra**.
  Tutte le chiamate del gruppo vengono registrate con le stesse regole
  dell'importazione. Con **Memorizzare il numero in modo permanente**
  (preselezionato) le chiamate future da questo numero passano senza domande.
- **Numero condiviso** è pensato per i numeri tramite cui chiamano più clienti,
  ad esempio la hotline di un fornitore di servizi. Le chiamate future da questo
  numero arrivano una per una nell'inbox per l'assegnazione e non vengono mai
  registrate automaticamente.
- **Ignora numero** esclude il numero in modo permanente, ad esempio per
  chiamate private; le chiamate future non vengono più importate.
- **Scarta gruppo** scarta solo le chiamate mostrate. Non ritornano nemmeno con
  una nuova importazione; le nuove chiamate del numero ricompaiono.

## Timbratura telefonica

Ecco come i dipendenti timbrano per telefono:

1. Nelle impostazioni del plugin inserisca uno o più dei Suoi numeri come
   **Numero timbratura: entrata**, **Numero timbratura: uscita** o **Numero
   timbratura: entrata/uscita**, esattamente come compaiono nell'elenco
   chiamate. La sezione **Timbratura telefonica** mostra poi i numeri di
   timbratura attivi.
2. Nella sezione **Timbratura telefonica** assegni a ogni dipendente il suo
   numero: scelga il **Dipendente**, inserisca il **Numero di telefono** (ad
   esempio +49 151 2345678) e clicchi su **Assegna**. Senza prefisso
   internazionale vale la Germania. La tabella mostra tutte le assegnazioni;
   **Rimuovi** ne elimina una.
3. Il dipendente chiama il numero di timbratura. Non è necessario rispondere:
   il numero di chi chiama funge da badge.
4. Alla successiva importazione dell'elenco chiamate, la chiamata diventa una
   timbratura di entrata o di uscita all'ora della chiamata. Per
   **entrata/uscita** vale: se una timbratura è aperta si esce, altrimenti si
   entra.

Limiti: la timbratura avviene solo durante l'importazione, non nel momento
della chiamata. Le chiamate in uscita, i numeri nascosti e quelli non assegnati
vengono ignorati, così come un'uscita senza entrata aperta. La durata minima
qui non si applica. Le chiamate verso un numero di timbratura non vengono mai
registrate come telefonata.

## Problemi tipici

- **File rifiutato:** se l'importazione segnala che non è stato riconosciuto un
  elenco chiamate FRITZ!Box (file vuoto o riga di intestazione mancante), usi
  l'esportazione CSV dell'elenco chiamate senza modificarla.
- **«Nessun utente prenotabile nell'organizzazione.»** oppure un controllo di
  stato che segnala che l'utente predefinito configurato non esiste più:
  verifichi **Registra i tempi per l’utente** oppure annulli la selezione.
- **Mancano le chiamate in uscita:** se l'elenco proviene da un firmware più
  vecchio, attivi **Considerare il tipo 3 come in uscita**.
- **Quasi tutto filtrato:** verifichi **Solo numeri propri**: la grafia deve
  corrispondere esattamente all'elenco chiamate.
- **Molti risultati bloccati:** il mese è già chiuso; le chiamate di quel
  periodo non vengono più registrate.
- **Timbratura mancante:** il numero del dipendente è assegnato ed è stato
  trasmesso durante la chiamata? Il numero di timbratura nelle impostazioni
  corrisponde all'elenco chiamate?
