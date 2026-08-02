---
title: "Comportamento di pagamento"
topic: reports.payment-behavior
version: 2
audience: []
related:
    - reports.economics
    - reports.customer-value
---

Vista comportamentale e di tendenza sulle **fatture gestite localmente** —
il report di fatturazione mostra lo stato (status, anzianità), questo
report il comportamento sottostante. La data di riferimento è sempre la
**fine periodo** (report riproducibili).

## DSO con esempio numerico

**DSO** (days sales outstanding) = crediti aperti a fine mese ÷ ricavi
degli ultimi 90 giorni × 90. Esempio: 12.000 € aperti con 48.000 € di
ricavi in 90 giorni → 12.000 ÷ 48.000 × 90 = **22,5 giorni** di
immobilizzo medio del capitale. Una curva in salita significa che
l'attività vincola sempre più liquidità.

## Tempi di pagamento vs ritardo

- **Tempi di pagamento** = giorni dall'emissione al pagamento
  (indipendente dalla scadenza) — come trend mensile e distribuzione per
  cliente.
- **Ritardo** = giorni **oltre la scadenza**; chi paga in anticipo conta
  0. La top list mostra i clienti con il ritardo medio più alto.

Leggere il box plot: linea = mediana, box = metà centrale, baffi =
intervallo. Un cliente con mediana 40 giorni su scadenza a 14 paga tardi
sistematicamente — è una questione di condizioni, non un caso isolato.

## Cosa farne

- **DSO in aumento** → rivedere i solleciti, accorciare le scadenze,
  valutare lo sconto per pagamento anticipato.
- **Clienti con ritardo medio alto** → rinegoziare i termini, acconto/
  pagamento anticipato sui nuovi ordini, limite di credito interno.
- **Fatture aperte scadute** (tabella in basso) → saltare direttamente
  alla fattura o alle fatture aperte del cliente.

Un clic su un cliente nel box plot o nella top dei ritardi filtra questo
report su di lui; se Lexoffice gestisce le fatture, entrano tramite lo
specchio documenti del plugin — il sync carica anche i dati di pagamento
(endpoint payments). Senza alcuna fonte il report lo dichiara
apertamente.
