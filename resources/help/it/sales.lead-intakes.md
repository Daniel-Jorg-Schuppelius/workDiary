---
title: "Fascicolo di ammissione"
topic: sales.lead-intakes
version: 1
keywords:
    - Fascicolo di ammissione
    - Ammissione
    - Accettazione del mandato
    - Verifica nuovi clienti
    - Valutazione iniziale
    - Antiriciclaggio
    - GwG
    - Identificazione
    - Titolari effettivi
    - Persona politicamente esposta
    - Livello di rischio
    - Copia del documento d'identità
    - Pacchetto di verifica
    - Secondo controllo
audience: []
modules:
    - module.vertrieb
related:
    - sales.leads
    - customers.deadlines
    - forms.fill
---

Il **fascicolo di ammissione** appartiene al lead e accompagna un nuovo
cliente dalla richiesta all'attivazione: modulo del colloquio, documenti, le
verifiche richieste dal profilo di settore e la decisione. Solo dopo il lead
diventa cliente, con l'ambito dei servizi e le caratteristiche delle scadenze
ripresi dal fascicolo.

## Aprire il fascicolo

Sul lead compare il pannello **Fascicolo di ammissione** con «Apri
ammissione». Il fascicolo riprende i pacchetti di verifica attivi in quel
momento e li congela: se un pacchetto cambia in seguito, il fascicolo resta
com'era.

## Fasi

**Richiesta → Colloquio → Documenti → Decisione → Attivazione.** Le prime fasi
si scelgono liberamente; l'attivazione deriva dall'accettazione e termina con
la conversione del lead («attivato»). Il riquadro **Ammissioni** della
dashboard conta i fascicoli aperti per fase.

## Il modulo

È un modulo lungo con sezioni, avanzamento e indice. Ogni campo viene salvato
quando lo si lascia; la **modalità colloquio** ingrandisce le aree di tocco per
il tablet durante l'incontro. La parte di base chiede richiesta, servizi
desiderati, inizio e note; seguono le **caratteristiche delle scadenze** dei
set di regole attivi e, per pacchetto, i suoi campi e la sua **lista dei
documenti** con spunte e file.

## Pacchetti di verifica

| Pacchetto | Profilo | Contenuto |
| --- | --- | --- |
| Legge antiriciclaggio (GwG) | Consulenza fiscale (attivo) | Identificazione, verifica con documento, titolari effettivi, registro della trasparenza, persona politicamente esposta, scopo, fattori di rischio, livello di rischio |
| Ammissione assistenziale | Assistenza domiciliare (attivo) | Grado di non autosufficienza e provvedimento, tipo di prestazione, procure e amministrazione di sostegno, disposizioni anticipate, contatti di emergenza, medico di base, contratto |
| Nuovo cliente B2B | IT, pulizie, artigianato (attivabile) | Partita IVA (verifica formale), informazioni di solvibilità, termine di pagamento, pagamento anticipato |

I pacchetti si attivano in **Ammissioni → Impostazioni** (permesso «Decidere
le ammissioni»). Lì si impostano anche l'**aggiornamento per livello di
rischio** (predefinito annuale) e il **secondo controllo**.

## Decisione

Accettare o rifiutare, sempre con motivazione e solo con il permesso
«Decidere le ammissioni». L'accettazione verifica tutti i dati obbligatori e
le regole del pacchetto: per una persona politicamente esposta il livello di
rischio deve essere alto, «basso» e «alto» richiedono una motivazione, e chi
ha verificato con documento d'identità o visura archivia la copia. Con il
**secondo controllo** un'altra persona conferma l'accettazione o la
restituisce con motivazione. L'accettazione con il pacchetto GwG crea la nota
«Identificazione e nota di rischio» sul lead.

**Conversione:** se è attivo un pacchetto con obbligo di decisione (GwG,
ammissione assistenziale), il lead diventa cliente solo dopo l'accettazione.
Senza un tale pacchetto la conversione resta come prima. Alla conversione il
cliente riprende i servizi desiderati come ambito dei servizi, le
caratteristiche delle scadenze, il grado di non autosufficienza e, come
proposta, partita IVA e termine di pagamento.

## Visibilità ristretta

Dati di identità, copia del documento e dati sanitari sono visibili e
registrabili solo da chi ha il permesso proprio — anche chi vede il cliente
non li vede altrimenti: né nel modulo, né nei documenti, nel PDF o nel
riscontro all'accesso. Sono salvati cifrati.

## Aggiornamento e conservazione

Dopo l'accettazione decorre il **termine di aggiornamento** (GwG per livello
di rischio). Poco prima lo ricorda una notifica e la coda di lavoro mostra il
fascicolo; «Aggiorna» crea una nuova **versione**, quella precedente resta
come cronologia.

Il fascicolo GwG si conserva **cinque anni dalla fine dell'anno solare** in
cui termina il rapporto d'affari (§ 8 c. 4 GwG). La fine si registra sul
cliente nel pannello del fascicolo. Fino ad allora il fascicolo sopravvive
all'anonimizzazione del lead e all'archiviazione del cliente; poi l'analisi
della conservazione ne propone la cancellazione. Le ammissioni rifiutate
decorrono dal rifiuto. I pacchetti senza regola propria vengono eliminati con
l'anonimizzazione del lead.

## PDF

«PDF» produce il fascicolo in formato cartaceo: tabella d'intestazione,
sezioni, caselle, ogni pacchetto su una pagina propria, piè di pagina con
data, cliente e numero di pagina. «Archivia come documento» salva il PDF sul
cliente o sul lead, sempre senza dati di identità e sanitari.
