---
title: "Firefly III come fonte di riconciliazione"
topic: admin.firefly
version: 1
keywords:
    - Firefly III
    - movimenti di conto
    - incasso
    - riconciliazione
    - causale
    - token di accesso personale
audience:
    - admin
related:
    - admin.plugins
    - admin.integrations
    - admin.scheduler
---

La pagina **Firefly III** legge le entrate e le uscite della Sua installazione
Firefly III e mostra a quale fattura o fattura in entrata appartiene un movimento.
Trova la pagina nel menu di sistema (l’ingranaggio **Sistema**) sotto **Plugin** →
**Firefly III**, non appena il plugin è attivo. WorkDiary non scrive nulla in
Firefly III e non registra pagamenti dai movimenti.

## Configurare l’accesso

1. In Firefly III crei un **token di accesso personale** in **Opzioni** →
   **Profilo** → **OAuth**.
2. Sulla pagina apra **Credenziali** nel menu ⋯ e inserisca l’**indirizzo** (senza
   /api) e il token. Se Firefly III funziona nella propria rete, attivi anche
   **Consentire indirizzi privati**; ha effetto solo se il gestore lo consente.
3. Clicchi su **Verificare la connessione**. WorkDiary mostra istanza, versione e
   account.

## Cosa viene riconciliato

Ogni esecuzione legge i movimenti del **periodo** (60 giorni fino a oggi di
default, modificabile in **Credenziali**). I giroconti tra conti propri restano
esclusi.

- **Entrata:** se causale, note, riferimento o identificativo SEPA end-to-end contengono esattamente un numero di
  fattura della Sua organizzazione, la riga mostra quella fattura.
- **Uscita:** lo stesso con il numero di una fattura in entrata.
- Se una transazione ha un importo in una seconda valuta, compare sotto
  l’importo.
- Una transazione suddivisa (split) compare come una riga per ogni parte.
- Se corrispondono più documenti, il movimento è **Ambiguo**; senza corrispondenza
  è **Senza assegnazione**. Importo e data da soli non assegnano mai.

Ciò che manca in Firefly III in un periodo letto per intero scompare dall’elenco.
Un’esecuzione interrotta risulta **incompleta** e non rimuove nulla.

## Quando avviene la sincronizzazione

Ogni ora; modifichi la cadenza in **Job pianificati**, **Sincronizzare ora** avvia
subito. **Scollegare** ferma la sincronizzazione.

## Errori frequenti

- La verifica fallisce con 401: il token è errato o scaduto.
- La verifica segnala un indirizzo interno: l’indirizzo punta alla propria rete,
  ma **Consentire indirizzi privati** è disattivato o non consentito dal gestore.
