---
title: "Conflitti Lexoffice"
topic: admin.lexoffice
version: 4
keywords:
    - Lexware Office
    - conflitto di sincronizzazione
    - dati divergenti
    - risolvere conflitto
    - mantenere valori locali
    - accettare valori esterni
    - allineamento dati
    - conflitto sync
audience:
    - admin
    - buchhaltung
related:
    - admin.plugins
    - articles.lexoffice
    - invoices.manage
    - admin.integration-inbox
    - inventory.conflicts
---

Qui risolve i conflitti di sincronizzazione con Lexoffice. Un conflitto
si verifica quando un record locale (WorkDiary) e il contatto Lexoffice
corrispondente divergono in uno o più campi e nelle impostazioni di
Lexoffice la **Strategia di conflitto** è impostata su **Verifica
manuale** (valore predefinito). I conflitti si gestiscono nella **Inbox
di riconciliazione**: aprendo questa pagina vi accede già filtrato
sull'origine **Lexoffice** e sul caso **Conflitto di campo**.

Nella Inbox di riconciliazione:

- Per ogni conflitto i campi divergenti sono affiancati come
  **Locale** e **Remoto**.
- Sono interessati clienti e fornitori, cioè i contatti provenienti da
  Lexoffice.
- Con il filtro di stato può richiamare anche i conflitti già evasi.

Soluzioni per ciascun conflitto:

- **Adotta il remoto**: aggiorna il record locale con i valori di
  Lexoffice dei campi divergenti.
- **Mantieni locale**: conserva i valori locali; i valori divergenti di
  Lexoffice non vengono acquisiti.
- **Scarta**: chiude il conflitto senza modifiche (ad es. in caso di
  dati volutamente diversi); riceve lo stato **Scartato**.

Rischi: **Adotta il remoto** sovrascrive valori locali. Verifichi con
attenzione i dati messi a confronto prima di decidere. Tenga presente
che per le fatture la titolarità della fatturazione spetta al programma
esterno – WorkDiary vi trasmette i dati.

La strategia di conflitto vale per contatti e articoli. I conflitti
relativi agli articoli non compaiono nella Inbox di riconciliazione, ma
nella scheda **Conflitti** del magazzino (**Magazzino** → **Conflitti**):
lì sceglie **Mantieni locale**, **Applica lo stato di Lexoffice** oppure
**Ignora** — con il diritto **Gestisci articoli**.

Autorizzazione: la Inbox di riconciliazione è accessibile agli
amministratori e al ruolo **Contabilità**.
