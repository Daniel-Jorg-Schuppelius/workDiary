---
title: "Plugin"
topic: admin.plugins
version: 2
keywords:
    - estensioni
    - componenti aggiuntivi
    - add-on
    - integrazioni
    - attivare plugin
    - disattivare plugin
    - test connessione
    - controllo stato
    - errori plugin
    - disattivazione automatica
    - log errori
audience:
    - admin
related:
    - admin.handbook
    - admin.toggl
    - admin.openproject
    - admin.lexoffice
    - admin.remote-support
---

Qui gestisce i plugin e le integrazioni installati. I plugin
estendono WorkDiary con collegamenti esterni (ad es. Toggl,
OpenProject, Lexoffice, teleassistenza).

Importante: i plugin vengono gestiti **per organizzazione**.
Attivazione, impostazioni, stato di salute ed errori valgono di volta
in volta per la Sua organizzazione – un plugin può trovarsi in uno
stato del tutto diverso in un'altra organizzazione.

Panoramica (elenco):

- **Stato**: attivo, inattivo o disattivato automaticamente.
- **Salute (health)**: ok / limitato / difettoso, con l'ora dell'ultimo
  controllo.
- **Azioni per plugin**: configurare, attivare/disattivare, eseguire
  subito il controllo di salute, in caso di disattivazione automatica
  azzerare e riattivare.

Configurare (modificare):

- Impostazioni per ciascun plugin (ad es. token API, endpoint).
  Password/token: un campo vuoto lascia invariato il valore esistente.
- **Verifica connessione** avvia un controllo di salute senza salvare.

Controllo di salute e disattivazione automatica:

- Il controllo di salute verifica raggiungibilità e funzionamento e
  aggiorna il risultato per ciascuna organizzazione. Viene eseguito
  manualmente o in modo pianificato (cron).
- Se gli errori si ripetono, al raggiungimento della soglia il plugin
  viene **disattivato automaticamente** – solo per l'organizzazione
  interessata. In questo modo il funzionamento per le altre resta
  invariato.
- Dopo aver eliminato la causa, azzeri il contatore degli errori e
  riattivi il plugin.

Registro degli errori (errori dei plugin):

- Elenco di tutti gli errori registrati con ora, plugin, fase
  (avvio/esecuzione/controllo di salute), classe di eccezione e
  messaggio.
- Filtri per plugin, fase e stato (aperto/confermato).
- Nella vista di dettaglio: messaggio completo, contesto e stack
  trace.
- Gli errori possono essere contrassegnati come **confermato** (con
  l'incaricato e la marca temporale); restano conservati per la
  tracciabilità.

Autorizzazione: queste sezioni sono riservate agli amministratori e
richiedono un contesto di organizzazione.

Rischi: un plugin disattivato interrompe la propria sincronizzazione –
importazioni/esportazioni e controlli di salute restano sospesi finché
non viene riattivato. Dopo ogni modifica della configurazione verifichi
lo stato di salute.
