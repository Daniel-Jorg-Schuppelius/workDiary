---
title: "Autofatturazione"
topic: invoices.self-billing
version: 1
keywords:
    - autofatturazione
    - fattura emessa dal committente
    - nota di credito al fornitore
    - liquidazione subappaltatori
    - codice tipo 389
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.vertrieb
related:
    - invoices.manage
    - finance.incoming-invoices
---
Con l’**autofatturazione** la sua organizzazione, in qualità di destinataria della prestazione, emette essa stessa la fattura per la prestazione del fornitore (art. 14, comma 2, frase 2 della legge tedesca sull’IVA), ad esempio per subappaltatori, provvigioni o acquisti. Il documento si chiama **Gutschrift** ed è distinto dalla nota di credito commerciale su una propria fattura.

## Requisiti

- Sul fornitore è registrata la data dell’**accordo di autofatturazione** (modifica fornitore). Senza accordo il fornitore non viene proposto.
- Il fornitore ha una **partita IVA** o un **codice fiscale** e un indirizzo completo.
- Il documento ha almeno una riga e un **periodo di prestazione**.

Se manca qualcosa, la pagina di dettaglio elenca i punti aperti; **Emetti** compare solo quando tutto è completo.

## Procedura

1. **Crea autofattura**: scegliere fornitore e periodo di prestazione.
2. **Aggiungi riga**: descrizione, quantità, unità, prezzo unitario e aliquota IVA. Il calcolo è lo stesso di fatture e offerte.
3. **Emetti**: assegna il numero dalla propria numerazione (predefinito `GS-anno-numero`, modificabile nelle numerazioni) e congela importi e contenuto.

Con l’emissione nasce la fattura elettronica (XRechnung, codice tipo 389), che arriva nelle **fatture in entrata** già assegnata al fornitore. Da lì passa alla contabilità come ogni fattura in entrata: a DATEV o al programma collegato. Il PDF riporta la dicitura «Gutschrift», indica il fornitore come prestatore e richiama l’accordo.

## Correzione e storno

Un’autofattura emessa non è modificabile. **Stornare** è possibile solo finché la contabilità non l’ha acquisita; la relativa entrata viene quindi rifiutata. Successivamente si corregge con una nuova autofattura con importi negativi.

Permessi: visualizzare con «Elenca le fatture», creare con «Crea una fattura», emettere e stornare con «Emetti una fattura».
