---
title: "Gestione licenze"
topic: admin.license
version: 3
keywords:
    - chiave di licenza
    - piano
    - abbonamento
    - cambiare piano
    - upgrade
    - moduli aggiuntivi
    - limite utenti
    - periodo di prova
    - licenza scaduta
    - account bloccato
    - feature flag
    - dati di fatturazione
audience:
    - admin
    - geschaeftsfuehrung
related:
    - admin.handbook
    - admin.tenants
---

La pagina delle licenze mostra che cosa è consentito alla Sua
installazione: **Piano** (free/pro/enterprise), **limiti di utenti e
organizzazioni**, **Moduli** abilitati e **data di scadenza**.

Ecco come si collega il tutto:

- La **licenza è la fonte** per il piano e i moduli aggiuntivi
  (add-on); l'associazione piano → moduli si trova nella
  configurazione. I nuovi moduli di un piano sono quindi disponibili
  senza dover emettere nuovamente la licenza.
- Le **licenze legate all'organizzazione** si possono installare e
  rimuovere per ciascuna organizzazione; se manca una licenza
  dell'organizzazione, subentra come ripiego la licenza globale.
- **Senza una licenza valida** l'installazione funziona rigidamente nel
  piano Free.

Azioni tipiche:

1. Verificare lo stato della licenza e i moduli.
2. Sovrascrivere in modo mirato i **Flag di funzionalità** (interruttori
   di override).
3. **Installare/rimuovere** la licenza dell'organizzazione oppure – se
   la Sua installazione è autorizzata – **emettere** nuove licenze
   (licenziatario, e-mail, piano, add-on, scadenza, limiti,
   organizzazione, dominio).

Stato del tenant (SaaS):

- Lo **Stato del tenant** indica se l'organizzazione si trova nel
  **Periodo di prova**, è **Attivo** oppure **Sospeso**. Se non è
  impostato alcuno stato fisso, questo viene derivato dal periodo di
  prova e dalla scadenza della licenza (valida / in periodo di tolleranza
  / scaduta).
- Un amministratore della piattaforma può impostare manualmente lo
  stato su **Attivo**, **Periodo di prova** o **Sospeso**, oppure
  riportarlo alla gestione automatica con *Automatico (deriva)*.
- Con lo stato **Sospeso** (o con una licenza definitivamente scaduta)
  le **azioni di scrittura sono disattivate**; la lettura resta
  possibile. Le pagine della licenza e del logout restano raggiungibili,
  in modo che il blocco possa essere rimosso.
- Il **limite utenti** della licenza viene applicato alla creazione di
  nuovi membri: se il limite è raggiunto, la creazione viene bloccata
  con un avviso.

Da sapere:

- I downgrade del piano bloccano i moduli tramite il controllo di
  accesso per piano (plan gating); i contenuti dei moduli soggetti a
  obbligo di conservazione vengono mantenuti.
- Non viene caricato alcun file – le licenze vengono inserite come
  chiavi firmate.

## Dati di fatturazione e cambio di piano

In «Dati di fatturazione» gestisce destinatario della fattura, e-mail,
indirizzo, partita IVA e riferimento d'ordine per la fatturazione da parte
del gestore. Lì richiede anche un altro piano o moduli aggiuntivi; per ogni
organizzazione esiste una richiesta aperta, che può ritirare. Il gestore la
evade emettendo una nuova licenza.
