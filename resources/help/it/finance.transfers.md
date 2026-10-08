---
title: "Trasferimento fatturazione"
topic: finance.transfers
version: 3
keywords:
    - trasferimento a Lexoffice
    - trasferimento DATEV
    - bozza di fattura
    - trasferire prestazioni
    - fatturare materiale
    - fatturare ore
    - export fatturazione
    - software di fatturazione
    - righe di fattura
audience: []
modules:
    - module.finance
related:
    - exports.payroll
    - admin.surcharge-rules
    - roles.buchhaltung
    - glossary.core
---

La consegna fatturazione trasmette **tempi** e **materiali**
fatturabili al sistema di fatturazione principale. La trova nel menu
alla voce **Consegna fatturazione**; la pagina **Ricevute di
trasferimento** elenca tutti i trasferimenti.

Principio di base della titolarità della fatturazione: **la fattura
nasce nel programma esterno principale** (ad es. Lexoffice, orgaMAX,
sevDesk, easybill o DATEV) – WorkDiary fornisce solo posizioni
verificate insieme alla ricevuta di trasferimento. Una fattura locale
in WorkDiary esiste solo se non è in uso alcun software di
fatturazione esterno. Per ogni organizzazione o cliente vale un solo
**Canale di fatturazione**.

Procedura tipica:

1. **Preparare il trasferimento** (stato **Bozza**): scegliere
   **Cliente**, **Canale di trasferimento** – **Prestazioni/tempo**
   oppure **Prodotti/materiale**, separati –, **Destinazione del
   trasferimento** e **Periodo di prestazione**. La destinazione è
   preimpostata in base al canale di fatturazione del cliente:
   **Lexoffice** (bozza di fattura), **orgaMAX (ordine)**, **sevDesk
   (bozza di fattura)** o **easybill (bozza di fattura)**; in aggiunta
   è sempre disponibile l'**Esportazione file**. Se guida DATEV, la
   consegna avviene come pacchetto file (CSV) tramite l'esportazione
   file.
2. Verificare le posizioni e **Confermare il trasferimento** (stato
   **Confermato**). Solo dopo è possibile modificare denominazione e
   testo della prestazione nonché unire o rimuovere posizioni.
3. **Trasferire ora** → stato **Trasferito** (definitivo). In caso di
   **Fallito**, **Riprova** riporta il trasferimento allo stato
   **Confermato**; dopodiché lo trasferisce di nuovo.
4. I trasferimenti in stato **Bozza** o **Confermato** possono essere
   annullati con **Annullare il trasferimento** – le posizioni
   contenute vengono nuovamente liberate.

Rischi e azioni irreversibili:

- **«Trasferito» è definitivo** – le posizioni contenute sono bloccate
  contro le modifiche.
- Le correzioni avvengono tramite operazioni tracciabili, mai tramite
  un ripristino silenzioso: **Stornare il trasferimento** libera di
  nuovo le fonti, **Crea correzione** genera un trasferimento di
  correzione con gli stessi tempi. Una bozza creata presso la
  destinazione non viene eliminata – la rimuova lì manualmente.

Autorizzazioni: i trasferimenti di tempi e di materiale sono protetti
separatamente (**Preparare e trasferire i tempi fatturabili** oppure
**Preparare e trasferire il materiale fatturabile**). L'elenco è
visibile a chi ha **Consultare le ricevute di trasferimento**; stornare
e correggere richiedono inoltre **Gestire la configurazione
finanziaria**.
