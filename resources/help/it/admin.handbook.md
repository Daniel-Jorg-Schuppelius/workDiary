---
title: "Manuale amministratore: panoramica"
topic: admin.handbook
version: 2
keywords:
    - guida amministratore
    - amministrazione
    - configurazione iniziale
    - primi passi
    - gestione tenant
    - ruoli e permessi
    - export GDPR
    - amministrazione sistema
    - manuale admin
audience:
    - admin
related:
    - admin.tenants
    - admin.roles
    - admin.backups
    - admin.license
    - admin.import
    - admin.security
    - roles.admin
---

Il manuale amministratore raccoglie tutti i temi amministrativi di
WorkDiary. I capitoli (vedere gli argomenti correlati qui sotto):

- **Organizzazioni/tenant**: creare, disattivare, export e
  cancellazione GDPR, cambio di organizzazione.
- **Ruoli & permessi**: modello di autorizzazioni granulare, ruoli,
  gruppi, assegnazione dei membri – e perché il ruolo admin globale è
  tabù.
- **Backup & operatività**: heartbeat dei backup, verifica dello stato
  del sistema.
- **Licenza**: piano, moduli, limiti, licenze legate
  all'organizzazione.
- **Importazione**: procedura guidata CSV con analisi preliminare
  (preflight) e report degli errori.
- **Sicurezza**: metodi 2FA, cifratura dei dati esistenti, catena di
  audit, SBOM/componenti.

Ordine consigliato per la configurazione iniziale:

1. verificare organizzazione e licenza,
2. configurare ruoli e membri,
3. importare i dati anagrafici,
4. configurare le regole (notifiche, maggiorazioni),
5. attivare sicurezza e monitoraggio dei backup.

Principio: per le correzioni di merito utilizzi sempre il percorso
operativo previsto (richiesta di correzione, storno, nuova versione)
anziché un intervento diretto dell'amministratore – così la traccia di
audit e la tracciabilità restano intatte.
