---
title: "Trasporto passeggeri (taxi/NCC)"
topic: passenger.overview
version: 1
audience: []
modules:
    - module.fuhrpark
related:
    - claims.overview
---

Il profilo settoriale taxi/NCC gestisce ogni trasporto passeggeri in un
fascicolo corsa dedicato: accettazione con modalità operativa congelata,
dispacciamento con controlli obbligatori (patente per trasporto persone,
licenza, attestati del veicolo), partenza con tariffa o prezzo fisso
congelato e chiusura con valore del tassametro, decisione fiscale e metodo
di pagamento.

**Corse:** Le nuove corse si creano con «Nuova corsa». NCC e trasporto a
chiamata aggregato richiedono una ricezione dell'ordine documentata presso
la sede; solo il taxi ammette destinazioni aperte. Il dispacciamento
verifica autista, profilo veicolo e licenza — gli ostacoli appaiono come
errori di validazione.

**Anagrafiche:** Le tariffe sono versionate (periodo di validità, prezzo
base, prezzo al km e al minuto, supplementi, corridoio del prezzo fisso).
Licenze e profili veicolo con scadenze di taratura, BOKraft e revisione
stanno accanto; attestati scaduti bloccano il dispacciamento.

**Chiusura turno:** Incasso del tassametro e modalità di pagamento
(contanti, carta, buono, fattura, intermediario) restano separati; le mance
non contano contro il totale del tassametro. Le differenze restano aperte
finché non vengono chiarite con motivazione.

WorkDiary non sostituisce né tassametro/contachilometri né la TSE — questi
sistemi restano autoritativi; i loro valori vengono documentati e
riconciliati.
