---
title: "Organizzazione e impostazioni"
topic: admin.organization-settings
version: 1
keywords:
    - impostazioni aziendali
    - impostazioni del tenant
    - impostazioni dell'organizzazione
    - servizio mappe
    - servizio meteo
    - calendario festività
    - festività regionali
    - modalità manutenzione
    - 2FA obbligatoria
    - livelli di sollecito
    - geocodifica
    - calcolo percorso
audience:
    - admin
related:
    - admin.tenants
    - admin.settings
    - admin.license
    - reports.arbzg-compliance
    - catalog.holidays
    - finance.dunning
    - invoices.manage
    - accounting.fixed-assets
    - account.ai-assistant
    - admin.notification-rules
    - dispatch.board
    - tours.manage
---

Nella finestra di dialogo **Modifica organizzazione** Lei gestisce i dati
anagrafici della Sua organizzazione e tutte le impostazioni che valgono per i
suoi membri: regole sull'orario di lavoro e livelli di approvazione, valori
predefiniti per fatture e solleciti, servizi di mappe e meteo, la regione
delle festività e la modalità manutenzione. La apre dal menu di sistema
(l'icona a ingranaggio **Sistema** nell'intestazione) sotto
**Organizzazione** → **Organizzazione**. La voce è disponibile per gli
amministratori e apre sempre la propria organizzazione; la gestione della
piattaforma raggiunge la stessa finestra dall'elenco **Organizzazioni**.

## Come agiscono le impostazioni

- **Validità:** ogni valore vale per l'intera organizzazione. Dove membri,
  clienti, progetti o siti possono avere valori propri, la sezione relativa lo
  indica; i loro valori hanno allora la precedenza.
- **Valori predefiniti:** molti campi sono vuoti e mostrano in grigio
  «Predefinito …», per esempio «Predefinito 25». Un campo vuoto adotta il
  valore predefinito del sistema: un valore che il gestore ha impostato nelle
  impostazioni di sistema, altrimenti il valore integrato indicato dal testo
  grigio. Un valore inserito vale solo per la Sua organizzazione e ha la
  precedenza su qualsiasi valore di sistema.
- **Ripristinare:** se svuota un campo e salva, il Suo valore viene rimosso e
  torna a valere il valore predefinito.
- **Campi precompilati:** le sezioni senza testo grigio, come i limiti
  dell'orario di lavoro o i livelli di approvazione, mostrano il valore in
  vigore e lo salvano di nuovo al prossimo salvataggio.
- **Salvare:** **Salva** registra tutte le sezioni e tutte le schede in una
  volta. Se un valore è fuori dall'intervallo consentito, la finestra lo
  segnala nel campo e non salva nulla.
- **Eliminare e disattivare:** la disattivazione, l'esportazione dei dati e
  l'eliminazione definitiva di un'organizzazione sono riservate alla gestione
  della piattaforma (veda «Organizzazioni e tenant»); la finestra non mostra
  pulsanti per queste azioni.

## Dati anagrafici

- **Nome** (obbligatorio, al massimo 255 caratteri): nome
  dell'organizzazione. Serve anche come nome azienda della fattura
  elettronica finché lì non ne è indicato un altro.
- **Lingua** (obbligatorio): lingua dell'interfaccia per tutti i membri che
  non hanno scelto una lingua propria, e lingua delle loro notifiche ed
  e-mail. Fatture, offerte, solleciti e documenti di trasporto compaiono in
  questa lingua se il cliente non ha una lingua dei documenti propria.
- **Fuso orario** (obbligatorio): fuso orario di visualizzazione per i membri
  senza un fuso proprio. Determina anche i limiti del giorno come «oggi» e
  l'inizio della settimana.
- **Formato data** e **Formato ora**: valore predefinito per tutti i membri
  che non hanno scelto un formato proprio nel profilo. L'elenco mostra ogni
  formato con un esempio; **— Predefinito —** adotta il formato di sistema.
- **Timbrature dimenticate**: come vengono integrate le timbrature mancanti –
  **Il dipendente richiede – le risorse umane approvano** (predefinito) oppure
  **Il dipendente può integrare autonomamente**. Queste integrazioni sono
  sempre contrassegnate come «manuale» e restano visibili nella inbox di
  correzione.

## Piano e stato

- **Piano**: tariffa dell'organizzazione – **Gratuito**, **Pro** o
  **Enterprise**. Qui è solo visualizzato: i moduli abilitati dipendono dalla
  licenza installata e, all'installazione di una licenza, l'organizzazione ne
  assume il piano. Può modificarlo solo la gestione della piattaforma, perché
  il passaggio a un piano più piccolo avvia per i moduli esclusi un periodo di
  tolleranza di 30 giorni, dopo il quale un'elaborazione notturna elimina i
  dati dei moduli eliminabili.
- **Attivo**: anche il blocco dell'organizzazione lo imposta solo la
  gestione della piattaforma. I membri di un'organizzazione bloccata vedono
  soltanto il messaggio di organizzazione disattivata con l'invito a
  rivolgersi al gestore.
- **Sicurezza** – **Autenticazione a due fattori obbligatoria per tutti i
  membri**: chi non ha ancora configurato un secondo fattore viene condotto
  alla configurazione dopo l'accesso e può continuare a lavorare solo dopo;
  ciò vale anche per gli accessi al portale clienti. Finché l'obbligo è
  attivo, l'ultimo fattore non può essere rimosso e l'autenticazione a due
  fattori non può essere disattivata.

## Modalità conformità

**Modalità** stabilisce con quale rigore reagiscono i controlli sull'orario
di lavoro secondo la legge tedesca sull'orario di lavoro (ArbZG):

- **Disattivato**: nessun controllo – né nella pianificazione di servizi e
  turni né nell'analisi ArbZG dei tempi registrati e dei relativi casi da
  chiarire.
- **Avvisa** (predefinito): le violazioni vengono mostrate, ma il salvataggio
  avviene comunque.
- **Blocca**: un turno pianificato con una violazione grave non può essere
  salvato, a meno che la persona che pianifica non scavalchi deliberatamente
  il controllo nella finestra del turno. Sono gravi la sovrapposizione, un
  riposo troppo breve, l'orario giornaliero superato e un turno durante ferie
  approvate; le altre regole si limitano ad avvisare.

## Modello orario di lavoro

**Tipo di orario di lavoro predefinito** è il modello orario di tutti i
membri che non ne hanno uno proprio, nonché la precompilazione dei nuovi
modelli: **Orario flessibile** (predefinito), **Orario settimanale fisso**,
**Per giorno della settimana** oppure **Orario di lavoro fiduciario**. Un
modello proprio della persona ha la precedenza.

## Limiti orario di lavoro

I limiti valgono per la pianificazione di servizi e turni e per l'analisi
ArbZG dei tempi registrati:

- **Max ore/giorno** (1–24, predefinito 10): orario giornaliero senza pause.
- **Riposo min. (h)** (1–24, predefinito 11): riposo tra due giornate
  lavorative o due turni.
- **Max ore/settimana** (1–168, predefinito 48).
- **Max giorni consecutivi** (1–14, predefinito 6): giorni lavorativi
  consecutivi nella pianificazione dei turni.
- **Orario notturno dalle (ora)** (20–23, predefinito 23) e **Orario
  notturno fino alle (ora)** (4–7, predefinito 6): finestra notturna per il
  controllo del lavoro notturno. Secondo l'ArbZG va dalle 23 alle 6, in
  panetterie e pasticcerie dalle 22 alle 5.
- **Tolleranza fascia oraria (min.)** (0–240, predefinito 15): un caso da
  chiarire nasce solo se le timbrature superano la fascia oraria del modello
  orario di oltre questi minuti.
- **Semaforo orario flessibile: giallo da (min.)** (predefinito 1200, cioè 20
  ore) e **Semaforo orario flessibile: rosso da (min.)** (predefinito 2400,
  cioè 40 ore): colorazione del saldo dell'orario flessibile nel conto orario
  e nella dashboard. Ore in più e in meno contano allo stesso modo. Se il
  valore rosso è inferiore a quello giallo, il valore giallo vale anche per il
  rosso.

## Approvazioni

- **Livelli di approvazione ferie**, **Fasi di approvazione degli
  straordinari** e **Fasi di approvazione delle correzioni orarie**: ciascuno
  **Un livello (una approvazione)** (predefinito) oppure **Due livelli
  (principio dei quattro occhi)** – una richiesta richiede allora due
  approvazioni.
- **Commerciale: ruolo responsabile**, **Tecnica: ruolo responsabile** e
  **Risorse umane: ruolo responsabile**: le fasi di approvazione di una
  trattativa contrattuale compaiono nella casella delle approvazioni sotto
  «Approvazioni» per il ruolo assegnato al loro tipo di fase. Si possono
  scegliere tutti i ruoli tranne Cliente. Se il campo è vuoto vale il
  predefinito: **Predefinito (Contabilità)**, **Predefinito (Capo team)**
  oppure **Predefinito (Gestione del personale)**. L'approvazione
  direttamente dalla pratica resta possibile.
- **Registrare subito con riserva le assenze richieste (il rifiuto le
  rimuove)**: le assenze richieste valgono già prima dell'approvazione nella
  pianificazione, contrassegnate come riserva; un rifiuto le rimuove. Si
  fattura ed esporta comunque solo ciò che è approvato.
- **Attivare il pannello presenze (presenza attuale)**: abilita la pagina
  **Presenza attuale** (disattivato di fabbrica).

## Tempi di guida e di riposo

**Applicare le regole sui tempi di guida (veicoli contrassegnati "Applicare le
regole sui tempi di guida e di riposo")** (disattivato di fabbrica) controlla
i viaggi rispetto ai limiti del regolamento (CE) 561/2006 e della FPersV
tedesca: tempo di guida giornaliero e settimanale, interruzioni di guida e
riposo giornaliero e settimanale. Vengono controllati solo i viaggi con
veicoli su cui è impostato anche **Applicare le regole sui tempi di guida e
di riposo** – entrambi gli interruttori devono essere attivi. Non si tratta di
consulenza legale: quali norme valgano nel singolo caso lo chiarisce
l'azienda.

## Regole attive

Qui Lei disattiva singoli controlli; di fabbrica sono tutti attivi. Una
regola disattivata non viene più controllata, e la modalità **Disattivato**
le disattiva tutte. Le prime otto regole riguardano la pianificazione dei
turni:

- **Turni sovrapposti**: due turni della stessa persona si sovrappongono.
- **Riposo minimo**: il riposo tra due turni è troppo breve.
- **Orario di lavoro giornaliero** e **Orario settimanale**: il limite è
  superato.
- **Giorni consecutivi**: più giorni lavorativi di fila di quanto consentito.
- **Conflitto di ferie**: il turno cade in ferie richieste o approvate.
- **Corrispondenza qualifiche**: alla persona manca una qualifica richiesta
  dal fabbisogno di personale del turno.
- **Registrazione festivo**: il turno cade in un giorno festivo gestito sotto
  **Giorni festivi**.

Le ultime quattro controllano le timbrature e generano casi da chiarire
nell'analisi ArbZG:

- **Timbratura di uscita dimenticata**: una presenza resta aperta oltre la
  giornata.
- **Timbratura in giorno libero**: timbratura in un giorno libero secondo il
  modello orario o il piano turni.
- **Timbratura durante assenza**: timbratura nonostante un'assenza approvata
  di un'intera giornata, come ferie o malattia.
- **Fascia oraria (timbrature)**: timbratura fuori dalla fascia oraria, oltre
  la tolleranza.

## Impostazioni avanzate

L'ultima sezione raccoglie altri valori predefiniti in schede: **Elenchi**,
**Fatturazione**, **Caricamenti**, **Limiti di immissione**, **Notifiche**,
**Interfaccia**, **Routing e mappe**, **Trasferta**, **Regione e festività**,
**Meteo** e **Manutenzione**. Oltre ai valori di fatturazione, la scheda
**Fatturazione** contiene anche solleciti, fattura elettronica, cespiti,
spedizione e dogana, pagamento online, assistenti IA, condizioni di noleggio,
parco veicoli, schemi di reclami, problemi ricorrenti e importazione tempi.
Per tutte le schede vale l'indicazione «Lasci vuoto per usare il valore
predefinito del sistema.» Le sezioni seguenti seguono l'ordine delle schede.

## Elenchi

Quante voci un elenco mostra per pagina, ciascuna da 1 a 500: **Fogli ore**,
**Piani turni**, **Clienti**, **Giri**, **Veicoli**, **Etichette**,
**Organizzazioni** (elenco della gestione della piattaforma) e i tre elenchi
della inbox di teleassistenza (**Inbox teleassistenza: dispositivi non
assegnati**, **Inbox teleassistenza: dispositivi multi-cliente**, **Inbox
teleassistenza: sessioni per scheda dispositivo**). I campi **Ricerca clienti
(digitazione predittiva)**, **Allegati cliente**, **Archivio** e **Dashboard:
elementi recenti** attualmente non hanno effetto; quante voci recenti mostra
la dashboard si imposta nella scheda **Interfaccia**.

## Fatturazione

- **Aliquota fiscale predefinita (%)**: se vuota, workDiary determina
  l'aliquota delle fatture nazionali dalle regole fiscali. Un'aliquota
  inserita vale per tutte le fatture nazionali create localmente e ha la
  precedenza sulle regole fiscali.
- **Valuta predefinita (ISO-4217)**: valuta predefinita dell'organizzazione;
  gli importi di un documento restano nella valuta del cliente.
- **Unità di tempo per le voci** (fino a 8 caratteri, predefinito h): unità
  delle voci di tempo nel trasferimento.
- **Prestazione predefinita (articolo)**: fornisce denominazione, unità,
  testo standard e – se non si trova alcuna tariffa – il prezzo delle
  posizioni di trasferimento. Le regole di fatturazione del progetto hanno la
  precedenza. Senza articoli nell'anagrafica articoli l'elenco resta vuoto.
- **Modello: testo introduttivo del trasferimento** e **Modello: nota finale
  del trasferimento** (fino a 2000 caratteri ciascuno): vengono copiati nella
  ricevuta alla creazione di un trasferimento e lì sono modificabili.
  Segnaposto: :customer, :from, :to, :channel. Senza modello per la nota
  finale vale il testo di fattura del cliente.
- **Tariffa oraria predefinita (ricavo)**: vale quando né la registrazione,
  né la condizione cliente, né il collaboratore, né l'attività, né il
  progetto, né il cliente impostano una tariffa. Se vuota, questi tempi
  restano a 0,00 €.
- **Tariffa oraria di calcolo montaggio**: valuta il tempo di montaggio di un
  articolo nella proposta di prezzo di vendita; se vuota vale la tariffa
  oraria predefinita.
- **Incremento di fatturazione predefinito (minuti)** (1–1440): arrotonda per
  eccesso il tempo fatturabile a questo incremento quando né il progetto né il
  cliente ne impostano uno; vuoto = al minuto.
- **Intervallo di raggruppamento predefinito (minuti)** (0–1440): le
  registrazioni separate al massimo da questo intervallo vengono unite in un
  blocco in fatturazione; vuoto = nessun raggruppamento.
- **Canale di fatturazione**: canale di fatturazione predefinito
  dell'organizzazione, per esempio **WorkDiary (locale)** o **Lexoffice
  guida**; i clienti possono sostituirlo singolarmente. Se vuoto vale **—
  WorkDiary (predefinito) —**. Il campo compare solo con il diritto «Gestire
  la configurazione finanziaria».

Come nascono le fatture è descritto nel tema «Fatture & documenti».

## Solleciti

Valori predefiniti per livello per il sollecito singolo e la serie di
solleciti; la procedura è descritta nel tema «Solleciti».

- Per ciascuno dei livelli da 1 a 3: **Livello 1: tolleranza (giorni)** e
  così via – al livello 1 i giorni di ritardo prima che il promemoria di
  pagamento sia dovuto, ai livelli 2 e 3 i giorni dall'ultimo sollecito
  (predefinito 7 ciascuno); **Livello 1: spese (EUR)** e così via
  (predefinito 0,00); **Livello 1: termine di pagamento (giorni)** e così via
  (predefinito 14, 10 e 7 giorni).
- **Calcolo degli interessi di mora**: **Tasso fisso** (predefinito) oppure
  **Tasso base + punti percentuali** – il tasso base secondo il § 247 BGB
  viene rilevato mensilmente dalla Bundesbank.
- **Maggiorazione (punti percentuali)**: solo in modalità tasso base.
  Riferimento secondo il § 288 BGB: 5 punti verso i consumatori, 9 tra
  imprese; l'importo lo stabilisce la Sua azienda.
- **Interessi di mora (% p. a.)**: solo con tasso fisso; 0 = nessuna
  indicazione di interessi.

Gli interessi di mora compaiono solo nella lettera di sollecito; non vengono
registrati in contabilità.

## Fattura elettronica (XRechnung)

Dati del venditore per l'output XRechnung (EN 16931) delle fatture create
localmente: **Nome azienda** (vuoto = nome dell'organizzazione), **Via e
numero civico**, **CAP**, **Città**, **Codice paese (ISO 3166-1)**,
**Partita IVA**, **Codice fiscale**, **Contatto: nome**, **Contatto: e-mail**
(indirizzo valido), **Contatto: telefono**, **IBAN**, **BIC** e
**Intestatario del conto**.

Tre campi agiscono inoltre su tutte le fatture create localmente:

- **Codice paese (ISO 3166-1)** (due lettere, predefinito DE): paese del
  venditore. Il calcolo dell'imposta lo usa per distinguere fatture
  nazionali, UE ed extra UE.
- **Termine di pagamento (giorni)** (0–365): vale quando né la fattura né il
  cliente hanno un termine di pagamento; vuoto o 0 = 14 giorni.
- **Piccola impresa (§ 19 UStG)**: le fatture non indicano l'IVA e riportano
  la nota «Nessuna IVA ai sensi del § 19 UStG (regime delle piccole
  imprese).»; la XRechnung riceve la categoria fiscale E (esente).

## Cespiti: beni di modesto valore e fondo collettivo

Limiti di valore (netti) per il registro dei cespiti. I valori predefiniti
seguono il § 6 c. 2/2a EStG (stato 2026); li verifichi in caso di modifiche di
legge.

- **Limite beni di modesto valore** (predefinito 800): limite per
  l'ammortamento immediato dei beni di modesto valore.
- **Fondo da (oltre)** (predefinito 250), **Fondo fino a** (predefinito 1000)
  e **Anni del fondo** (1–20, predefinito 5).
- **Aumento dei prezzi per la previsione di sostituzione (% annuo)** (0–50,
  predefinito 0).

I dettagli si trovano nel tema «Registro dei cespiti e ammortamento».

## Spedizione e dogana

**Numero EORI**: numero doganale dell'azienda (codice paese e fino a 15
caratteri, ad es. DE1234567). Compare come dato del mittente sulle fatture
commerciali e proforma per spedizioni fuori dall'UE.

## Pagamento online

- **Fornitore di pagamento**: necessario solo se sono attivi più fornitori;
  predefinito **Automatico (primo fornitore attivo)**. I fornitori (Stripe,
  Mollie o SumUp) si attivano come plugin con le proprie credenziali.
- **Link di pagamento sulla fattura e nell'e-mail** (attivo di fabbrica):
  link di pagamento e codice QR compaiono sulla fattura e nell'e-mail. Se
  disattivato, il pagamento online resta possibile nel portale clienti.

## Assistenti IA (MCP)

**Consenti assistenti IA tramite MCP** (disattivato di fabbrica): assistenti
IA come Claude o ChatGPT possono collegarsi con il consenso dei singoli utenti
e leggere o creare bozze con i loro permessi. Se disattivato non è possibile
un nuovo collegamento; quelli esistenti non ricevono strumenti e non possono
essere rinnovati. Il collegamento stesso è descritto in «Collegare
l’assistente IA».

## Condizioni di noleggio attrezzature

- **Consegna solo con condizioni di noleggio firmate**: le condizioni di
  noleggio si gestiscono come accordo cliente «Condizioni di noleggio
  (noleggio attrezzature)» con versione e firma.
- **Consentire la prenotazione diretta nel portale clienti**: i clienti
  prenotano subito e in modo vincolante i dispositivi liberi abilitati per il
  portale; la direzione viene informata.
- **Raggio intorno al luogo d'impiego (m)** (50–50 000, predefinito 500): se
  la posizione segnalata di un'attrezzatura noleggiata è più lontana dalla
  sede del noleggio, lo scostamento viene segnalato. Senza sede valgono i
  geofence del cliente.

## Parco veicoli

**Nessun nuovo viaggio se un controllo obbligatorio è scaduto** (disattivato
di fabbrica): se la revisione, il controllo antinfortunistico o un altro
controllo obbligatorio del cespite associato è scaduto o bloccato, da oggi
non si possono più registrare viaggi. I viaggi passati restano documentabili.

## Schemi di reclami

Da quanti reclami simili nasce un'indicazione – stesso lotto, stesso articolo
con lo stesso tipo di difetto o causa, oppure stesso fornitore: **Soglia
(casi)** (2–50, predefinito 3) entro la **Finestra (giorni)** (7–365,
predefinito 90).

## Problemi ricorrenti

Questo allarme precoce individua clienti e oggetti per cui nel periodo scelto
arriva un numero vistosamente alto di ticket di assistenza.

- **Ticket da** (2–50, predefinito 3): numero minimo di ticket per un
  allarme.
- **Finestra (giorni)** (7–365, predefinito 90): periodo fino a oggi,
  misurato in base alla data di segnalazione dei ticket.

Come conta workDiary:

- Contano tutti i ticket con un cliente, indipendentemente dal loro stato. I
  ticket con un oggetto contano per cliente e oggetto, quelli senza oggetto
  per cliente.
- Se un cliente o un oggetto raggiunge la soglia, nasce l'allarme «Ticket
  ricorrenti: …» con il numero, il periodo, un link all'oggetto o al cliente e
  la raccomandazione di chiarire la causa con il cliente, verificare o
  sostituire l'oggetto e valutare un'istruzione di lavoro o una formazione.
  Vengono mostrati al massimo i 20 casi con più ticket.
- Gli allarmi compaiono sotto **Report** → **Progetti e clienti** →
  **Problemi e formazione** nell'area **Problemi ricorrenti** (per gli
  amministratori e le persone con il diritto «Visualizza i report») e nel
  riquadro **Anomalie** della dashboard.
- Inoltre la notifica «Allerta precoce dalle analisi» viene inviata una volta
  per cliente o oggetto ai ruoli Capo team e Amministratore. Destinatari e
  canali si modificano sotto **Regole di notifica**.

## Importazione tempi

**Assegna i tempi ai progetti tramite parole chiave** (attivo di fabbrica):
vale per i tempi importati, per esempio da assistenza remota, Toggl o Kimai.
Se il testo di un tempo importato contiene il nome o una parola chiave di un
progetto dello stesso cliente, viene registrato lì invece che nel progetto
predefinito o nella casella di assegnazione. Vengono registrate solo le
corrispondenze univoche.

## Caricamenti

Limiti di dimensione dei caricamenti in kilobyte (da 1 a 1 048 576 KB, cioè
fino a 1 GB): **Importazione CSV** (predefinito 10 240 KB, 10 MB),
**Allegato cliente** (10 240 KB), **Allegati (generale)** (25 600 KB, 25 MB)
e **Dati di stampa** (262 144 KB, 256 MB). I file più grandi vengono
rifiutati al caricamento.

## Limiti di immissione

Limiti di caratteri e di intervallo per i campi dei moduli, ciascuno a
partire da 1:

- **Presenza**: **Nota, caratteri max** (predefinito 1000), **ID
  dispositivo, caratteri max** (64) e **Pausa, minuti max** (600).
- **Etichette**: **Nome etichetta, caratteri max** (60).
- **Commenti**: **Corpo del commento, caratteri max** (5000).
- **Piani turni**: **Nota, caratteri max** (2000).

## Notifiche

**Anteprima del messaggio, caratteri max** (20–500, predefinito 120): tanti
caratteri del testo del messaggio mostra una notifica push; il resto viene
troncato.

## Interfaccia

- **Calendario** – **Durata degli slot in minuti**: griglia della vista
  settimanale; gli appuntamenti senza fine ricevono questa durata. Sono
  consentiti 10, 15, 20, 30 o 60 (predefinito 30).
- **Dashboard** – **Numero di elementi recenti** (predefinito 5): quante voci
  usate di recente mostra la dashboard.
- **Ricerca** – **Limite di risultati predefinito** (predefinito 20):
  attualmente non ha effetto.

## Nominatim (geocodifica)

Nominatim è un servizio di geocodifica basato su OpenStreetMap: converte un
indirizzo in coordinate. workDiary lo usa nel **Registro viaggi**: quando
lascia il campo **Da (indirizzo)** o **A (indirizzo)**, workDiary cerca
l'indirizzo e l'indirizzo trovato compare come suggerimento sul campo.
L'indirizzo inserito viene così trasmesso al servizio configurato.

- **URL base** (indirizzo completo, fino a 255 caratteri): indirizzo del
  servizio Nominatim. Se vuoto vale il valore del gestore. Se un indirizzo
  proprio si trova in una rete interna, per esempio un server gestito in
  proprio, workDiary lo interroga solo se il gestore lo ha abilitato per la
  Sua organizzazione.
- **E-mail di contatto**: viene trasmessa a ogni richiesta. Le regole d'uso di
  Nominatim richiedono che l'applicazione che interroga si identifichi con un
  indirizzo di contatto.
- **Richieste al secondo** (1–50, predefinito 1): workDiary attende di
  conseguenza tra due richieste. Il servizio Nominatim pubblico consente al
  massimo una richiesta al secondo; valori più alti sono pensati solo per un
  server proprio.
- I risultati vengono memorizzati per indirizzo del servizio (di fabbrica 365
  giorni); lo stesso indirizzo non viene richiesto di nuovo in questo periodo.
- Se il servizio non è raggiungibile o non trova nulla, non compare alcun
  suggerimento; l'immissione stessa non ne risente.

## OSRM (routing)

OSRM calcola i percorsi sulla rete stradale. workDiary lo usa

- nell'ottimizzazione di un giro: ordine delle fermate secondo le distanze
  stradali reali, tracciato del percorso sulla mappa e distanza e tempo di
  guida pianificati, a cui si aggiungono i tempi di sosta alle fermate;
- per i **Suggerimenti per tempi morti** della **Centrale operativa**: tempo
  di guida aggiuntivo andata e ritorno per un ordine che entrerebbe in una
  fascia libera.

Se OSRM non è raggiungibile, workDiary prosegue con la distanza in linea
d'aria: i giri restano pianificabili, solo senza tracciato, e i suggerimenti
riportano l'indicazione **stima approssimativa (in linea d'aria)**.

- **URL base** (indirizzo completo, fino a 255 caratteri): indirizzo del
  server OSRM. Per un indirizzo proprio in una rete interna vale la stessa
  abilitazione da parte del gestore prevista per Nominatim.
- **Profilo (es. driving)** (fino a 32 caratteri, predefinito driving):
  profilo di viaggio del server. Quali profili esistono, per esempio per
  bicicletta o a piedi, lo stabilisce il server OSRM.
- **Timeout (secondi)** (1–120, predefinito 10): quanto workDiary attende una
  risposta prima di ripiegare sulla linea d'aria.

## Tile mappa

Le tile della mappa sono le porzioni di immagine di cui sono composte le
mappe di workDiary, per esempio nei **Giri**, sulla mappa della **Centrale
operativa** e nella gestione delle crisi. Il browser di ogni utente le carica
direttamente dal server di tile configurato.

- **Modello URL tile** (indirizzo completo, fino a 255 caratteri): indirizzo
  del server di tile con i segnaposto {z} per il livello di zoom e {x} e {y}
  per la posizione della tile. Il valore predefinito è il server di tile di
  OpenStreetMap. workDiary consente automaticamente al browser di caricare
  immagini da questo server.
- **Zoom massimo** (1–22, predefinito 19): ingrandimento massimo delle mappe.
  Scelga al massimo il livello fornito dal server di tile.
- L'attribuzione in fondo alla mappa è fissata dalla configurazione di base
  del gestore; qui non si può modificare.

## Fatturazione trasferta

Nella scheda **Trasferta** Lei decide se le fatture contengono una trasferta.
Con **Calcola automaticamente la trasferta** (disattivato di fabbrica)
workDiary aggiunge alla fatturazione di progetto o di materiale di un cliente
una voce di trasferta per ogni giro con una fermata presso questo cliente;
la voce riporta la data del giro. I giri annullati e le trasferte già fatturate
non contano.

- **Modalità**: **Importo forfettario** oppure **Chilometri**.
- **Testo della voce** (fino a 50 caratteri, predefinito «Anfahrt»): testo
  della voce di fattura, completato con la data o i chilometri.
- **Importo forfettario (netto €)**: importo per trasferta nella modalità **Importo forfettario**;
  senza importo non nasce alcuna voce.
- **Tariffa (€/km)**: prezzo per chilometro nella modalità **Chilometri**.
- **Fonte dei chilometri**: **Sempre dalla sede aziendale** – distanza in
  linea d'aria dalla sede aziendale all'indirizzo del cliente – oppure **A
  seconda del giro (km effettivi)** – i chilometri del registro viaggi per
  questo cliente in quel giorno, altrimenti la distanza pianificata del giro.
- **Andata e ritorno (×2, solo sede aziendale)**: raddoppia la distanza in
  linea d'aria.
- **Ubicazione dell'azienda latitudine (lat)** e **Ubicazione dell'azienda
  longitudine (lng)**: coordinate della sede aziendale; se vuote vale il punto
  di partenza del giro.

Se nella modalità chilometri mancano le coordinate del cliente, non nasce
alcuna voce. Per singoli clienti Lei sostituisce i valori nella finestra del
cliente sotto **Trasferta (override)**.

## Giurisdizione e festività

**Regione delle festività (Paese / Land)** stabilisce quali festività legali
valgono per la Sua organizzazione. Può scegliere la Germania con **A livello
nazionale (senza festività regionali)** e tutti i 16 Länder, **Austria
(nazionale)** nonché **Svizzera (nazionale)** e tutti i 26 cantoni. Le
festività regionali come il Corpus Domini o il giorno della Riforma valgono
solo in determinati Länder – scelga quindi la regione della Sua azienda. Se il
campo è vuoto vale il valore predefinito del sistema, che la prima voce
dell'elenco indica come «Predefinito …».

La regione agisce ovunque workDiary tenga conto delle festività, tra l'altro
per:

- le maggiorazioni per festività e le condizioni cliente con regola per le
  festività;
- i giorni lavorativi di ferie e malattia, il conto ferie e l'obiettivo
  dell'orario flessibile;
- l'analisi ArbZG, per esempio per il lavoro nei giorni festivi;
- le viste calendario, vista settimanale, piano turni, calendario delle
  assenze e **Presenza attuale**;
- le scadenze SLA dell'assistenza e le scadenze delle dichiarazioni fiscali,
  che slittano al giorno lavorativo successivo;
- i prezzi festivi nel noleggio di attrezzature.

Le festività o i giorni di riposo propri si gestiscono sotto **Giorni
festivi**; valgono in aggiunta alla regione. Un sito può discostarsene sotto
**Siti** nel campo **Regione festività** (predefinito **Regola festività
dell'organizzazione**); ciò vale per le maggiorazioni dei tempi registrati
lì.

## Recupero meteo automatico

**Recupera il meteo automaticamente alla creazione di un verbale**
(disattivato di fabbrica): alla creazione di un verbale workDiary recupera in
background uno snapshot meteo per il luogo e l'orario del verbale e lo allega
come prova.

- Il luogo è dato dalle coordinate del sito a cui si riferisce il verbale,
  altrimenti da quelle del cliente – anche tramite il progetto o l'ordine del
  verbale. Senza coordinate non succede nulla.
- I progetti possono discostarsene: nel progetto, sotto **Recupero meteo
  automatico**, Lei sceglie attivo, disattivo oppure **Eredita (impostazione
  organizzazione)**. La scelta vale anche per i sottoprogetti.
- **Servizio meteo**: **Open-Meteo** (predefinito) funziona in tutto il
  mondo e senza registrazione. **Deutscher Wetterdienst (DWD)** fornisce dati
  ufficiali delle stazioni tedesche (licenza CC BY 4.0, attribuzione
  «Deutscher Wetterdienst»), solo per luoghi in Germania con una stazione a
  portata.
- **DWD: distanza massima dalla stazione (km)** (1–200, predefinito 30): se
  nessuna stazione DWD attiva si trova entro questa distanza, non viene creato
  alcuno snapshot – meglio nessun valore che uno errato.

## Allerte meteo per la pianificazione

**Allerte meteo per interventi pianificati** (attivo di fabbrica): workDiary
controlla ogni ora la previsione giornaliera per gli interventi dei prossimi
tre giorni, oggi compreso, e segnala quando una soglia viene superata.

- Vengono controllati gli ordini con data in questo periodo, assegnati a
  qualcuno o pianificati, e né completati né annullati. Le coordinate
  provengono dall'indirizzo dell'ordine, altrimenti dal cliente; senza
  coordinate nessun controllo.
- Le previsioni le fornisce solo **Open-Meteo**. Se come **Servizio meteo** è
  scelto il DWD, non nasce alcuna allerta.
- Soglie (vuoto = predefinito):
  - **Pioggia (mm/giorno)** – predefinito 20; avvisa a partire da questo
    totale giornaliero.
  - **Raffiche (km/h)** – predefinito 60.
  - **Gelo da minima di (°C)** – predefinito 0; avvisa quando la minima
    raggiunge questa temperatura o scende al di sotto.
  - **Caldo da massima di (°C)** – predefinito 30.
- workDiary segnala ogni superamento esattamente una volta per intervento,
  giorno e soglia – di fabbrica alla persona assegnata e al ruolo Capo team.
  Destinatari e canali si impostano sotto **Regole di notifica** per l'evento
  «Allerta meteo per un intervento»; per questo evento è possibile anche
  l'SMS. Se l'evento lì è disattivato, workDiary non recupera previsioni.

## Modalità manutenzione

Nella scheda **Manutenzione** Lei blocca temporaneamente workDiary per la Sua
organizzazione.

- **Attiva la modalità manutenzione**: tutti i membri che non sono
  amministratori vedono una pagina di manutenzione invece dell'applicazione;
  accesso e disconnessione restano possibili. Gli amministratori continuano a
  lavorare e vedono in alto l'avviso «Modalità manutenzione attiva — gli
  utenti non amministratori vedono attualmente una pagina di manutenzione.»
  con il link **Impostazioni** per tornare a questa finestra.
- **Messaggio mostrato sulla pagina di manutenzione** (fino a 300 caratteri).
- **Fine prevista** (facoltativo): dopo questo momento la modalità
  manutenzione termina automaticamente; l'avviso la mostra come «Fino a: …».
- **Sospendi anche gli ingressi terminale/webhook** (disattivato di
  fabbrica): senza questa spunta i terminali di timbratura e gli ingressi di
  telefonia e posizione continuano a funzionare durante la manutenzione.

L'attivazione e la disattivazione della modalità manutenzione vengono
registrate nel registro di audit.
