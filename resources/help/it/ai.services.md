---
title: "Servizi IA"
topic: ai.services
version: 6
keywords:
    - mascheramento
    - accordo sul trattamento
    - approvazione protezione dati
    - IA
    - AI
    - intelligenza artificiale
    - assistente IA
    - modello linguistico
    - LLM
    - Ollama
    - suggerimenti di testo
    - tradurre posizioni
    - riformulare testi
    - glossario
    - attivare IA
audience: []
modules:
    - module.ai
related:
    - invoices.manage
    - quotes.overview
---

L'assistenza IA è facoltativa e disattivata per impostazione
predefinita. In **Amministrazione → Servizi IA** collega i provider
(cloud o locali, ad es. Ollama), attiva le singole capability e
definisce per capability quali connessioni sono consentite e quale è
quella predefinita.

**Chi può usare l'IA:** Suggerimenti, traduzioni e azioni cumulative sono
disponibili solo per le persone con il permesso «Richiedere suggerimenti IA».
È compreso in tutti i ruoli standard del personale — dalla
direzione, contabilità e personale fino al servizio interno, al servizio
esterno e al call center —, ma non nel portale clienti né nel ruolo di
supporto in sola lettura. Per i ruoli personalizzati lo assegna in
**Ruoli & permessi**. Connessioni, autorizzazioni e budget li gestisce solo
chi ha «Gestire le connessioni ai provider di IA e il routing delle
capability».

**Privacy:** L'anteprima del flusso dati mostra per capability quali
classi di dati vanno a quale provider. Le connessioni cloud sono
bloccate con sensibilità alta e nel profilo di assistenza domiciliare;
i piani che usano gli input per l'addestramento non possono essere
collegati. Le chiavi API sono salvate cifrate e mai visualizzate.

**Sostituire una chiave:** Se una chiave API scade o è compromessa, apra
«Modifica connessione» e la inserisca in «Nuova chiave API».
Lasciato vuoto, il campo mantiene la chiave attuale. La nuova chiave viene
verificata subito; la connessione resta e la sua assegnazione come
predefinita per capability non va persa.

**Memoria IA:** Termini di glossario, regole di stile e coppie di
esempio per organizzazione, cliente o capability migliorano i
suggerimenti — senza addestrare modelli di terzi. Si apprende solo dopo
la sua conferma (dialogo «Ricordare?»).

**Testi di posizione:** Nelle bozze di fatture e preventivi l'IA crea
suggerimenti di testo per posizione (traduzioni incluse). Nulla viene
applicato prima del clic — quantità, prezzi e imposte restano
invariati.

**Azioni cumulative:** In una fattura in bozza, «Traduci tutto» traduce ogni
posizione, e in un verbale «Migliora tutti i punti» riformula ogni punto con
testo. Entrambe vengono eseguite in background. Ogni posizione e ogni punto
riceve una propria proposta, che lei accetta o rifiuta singolarmente; nulla
viene applicato automaticamente.

**Scegliere la connessione:** se l’organizzazione consente «gli utenti
possono scegliere» per un servizio e sono ammesse più connessioni, la proposta
mostra una selezione. «Rigenerare» crea la proposta con la connessione
scelta; la proposta aperta precedente viene sostituita. La scelta è riportata
nel registro di audit.

**Scelta della connessione:** se l’organizzazione consente «Gli utenti possono scegliere» per un servizio, un suggerimento si può rigenerare con un’altra connessione ammessa anche al trasferimento (testi delle righe), nella ricerca delle attività e per «Spiegare previsto/effettivo».

**Budget mensile:** Nella pagina **Consumo IA**, «Budget mensile» fissa per
famiglia un limite per mese solare – in token per i modelli linguistici, in
caratteri per le traduzioni. Una volta raggiunto, workDiary rifiuta con un
messaggio ulteriori chiamate di questa famiglia fino a fine mese; i riquadri
mostrano l'utilizzo. Se vuoto vale il predefinito del gestore; senza
predefinito il consumo è illimitato.

**Mascheramento:** Prima di ogni connessione cloud, workDiary sostituisce IBAN,
partite IVA, numeri fiscali, codici di identificazione fiscale, indirizzi
e-mail e numeri di telefono con segnaposto come `[IBAN-1]` o `[EMAIL-2]`.
All’interno di una richiesta lo stesso valore riceve sempre lo stesso
segnaposto. In bozze, riepiloghi, traduzioni e valori di campo riconosciuti
workDiary reinserisce i valori prima della visualizzazione; il fornitore non li
vede mai. Viene riconosciuto solo ciò che supera il controllo (cifre di
controllo di IBAN, partita IVA e codice fiscale) – importi e date restano
invariati. I nomi dei clienti della propria anagrafica vengono mascherati come
sempre. Le connessioni locali vedono i valori in chiaro finché in **Protezione
dei dati** «Mascherare anche davanti alle connessioni locali» è disattivato. Il
registro di audit conta i valori mascherati per tipo, senza nominarli.

**Approvazione sulla protezione dei dati:** Per ogni connessione,
«Approvazione sulla protezione dei dati» registra che esiste un accordo sul
trattamento dei dati (per i servizi cloud con il documento), che il
trattamento avviene nell’UE e che il fornitore non addestra modelli con i Suoi
dati – con data e persona che ha verificato. Per una connessione locale
l’accordo serve solo se un fornitore gestisce il modello. L’approvazione vale
per i mesi impostati in **Protezione dei dati** (predefinito 12); 30 giorni
prima della scadenza la coda di lavoro lo ricorda. Approvazione e revoca
compaiono nel registro di audit. Le approvazioni le concede chi ha il diritto
«Concedere e revocare l’approvazione sulla protezione dei dati delle
connessioni IA».

**Solo connessioni approvate:** Se questo interruttore in **Protezione dei
dati** è attivo, nessuna funzione IA usa una connessione senza approvazione
valida; il test preliminare e il messaggio di errore indicano il motivo. Le
nuove organizzazioni partono con l’interruttore attivo. Nelle organizzazioni
già esistenti prima di questa versione resta disattivato finché un
amministratore non lo attiva – fino ad allora le approvazioni in sospeso
compaiono come attività nella coda di lavoro.
