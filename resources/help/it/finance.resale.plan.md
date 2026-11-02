---
title: "Modelli ricorrenti (piano)"
topic: finance.resale.plan
version: 1
keywords:
    - modelli ricorrenti
    - fattura ricorrente
    - proposta di modelli
    - verifica del piano
    - stato di esecuzione
    - abbonamento senza modello
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.accounts
    - finance.resale.periods
---

I **modelli ricorrenti** sono le fatture ricorrenti del programma di
contabilità; il registro li rispecchia, assegna le loro righe agli
abbonamenti e verifica lo stock rispetto al piano. La pagina «Proposta di
modelli» indica quali modelli restano da creare.

**Modelli ricorrenti (piano):** Se un programma di contabilità emette le
fatture, le fatture ricorrenti nascono lì come modelli ricorrenti. Il registro
li specchia (Lexoffice: ogni giorno e dopo la sincronizzazione dei documenti,
in sola lettura) e mostra nel conto licenze per modello stato, ritmo, prossima
esecuzione e le righe di licenza con gli abbonamenti che fatturano.
L'assegnazione abbonamento ↔ riga del modello è proposta dall'elaborazione —
prima tramite la fattura che il modello ha creato per ultima ed è assegnata a
un periodo, altrimenti tramite stesso destinatario, articolo e quantità (con
più clienti finali di un partner solo se nominati): confermate, assegnate a mano o rimuovete. I periodi fatturati
da un modello attivo restano fuori dalla proposta di fattura; ciò che termina
prima della sua prima esecuzione vi rimane. Così gli anni passati si possono
fatturare anche se il modello esiste già. I
**controlli dotazione ↔ piano** segnalano prima della fattura: abbonamento senza
modello, modello senza abbonamento («Crea abbonamento dal modello» riprende
articolo, quantità, ritmo e prezzo), quantità diversa, modello che continua
oltre la fine dell'abbonamento, modello in pausa o terminato con abbonamento
attivo, prossima esecuzione a più di sette giorni dall'inizio periodo, ultima
esecuzione fallita: nel riquadro, nel riepilogo settimanale e nella coda di
lavoro. Ogni fattura specchiata porta il riferimento al suo modello.

**Proposta di modelli e stato di esecuzione:** la pagina «Proposta di
modelli» (dall'elenco periodi) elenca, per destinatario con fatturazione
esterna, gli abbonamenti senza modello assegnato con articolo, testo riga,
quantità, unità, prezzo unitario, intervallo e prima esecuzione; i
destinatari coperti dall'esecuzione ricorrente del registro sono
contrassegnati. Il modello si crea nel programma di contabilità, il prossimo
rispecchiamento propone l'assegnazione. Per modello, il conto licenze mostra
cosa è stato dell'ultima esecuzione — fattura pagata, aperta (verificare
l'invio nel programma) o mancante — e, in caso di errore, il messaggio del
programma come tooltip; l'API Lexoffice non sa se una fattura è partita per
e-mail. Una riga di modello accoglie al massimo tante licenze quante ne
fattura; gli abbonamenti di dominio vengono assegnati solo se il dominio è
citato esattamente.
