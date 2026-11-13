---
title: "Trasferimento fatturazione"
topic: finance.transfers
version: 7
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
    - documenti collegati
    - catena dei documenti
    - fattura presso il fornitore
    - inviare giustificativi
    - fascicolo di invio
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

**Forfait:** se la regola di fatturazione del progetto o la prestazione
predefinita dell'organizzazione indicano l'unità «forfait» o «Pauschale», ogni
ordine genera una riga con quantità 1 al prezzo della prestazione; i tempi
dell'ordine restano le fonti della riga. Tutte le altre unità, ad esempio ora,
giorno o pz., contano in base alla durata. L'arrotondamento e le ore concordate
non valgono per i forfait.

## Documenti collegati

In fondo alla pagina, **Documenti collegati** elenca tutti i documenti legati
a questo: l'offerta e le sue versioni, fatture, acconti e fattura finale, note
di credito e storni, documenti di trasporto, ordini di produzione e
trasmissioni. Se un programma di contabilità ha ripreso un documento, il suo
corrispondente compare lì con il numero del programma. Compaiono anche i
collegamenti segnalati dal programma stesso, per esempio un acconto creato in
Lexoffice. La colonna **Relazione** indica che cosa è il documento rispetto a
questo; «tramite …» nomina il documento attraverso cui esiste il
collegamento.

## Fattura presso il fornitore: giustificativi e invio tramite WorkDiary

Se Lexware Office, sevDesk, easybill o orgaMAX gestiscono la fattura, numero
e contenuto restano lì. WorkDiary può comunque aggiungere le attestazioni
delle prestazioni alla fattura finalizzata e consegnarla, senza una fattura
propria.

1. Finalizzi la bozza nel sistema di fatturazione.
2. Nel riepilogo di trasferimento, faccia clic su **Recupera fattura da …**
   nella scheda **Fattura presso il fornitore**. WorkDiary recupera numero,
   stato e originale e assegna le posizioni del trasferimento. Per fatture
   suddivise, raggruppate o create direttamente nel sistema di fatturazione,
   usi **Assegna un’altra fattura**.
3. Se le posizioni presso il fornitore differiscono, confermi la differenza
   nella pagina della fattura con una motivazione. Senza conferma l’invio è
   bloccato.
4. Generi o carichi i giustificativi nella scheda **Documenti
   giustificativi** e faccia clic su **Consegna**.

WorkDiary integra l’originale nel suo formato: un PDF parte invariato con i
giustificativi come allegati, una XRechnung li contiene incorporati, un PDF
ZUGFeRD viene riscritto con l’XML integrato. Gli originali firmati non
vengono mai modificati. Il **fascicolo di invio** conserva ogni versione con
hash, giustificativi e tentativi di invio. **Riprova** invia gli stessi file;
se un tentativo resta senza risposta, verifichi con il destinatario se è
arrivato. I giustificativi creati in seguito partono con **Invia
giustificativi successivi**.

Serve il canale di invio «WorkDiary invia» nell’anagrafica cliente o nelle
impostazioni dell’organizzazione. Disattivi quindi l’invio automatico nel
sistema di fatturazione, altrimenti il cliente riceve la fattura due volte.
