---
title: "Servizi IA"
topic: ai.services
version: 5
keywords:
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
