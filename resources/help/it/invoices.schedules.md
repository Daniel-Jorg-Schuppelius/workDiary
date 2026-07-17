---
title: "Piani di fatturazione"
topic: invoices.schedules
version: 1
audience:
    - admin
related:
    - invoices.manage
---

I **piani di fatturazione** creano **bozze** di fatture ricorrenti
(MVP-415) — emissione e invio restano sempre passaggi manuali.

- **Ritmo**: settimana/mese/trimestre/anno × quantità; il periodo fatturato è
  quello trascorso o quello corrente (pagamento anticipato).
- **Modello di posizioni**: i segnaposto `{zeitraum_von}` e `{zeitraum_bis}`
  vengono sostituiti a ogni esecuzione; gli sconti vengono ripresi.
- **Contratto**: collegamento facoltativo — se il contratto termina, termina
  anche il piano.
- **Idempotente**: al massimo una bozza per piano e periodo; le esecuzioni
  perse vengono recuperate.
- **Sovranità di fatturazione**: se un sistema esterno gestisce le fatture del
  cliente, il piano resta visibilmente bloccato.
