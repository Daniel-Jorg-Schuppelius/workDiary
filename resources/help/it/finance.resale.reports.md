---
title: "Report e prezzi"
topic: finance.resale.reports
version: 1
keywords:
    - report margine
    - margine
    - rinnovi
    - abbonamenti senza fattura
    - verifica prezzi
    - prezzo consigliato
    - esportare proposta di fattura
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.periods
---

I **report** del registro calcolano sui periodi scaduti: report margine,
proposta di fattura, rinnovi e abbonamenti senza fattura — ciascuno come CSV,
XLSX o PDF; la pagina «Prezzi» verifica i prezzi di vendita rispetto ad
acquisto, catalogo e prezzo consigliato.

**Report margine:** per prodotto e per destinatario fattura compaiono i
periodi scaduti dell’intervallo con vendita prevista (quantità × prezzo di
vendita), fatturato (importi netti dei collegamenti fattura, proposte
incluse), acquisto previsto (prezzo fornitore × quantità) e acquisto
effettivo dai documenti di acquisto. Margine = fatturato − acquisto;
l’acquisto effettivo conta non appena ogni periodo della riga ne ha uno,
altrimenti l’acquisto previsto. Gli importi non vengono mai sommati tra
valute — con più valute c’è una riga per valuta e un avviso. Esportazione
in CSV, XLSX o PDF; la proposta di fattura (periodi aperti con mesi di
licenza aperti e importo) in CSV o XLSX.

**Rinnovi e abbonamenti senza fattura:** il report «Rinnovi» mostra quali
abbonamenti si rinnovano o terminano nel periodo (riquadri 30, 60, 90
giorni): il rinnovo è l’inizio del prossimo periodo pianificato, per gli
abbonamenti disdetti conta la fine. «Senza fattura» elenca gli abbonamenti
il cui periodo scaduto aperto più vecchio risale a più di N giorni fa
(predefinito 60), con periodi aperti e importo aperto. Entrambi in CSV o
XLSX.

**Verifica prezzi:** per prodotto l’acquisto secondo contratto, il prezzo di
listino e il prezzo consigliato dell’ultimo listino importato a confronto
con i prezzi di vendita degli abbonamenti (minimo, mediana, massimo).
Avvisi: «vendita sotto acquisto», «vendita sotto prezzo consigliato»,
«contratto più caro del listino», «nessun prezzo di vendita».
