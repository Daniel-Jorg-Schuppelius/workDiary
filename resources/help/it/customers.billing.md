---
title: "Condizioni speciali & conto cliente"
topic: customers.billing
version: 2
audience: []
related:
    - contacts.manage
    - invoices.manage
    - customer-portal.billing
---

Nella scheda cliente si possono definire **condizioni speciali**:
tariffe orarie proprie per attività e tipo di giorno (feriale/weekend,
definito tramite «giorni lavorativi a settimana») e la modalità di
conteggio — **conto cliente** senza fatture con saldo corrente,
**fattura mensile** oppure **forfait (Lexoffice)**.

In modalità conto ogni mese ha un blocco di conteggio: totale (ore ×
tariffa), saldato (pagamenti), mese precedente (riporto) e aperto
(saldo). Il saldo passa automaticamente al mese successivo. I mesi si
**chiudono** in ordine cronologico (blocco + snapshot, i tempi risultano
saldati) e si possono riaprire in ordine inverso.

I pagamenti si registrano manualmente nel pannello o tramite la
riconciliazione bancaria (il conto cliente è una destinazione di
abbinamento). Le registrazioni tardive in mesi chiusi vengono segnalate —
riaprire il mese o cambiare la data.

In **modalità forfait** Lexoffice gestisce documento e pagamento. Il
forfait mensile si indica al netto («acconto mensile previsto»); il
saldo locale contrappone ore × tariffa al forfait pagato. Per il
documento ci sono due strade:

- **Invia forfait** crea la fattura in Lexoffice (anche mensilmente e in
  automatico per il mese precedente).
- **Collega documento** aggancia al mese una fattura già creata in
  Lexoffice. Se esattamente una fattura del cliente coincide per mese e
  importo netto, ciò avviene automaticamente durante la sincronizzazione
  dei documenti.

Quando un documento è agganciato, «Invia forfait» scompare — altrimenti
in Lexoffice nascerebbe un secondo documento. Lo stato di pagamento
rientra con la sincronizzazione e viene registrato **al netto**
(Lexoffice lavora al lordo).

Se le condizioni speciali sono state create solo in seguito, i tempi più
vecchi compaiono dapprima con 0,00 € sotto «totale». **Ricalcola** li
valorizza con le tariffe impostate; le tariffe forzate manualmente
restano invariate.

Il cliente vede presenze e saldo nel portale clienti sotto
«Fatturazione» e può scaricare il registro presenze in PDF.
