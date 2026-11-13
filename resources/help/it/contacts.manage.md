---
title: "Clienti & fornitori"
topic: contacts.manage
version: 18
keywords:
    - Anteprima come cliente
    - Password iniziale
    - Crea accesso
    - anagrafica clienti
    - anagrafica fornitori
    - creare un cliente
    - creare un fornitore
    - debitore
    - creditore
    - numero debitore
    - unire duplicati
    - importare clienti
    - rubrica
    - partner commerciale
    - CRM
    - portale clienti
    - accesso al portale
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - admin.number-formats
    - projects.manage
    - invoices.manage
    - admin.import
    - communication.notes
---

## Scopo e contesto

Clienti e fornitori sono i dati anagrafici centrali di WorkDiary:
progetti, commesse, fatture, comunicazione, trasferte e analisi
dipendono da loro. Anagrafiche pulite decidono se i processi
successivi — dalla registrazione dei tempi alla consegna DATEV —
funzionano senza rilavorazioni.

## Prerequisiti

- Il diritto di gestire clienti o fornitori (di norma amministrazione
  o vendite).
- Per l'importazione al posto dell'inserimento manuale: la procedura
  guidata CSV.
- Identificativi esterni (numero debitore, codici delle integrazioni
  di fatturazione) se si consegnano documenti.

## Procedura consigliata

1. **Cercare prima di creare:** verifichi se il partner esiste già —
   così non nascono duplicati. I duplicati esistenti si possono
   unire; la cronologia segue.
2. Crei il contatto con nome, indirizzo e referenti.
   Se, secondo i **Formati di numerazione**, un programma di contabilità
   assegna i numeri cliente, il campo è bloccato: il cliente viene
   trasferito al programma alla creazione e ne riceve il numero.
   Inserisca il referente con appellativo, titolo, nome, altri nomi e
   cognome; ogni riga dell'elenco dei referenti ha gli stessi campi. I
   programmi di contabilità li ricevono separatamente.
3. Completi dati di pagamento e fatturazione e gli identificativi
   esterni — guidano fatturazione e consegna contabile.
4. Colleghi progetti, sedi e accordi man mano che nascono.
   Una nuova sede riprende come proposta indirizzo, paese e coordinate del cliente scelto.

![Elenco clienti con numeri, contatti, tariffe orarie e numero di progetti](media/kunden/kundenliste.png)
*L’elenco clienti: anagrafica, tariffa oraria e progetti collegati per partner.*

**Enti pubblici e destinatari di tributi:** se registra l'ufficio delle
imposte, le casse malattia, l'istituto di assicurazione contro gli infortuni
sul lavoro o il comune come fornitori affinché la contabilità possa
registrare imposte e contributi, vi spunti **Nascondi nelle analisi**. I
documenti restano conservati; le analisi dei fornitori tralasciano questi
enti. Per i clienti, la stessa casella agisce sui report su clienti e
progetti.

**Comunicazione:** registri chiamate, e-mail e impegni come nota di
comunicazione sul cliente o sul fornitore. Le note compaiono nella pagina di
dettaglio e nell’elenco centrale delle note; un riscontro di accesso ai dati
relativo a un fornitore le elenca con numero e periodo.

**Accessi al portale:** nella sezione **Accessi al portale** della scheda
cliente invita i referenti nel portale clienti con **Invita accesso**; il
contatto imposta autonomamente la password tramite il link dell'invito. Finché
l'invito è aperto o scaduto, è disponibile **Invia di nuovo l'invito**. Per
gli accessi attivi, **Reimpostare l’accesso** reimposta l'accesso dopo una
richiesta di conferma: la password precedente non è più valida da subito,
tutte le sessioni vengono chiuse e il contatto riceve un nuovo invito; i
metodi a due fattori configurati restano attivi. Se il contatto ha solo
dimenticato la password, non serve: la reimposta autonomamente nella pagina di
accesso del portale tramite **Password dimenticata?**. **Disattiva**
disconnette subito l'accesso e blocca il login, **Riattiva** annulla il
blocco. Le aree visibili per un accesso sono stabilite dalla configurazione
del portale del cliente.

Se un contatto ha perso tutti i metodi a due fattori e i codici di recupero, **Reimposta il secondo fattore** rimuove tutti i metodi dopo una conferma della password e una richiesta di conferma e termina tutte le sessioni. Il contatto riceve un’e-mail al riguardo e accede poi con la propria password; se la Sua organizzazione richiede l’autenticazione a due fattori, la configura nuovamente in quel momento. Verifichi prima la sua identità, ad esempio richiamandolo.

**Crea accesso** crea un accesso senza e-mail di invito, ad esempio al telefono o in un colloquio: dopo **Nome** ed **E-mail** la scheda cliente mostra una sola volta una **password iniziale** casuale. La consegni di persona o per telefono, mai per e-mail; il portale non la invia e non la salva da nessuna parte in chiaro. Fino al primo accesso l'accesso mostra **Password iniziale in sospeso**; al primo accesso il contatto deve impostare una propria password. Se l'ha persa, **Genera password iniziale** ne crea una nuova (la password precedente non è più valida da subito, tutte le sessioni terminano). Entrambe le azioni richiedono una conferma della Sua password e vengono registrate.

**Visualizza portale** apre il portale clienti in una nuova scheda così come lo vede questo contatto – con le sue aree, i documenti e le fatture – e aiuta con domande come «Dove trovo …?». L'anteprima è solo una visualizzazione: le azioni sono bloccate, le pagine di sicurezza e password non sono raggiungibili e non conta come accesso del contatto. Termina con **Termina anteprima** nella barra gialla o nella scheda cliente, al più tardi dopo 60 minuti, e viene registrata con inizio e fine. Richiede il permesso **Visualizzare il portale clienti come cliente**; senza un accesso non c'è anteprima: ne crei prima uno.

**Visibilità del portale:** accanto alle aree sceglie qui il **periodo visibile dei tempi** – **predefinito dell'organizzazione** (impostazione **Portale clienti** nelle impostazioni dell'organizzazione, di norma tre anni civili, anno in corso compreso), un numero fisso di anni o **illimitato**. È un limite di visualizzazione nel portale, non una cancellazione. L'abilitazione **Analisi** mostra al cliente indicatori e grafici – solo dalle aree anch'esse abilitate (tempi nel livello di dettaglio impostato, fatture, conto di fatturazione, ticket); come ogni nuova abilitazione parte «disattivata».

**Rimuovere un accesso:** un accesso che **non ha mai effettuato il login** (invito in sospeso o scaduto, password iniziale in attesa) si elimina definitivamente con **Elimina accesso** – non vi sono prove collegate. Se il contatto ha già effettuato il login, il record resta: disattivi l'accesso e scelga **Rimuovi accesso**. Nome, indirizzo e credenziali decadono, l'indirizzo e-mail torna disponibile per un nuovo invito, e conferme foto, domande e richieste restano come prova («Ex accesso al portale»). Entrambe le operazioni richiedono una conferma della password e vengono registrate sul cliente; gli accessi rimossi compaiono solo nella riga riepilogativa sotto l'elenco. Il controllo dei termini di conservazione (area **Accessi al portale**) propone gli accessi disattivati per la rimozione dopo un anno.

## Fattura elettronica

Nella finestra del cliente i campi della fattura elettronica si trovano nella
sezione **Fatturazione e visualizzazione**:

- **Formato e-fattura predefinito**: preimpostazione per le nuove fatture e per
  l'importazione di file di questo cliente, ad esempio **XRechnung (XML)** o
  **ZUGFeRD (PDF ibrido)**; **Nessuno standard (PDF)** mantiene il PDF.
- **Leitweg-ID / riferimento acquirente (BT-10)**: dato obbligatorio della
  XRechnung – per le amministrazioni la Leitweg-ID, altrimenti un riferimento
  indicato dal cliente. Se il valore sembra una Leitweg-ID o il cliente è un
  committente pubblico, la finestra lo salva normalizzato (maiuscole, senza
  spazi) e ne verifica struttura e cifra di controllo secondo la specifica di
  formato della KoSIT. Una Leitweg-ID non valida viene salvata comunque; la
  finestra avvisa indicando il motivo, ad esempio una cifra di controllo errata.
- **Identificativo partecipante Peppol**: identificativo del destinatario per
  l'invio tramite Peppol nella forma schema:identificativo, ad esempio con la
  partita IVA o la Leitweg-ID; vuoto significa nessun invio Peppol a questo
  cliente. Con lo schema 0204 deve seguire una Leitweg-ID valida, altrimenti la
  finestra rifiuta l'identificativo: a un indirizzo errato non si può
  recapitare nulla.
- **Profilo destinatario della fattura elettronica**: **Pubblica
  amministrazione** invia solo l'XML via e-mail, richiede la Leitweg-ID,
  incorpora i documenti e allega automaticamente l'attestazione delle
  prestazioni; **Standard** vale per tutti gli altri clienti, **Valore
  dell’organizzazione** riprende l'impostazione dell'organizzazione.
- **Allegare l’attestazione delle prestazioni**: **Secondo il profilo
  destinatario**, **Sempre** o **Mai**; l'attestazione nasce all'emissione dai
  tempi fatturati.
- **Fattura elettronica più grande con documenti (MB)**: limite massimo per la
  fattura elettronica con i documenti incorporati; se vuoto vale il limite del
  profilo (standard 15 MB, amministrazione 10 MB).
- **Canale di invio con fatturazione esterna**: se un sistema di fatturazione
  gestisce numero e contenuto della fattura, la invia quel sistema oppure
  WorkDiary invia la fattura con le attestazioni – in tal caso disattivi l'invio
  automatico nel sistema di fatturazione. Vede questo campo solo con il permesso
  **Gestire la configurazione finanziaria**.

Nella sezione **Portale e altro**, accanto alla partita IVA, si trova il
**numero di registro (registro delle imprese)**, ad esempio «Amtsgericht
Musterstadt HRB 12345». La fattura elettronica lo riporta come identificativo di
registrazione del cliente (BT-47). Se il cliente è debitore dell'imposta (§ 13b
UStG) e non ha una partita IVA, il numero di registro basta per la fattura
elettronica.

Come i documenti entrano nella fattura elettronica e cosa vale per i clienti
della pubblica amministrazione lo descrive l'argomento di aiuto **Fatture &
documenti**.

## Esempio pratico

Un fornitore IT crea «Müller GmbH» con indirizzo di fatturazione,
termini di pagamento e numero debitore dello studio. Quando più tardi
nasce il primo lotto DATEV, nessun documento è bloccato da anagrafiche
mancanti.

## Errori tipici

- **Creare duplicati** perché nessuno ha cercato prima — analisi e
  cronologia si frammentano.
- **Cancellare relazioni storiche:** meglio disattivare o archiviare i
  contatti inutilizzati; documenti e tempi restano tracciabili.
- **Cambiare i dati di fatturazione «al volo»:** le modifiche valgono
  per il futuro; i documenti già creati mantengono volutamente lo
  stato documentato.

## Effetti e prossimi passi

Le modifiche anagrafiche valgono solo in avanti — le consegne chiuse
restano invariate. Poi: creare i progetti del cliente, controllare i
dati di fatturazione e usare l'import CSV per grandi quantità.

## Fornitore: dati fiscali e autofatturazione

Sul fornitore si registrano **partita IVA** e **codice fiscale**. Se la sua organizzazione fattura essa stessa le prestazioni di un fornitore, inserisca la data dell’**accordo di autofatturazione**; solo allora si possono emettere autofatture. La pagina del fornitore mostra l’accordo e le ultime autofatture.

## Fatture e documenti nella scheda

Oltre alle fatture create in WorkDiary, la scheda **Fatture e documenti**
mostra i documenti dei programmi di contabilità collegati per il periodo
dell’intestazione: Lexoffice, sevDesk, easybill, orgaMAX, Invoice Ninja,
Akaunting, JTL-Wawi e InvoicePlane per clienti e fornitori. Un programma compare finché la sua importazione dei documenti è configurata per
la Sua organizzazione; la colonna «Fonte» lo indica. I documenti vengono
assegnati durante l’importazione tramite il collegamento del contatto, il
numero cliente o il nome del fornitore; se manca il collegamento, la scheda
vuota lo segnala. Dove il programma fornisce un PDF (fatture e note di
credito sevDesk, easybill, fatture orgaMAX), **PDF originale** lo scarica
direttamente. I documenti in
entrata di questi programmi si possono inoltre assegnare come costi di
materiale del cliente e negli acquisti di abbonamenti e licenze.

## Trasferire i fornitori alla contabilità

Se WorkDiary guida i dati anagrafici, i fornitori vengono trasferiti al
programma di contabilità come i clienti: Lexoffice, sevDesk, easybill e
orgaMAX. Il trasferimento avviene alla creazione, se il programma assegna il
numero fornitore secondo i **Formati di numerazione**, e dopo modifiche a un
fornitore già collegato. easybill e orgaMAX possono assegnare il numero
fornitore; sevDesk crea un contatto della categoria «Fornitore» senza un
proprio numero fornitore, quindi resta il numero di WorkDiary. orgaMAX accetta
fornitori solo se nell’amministrazione orgaMAX è attiva la funzionalità
«Fornitori» ed è impostato «WorkDiary guida»; altrimenti orgaMAX guida i
fornitori e WorkDiary li collega soltanto.

Questo non raggiunge i fornitori precedenti al collegamento. **Trasferisci alla
contabilità** nella scheda del fornitore ne trasferisce uno al primo programma
attivo che accetta fornitori; la gestione può trasferirli tutti in una volta
con `php artisan accounting:push-contacts <programma> --suppliers`. Un
fornitore già collegato viene aggiornato, non creato due volte; i fornitori
archiviati e il fornitore collettivo restano esclusi. Se la contabilità guida i
dati anagrafici, il pulsante non compare e l’elaborazione non trasferisce nulla.

## Dati anagrafici dal programma di contabilità

Se la titolarità dei dati anagrafici è impostata su «Guida la contabilità»,
WorkDiary non trasferisce contatti. Clienti e fornitori arrivano invece dal
programma: Lexoffice e orgaMAX tramite la propria sincronizzazione dei
contatti, sevDesk ed easybill ogni ora secondo queste regole:

- Un contatto già collegato assume lo stato del programma (nome, partita IVA,
  codice fiscale, indirizzo, e-mail e telefono; con easybill anche il
  referente).
- Una corrispondenza sicura, ad esempio tramite partita IVA, viene collegata.
- Tutto ciò che non è chiaro compare come caso da chiarire nella **Inbox di
  riconciliazione**; non viene creato nulla.

sevDesk indica il ruolo tramite la categoria del contatto: vengono importati
«Cliente» e «Fornitore», non partner, potenziali clienti e referenti di
un’azienda. L’indirizzo è l’indirizzo sevDesk della categoria che WorkDiary
usa nel trasferimento, altrimenti l’unico indirizzo; per e-mail, telefono,
cellulare, fax e sito web vale quello contrassegnato come principale,
altrimenti l’unico. Se ce ne sono più di uno senza questi contrassegni, il
valore in WorkDiary resta invariato, così come il paese. easybill distingue
tramite numero cliente e numero fornitore; un contatto con entrambi i numeri è
cliente e fornitore. I contatti easybill archiviati restano esclusi.

## Cockpit cliente e fornitore

La scheda raccoglie ciò che gli altri moduli sanno del contatto: per i clienti
i ticket aperti, i contratti con la prossima scadenza, gli accordi, i termini
di garanzia, le fideiussioni, i sondaggi con NPS, i domini e gli abbonamenti e
licenze; per i fornitori gli ordini, le attestazioni obbligatorie, i contratti
e le scadenze. Una sezione compare solo se il modulo è attivo, se dispone del
permesso e se esistono voci; i numeri corrispondono all'elenco relativo.
L'amministrazione vede gli ordini del negozio da Etsy e Billbee. Come i
clienti, i fornitori hanno campi personalizzati e documenti.

Per il fornitore si aggiungono i **Cataloghi** (nome, numero di articoli, ultima importazione; con il diritto di lettura del magazzino) e le **Prestazioni**: la scorecard degli ultimi dodici mesi con punteggio complessivo, puntualità, tasso di reclami, andamento dei prezzi, qualità ISMS, comportamento nei regressi e tempo di consegna — ogni riga apre la scorecard sullo stesso periodo. I contratti di leasing e finanziamento con il fornitore come locatore compaiono nella scheda con il diritto ai fascicoli di leasing. Per il cliente compaiono inoltre gli **Appuntamenti** prenotati e richiesti (Calendly, prenotazione appuntamenti) per chi può aprire la pagina «Richieste di appuntamento».

**Guida la contabilità:** se, secondo la titolarità dei dati, la contabilità gestisce i dati anagrafici di clienti e fornitori, il modulo mostra un avviso. Si può ancora modificare tutto, ma la prossima sincronizzazione sovrascrive le modifiche locali: le modifiche durature vanno fatte nel programma contabile.
