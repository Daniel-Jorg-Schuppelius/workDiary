---
title: "Metriche"
topic: admin.metrics
version: 3
keywords:
    - indicatori
    - statistiche
    - monitoraggio
    - utilizzo sistema
    - spazio di archiviazione
    - utenti attivi
    - job falliti
    - coda
    - statistiche di utilizzo
    - prestazioni
    - metriche operative
audience:
    - admin
related:
    - admin.diagnostics
    - admin.handbook
    - admin.backups
---

La pagina **Metriche operative** mostra, in sola lettura, indicatori
operativi e di prestazione per il monitoraggio del sistema. Integra la
diagnostica, che fornisce lo stato a semaforo dei controlli di salute
(health check). Tutte le metriche vengono raccolte e salvate
esclusivamente in locale; non avviene alcun invio a sistemi esterni.

La pagina si articola nelle seguenti sezioni:

- **Versione** dell'applicazione (nell'intestazione della pagina)
- **Coda**: **Job in attesa** e **Job falliti**
- **Heartbeat di backup**: backup segnalati più recenti (momento,
  dimensione, origine)
- **Errori dei plugin (7 giorni)**: numero e ultimi incidenti
- **Archiviazione**: numero e dimensione di **Allegati** e **Versioni
  dei documenti** secondo i metadati del database (l'occupazione del
  disco è mostrata dalla diagnostica)
- **Utenti attivi (30 giorni)**: utenti distinti con un accesso secondo
  il registro di audit
- **Record per modulo principale**: consistenze ad es. per **Incarichi
  (diario)**, **Documenti**, **Protocolli** e **Articoli di conoscenza**
- **Utilizzo delle funzionalità (30 giorni)**: **Numero** e **Ultimo
  utilizzo** per funzionalità, aggregati per organizzazione e giorno
- **Trasparenza delle metriche**: quali contatori di utilizzo vengono
  rilevati e se sono attualmente attivi

I valori vengono rilevati ex novo a ogni apertura; se non sono
disponibili, singole sezioni ricadono su valori predefiniti vuoti,
senza bloccare la pagina.

L'accesso richiede il diritto **Visualizzare le metriche operative**. I
controlli di salute dettagliati e l'e-mail di prova si trovano in
**Diagnostica**.
