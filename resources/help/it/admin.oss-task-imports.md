---
title: "Importazioni di attività da strumenti open source"
topic: admin.oss-task-imports
version: 1
keywords:
    - Vikunja
    - Kanboard
    - Plane
    - Taiga
    - Leantime
    - FreeScout
    - GLPI
    - importazione attività
    - importare ticket
    - self-hosted
    - token API
    - caso da verificare
audience:
    - admin
related:
    - admin.plugins
    - admin.integration-inbox
    - admin.data-ownership
    - projects.manage
    - admin.scheduler
---

Sette plugin acquisiscono elementi da strumenti open source diffusi come
attività nei progetti WorkDiary: **Vikunja**, **Kanboard**, **Plane**, **Taiga**
e **Leantime** per la pianificazione dei progetti, **FreeScout** e **GLPI** per
assistenza e ticket. Così registra il tempo sul lavoro pianificato o richiesto
lì. Ogni importazione legge soltanto: WorkDiary non scrive nulla nel sistema di
origine. Trova le pagine nel menu di sistema (l’ingranaggio **Sistema**) in
**Plugin**, non appena il plugin corrispondente è attivo. Tutti e sette
funzionano allo stesso modo; questo testo indica le differenze per sistema.

## Configurare l’accesso

1. Crei nel sistema di origine un accesso con permesso di lettura (vedi
   tabella).
2. Nella pagina del plugin apra **Credenziali** nel menu ⋯ e inserisca
   l’**indirizzo** e l’accesso. Se l’istanza si trova nella propria rete, attivi
   anche **Consentire indirizzi privati** – ha effetto solo se il gestore lo
   consente.
3. Clicchi su **Verificare la connessione**. WorkDiary accede e mostra istanza,
   indirizzo e – se il sistema lo indica – l’account.

| Sistema | Indirizzo | Accesso |
| --- | --- | --- |
| Vikunja | Indirizzo dell’istanza | Token API da Impostazioni → Token API |
| Kanboard | Indirizzo dell’istanza | Token applicativo da Impostazioni → API (nome utente vuoto) oppure nome utente e token di accesso personale |
| Plane | https://api.plane.so o la propria istanza | Identificativo del workspace e token di accesso personale |
| Taiga | https://api.taiga.io o la propria istanza | Nome utente e password |
| Leantime | Indirizzo dell’istanza | Chiave API dalle impostazioni aziendali |
| FreeScout | Indirizzo dell’istanza | Chiave API del modulo «API & Webhooks» |
| GLPI | Indirizzo dell’istanza | Token utente («chiave di accesso remoto»), token applicazione se richiesto |

Finché esistono associazioni non si può inserire un’altra istanza; così le
attività importate restano chiaramente legate alla loro istanza. Per Plane il
workspace conta come istanza.

## Requisiti per sistema

- **Vikunja** dalla versione 1.0. Il token API richiede i diritti di lettura su
  progetti e attività; con il diritto «other → user» la verifica mostra anche il
  nome dell’account.
- **Kanboard** propone solo progetti attivi; quelli inattivi non compaiono.
- **Plane** dalla versione 0.28. Vengono proposti solo i progetti di cui
  l’account del token è membro. Plane consente 60 richieste al minuto, quindi la
  prima sincronizzazione di progetti grandi richiede alcuni minuti.
- **Taiga**: l’account necessita nel progetto del diritto di vedere le user
  story. Senza di esso Taiga restituisce un elenco vuoto; WorkDiary segnala
  allora l’esecuzione come incompleta invece di considerare eliminate delle
  attività.
- **Leantime** dalla versione 3.8.
- **FreeScout**: vengono acquisite solo le conversazioni pubblicate, né bozze né
  conversazioni eliminate.
- **GLPI**: l’API REST deve essere attivata e l’account necessita dei diritti di
  lettura sui ticket dell’entità. WorkDiary chiude la sessione dopo ogni
  esecuzione.

## Associare le origini e verificare l’anteprima

L’origine è un progetto in ogni sistema, una **casella** in FreeScout e
un’**entità** in GLPI (senza sottoentità). Nella finestra di dialogo caselle ed
entità compaiono nell’elenco dei progetti.

1. **Associare un progetto** apre una finestra di dialogo con le origini visibili
   all’accesso e i progetti WorkDiary. Ogni origine ha esattamente una
   destinazione.
2. L’**Anteprima** mostra gli elementi aperti e cosa ne farebbe la prima
   sincronizzazione. Non salva nulla.
3. Colleghi attività esistenti del progetto di destinazione tramite
   **Collegare l’omonima** o **Collegare un’attività esistente**; i campi
   divergenti finiscono nella inbox per la verifica.
4. **Confermare l’importazione** abilita l’associazione. Solo allora la
   sincronizzazione crea gli elementi aperti come attività; quelli già
   completati mai.

## Cosa viene acquisito

Vengono acquisiti il **titolo**, la **descrizione** come testo e, se presente,
la **scadenza**. Lo stato proviene sempre da un campo fisso del sistema, mai da
nomi di colonne o di stati liberamente assegnabili:

| Sistema | Aperto | In lavorazione | Completato | Scadenza |
| --- | --- | --- | --- | --- |
| Vikunja | non completata | – | completata | data di scadenza |
| Kanboard | attiva | – | chiusa | data di scadenza |
| Plane | gruppo Backlog, Unstarted, Triage | gruppo Started | gruppo Completed, Cancelled | data obiettivo |
| Taiga | aperta | – | chiusa | data di scadenza |
| Leantime | tipo di stato «Nuovo» | tipo di stato «In lavorazione» | tipo di stato «Completato» | data di scadenza |
| FreeScout | attiva | in attesa | chiusa, spam | – |
| GLPI | nuovo, approvazione | assegnato, pianificato, in attesa | risolto, chiuso | tempo di risoluzione |

- Se un’attività è **In lavorazione** in WorkDiary, resta così finché l’origine
  non la chiude.
- In FreeScout la descrizione è il primo messaggio della conversazione; le note
  interne restano escluse. In Taiga WorkDiary legge la descrizione solo per user
  story nuove o modificate.
- Se Taiga chiude una user story perché tutte le sue attività sono completate,
  questo arriva con la sincronizzazione successiva. Se una nuova attività la
  riapre, WorkDiary lo vede solo nella sincronizzazione giornaliera di tutti gli
  elementi.
- Le milestone di Leantime non vengono acquisite.
- Nella finestra dell’attività, **Apri in …** porta all’originale.

## Quando avviene la sincronizzazione

Ogni 15 minuti WorkDiary recupera gli elementi modificati dall’ultima
esecuzione, e una volta al giorno tutti. Modifichi la cadenza in **Job
pianificati**; **Sincronizzare ora** nel menu ⋯ avvia subito. Un’esecuzione
interrotta risulta **incompleta**; la successiva riprende dall’ultimo stato
completo.

## Conflitti e casi da verificare

Modifiche diverse su entrambi i lati generano un conflitto nella inbox; nulla
viene riscritto nel sistema di origine. Se un elemento collegato non è più
visibile, la Sua attività resta invariata e la inbox segnala un caso da
verificare. Se, secondo la **Titolarità dei dati**, un altro sistema gestisce le
attività, WorkDiary non acquisisce nulla. **Casi da verificare** nel menu ⋯
apre la inbox filtrata sul plugin.

## Mettere in pausa, rimuovere, scollegare

**Mettere in pausa** e **Riprendere** fermano e riavviano la sincronizzazione di
un’associazione; **Rimuovere l’associazione** conserva attività e tempi
importati. **Scollegare** termina la sincronizzazione e mette in pausa tutte le
associazioni. Revochi l’accesso nel sistema di origine o lo elimini in
**Credenziali**; una nuova verifica con la stessa istanza riprende le
associazioni.

## Errori tipici

- La verifica segnala un indirizzo interno: l’indirizzo punta alla propria rete,
  ma **Consentire indirizzi privati** è disattivato o non consentito dal
  gestore.
- La verifica fallisce con 401: token, chiave o password sono errati, scaduti o
  senza accesso in lettura.
- La verifica segnala «…VersionUnsupportedException»: l’istanza è più vecchia
  della versione minima indicata sopra.
- Plane non mostra un progetto: l’account del token non ne è membro, anche se il
  progetto è pubblico.
- Kanboard mostra solo una parte dei progetti: con un nome utente l’accesso vede
  solo i progetti di quell’utente; per tutti i progetti usi il token
  applicativo senza nome utente.
- Taiga rifiuta l’accesso: gli account che accedono tramite GitHub o GitLab
  necessitano di una password propria.
- Leantime o GLPI non mostrano alcuna origine: la chiave API non è assegnata a
  nessun progetto, oppure l’API REST non è attiva in GLPI.
- FreeScout risponde con 404: il modulo «API & Webhooks» non è attivo.
