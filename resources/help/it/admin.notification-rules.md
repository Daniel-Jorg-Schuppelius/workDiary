---
title: "Regole di notifica"
topic: admin.notification-rules
version: 3
keywords:
    - escalation
    - configurare notifiche
    - notifica email
    - notifica push
    - destinatari
    - promemoria
    - avvisi scadenza
    - ritardi
    - canali di notifica
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
related:
    - admin.handbook
    - communication.notes
    - glossary.core
---

Le regole di notifica stabiliscono per ogni tipo di evento **chi**
viene informato e su **quali canali** – e quando scatta l'escalation.
L'elenco mostra per ogni **Evento** le colonne **Attivo**, **Canali**,
**Destinatari** ed **Escalation**; gli eventi senza una regola propria
riportano l'indicazione **Predefinito (non ancora personalizzato)**.

Procedura tipica:

1. Scegliere **Modifica** sull'evento (ad es. punto aperto
   assegnato/in scadenza/scaduto, azione di follow-up in scadenza,
   documento in scadenza, richiesta di correzione, approvazione mensile
   inviata, certificato ISMS in scadenza, azione correttiva scaduta,
   revisione dei rischi in scadenza). Si apre **Modifica regola di
   notifica**.
2. In **Attivo** attivare **Notifiche attive per questo evento** e
   scegliere i **Canali**: **In-app**, **E-mail**, **Push**, **Microsoft
   Teams**, **Mattermost** o **Calendario**. Per gli eventi critici (ad
   es. **Allarme di crisi**, **Servizio di emergenza assegnato**,
   **Evento di sicurezza critico**) è disponibile anche **SMS**.
3. Definire i **Destinatari**: **Notifica la persona interessata (es.
   assegnataria o richiedente)**, **Ruoli destinatari** (ad es.
   responsabile di team) e **Destinatari fissi aggiuntivi**.
4. Per gli eventi di scadenza superata, facoltativamente
   l'**Escalation**: attivare **Escalation attiva**; dopo **Escalation
   dopo (ore)** (1–720) viene notificato in aggiunta il **Ruolo di
   escalation**. **Livello di escalation 2** e **Livello di escalation
   3** notificano ciascuno, dopo ulteriori ore, i propri ruoli e
   destinatari fissi.

Da sapere:

- Senza una regola propria vale l'impostazione predefinita mostrata
  per l'evento (canali, flag della persona interessata, ruoli) – basta
  configurare i casi che se ne discostano.
- **Microsoft Teams** e **Mattermost** inviano al canale di chat
  configurato dell'organizzazione; **Calendario** inserisce gli eventi
  con data nei calendari collegati dell'organizzazione (CalDAV/Microsoft
  365/Google). Gli **SMS** raggiungono solo le persone con numero di
  cellulare confermato e hanno un costo per messaggio.
- L'escalation esiste solo per eventi scaduti o in scadenza.
- Alcuni eventi vengono attivati subito (ad es. l'assegnazione), altri
  vengono individuati dallo scanner delle scadenze (ad es. «in
  scadenza»).

Autorizzazione: l'elenco è visibile alle persone con il diritto
**Vedere le regole di notifica**; possono modificarlo solo le persone
con **Modificare le regole di notifica**.
