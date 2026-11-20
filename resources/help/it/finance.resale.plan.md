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

**Il modello come ancora dell'assegnazione:** quando una riga di modello è
assegnata a un abbonamento (confermata, impostata a mano o ricavata da un
collegamento di fattura), le fatture di quella riga vanno all'anno di licenza
di questo abbonamento o di uno dei suoi predecessori che contiene la data
della fattura, anche se un altro abbonamento dello stesso prodotto inizia più
vicino a quella data. Questi collegamenti sono considerati sicuri e riportano
il modello come nota. La riga non viene proposta a un abbonamento fuori da
questa catena; se non trova posto, compare tra le «righe senza abbonamento».
Se l'assegnazione non è corretta, modifichi l'assegnazione della riga di
modello e non il collegamento ogni anno. L'ancora non vale quando l'articolo
della fattura è diverso da quello della riga di modello o la fattura è
precedente all'abbonamento. Per ogni assegnazione il blocco dei modelli mostra
l'**esecuzione di fatturazione**: l'anno di licenza dell'ultima esecuzione e
se è collegato al documento di quell'esecuzione. «Documento non assegnato»
porta direttamente all'assegnazione.

**Il modello termina, l’abbonamento continua:** se un modello ha una data di
fine nei prossimi 90 giorni e l’abbonamento assegnato prosegue oltre, il
registro lo segnala in anticipo. Crei il modello successivo, altrimenti il
prossimo anno di licenza resta senza fattura. **Durata parziale:** una
quantità di mesi che non è un multiplo del ritmo («7 mesi» su un modello
annuale) conta come una licenza per quei mesi.

**Prima della prossima esecuzione:** i rilievi sui modelli mostrano quando il
modello verrà eseguito la prossima volta e quale importo fatturerà allora la
riga. Se un modello con rilievo viene eseguito nei prossimi 30 giorni, compare
nel riepilogo sotto «Verificare i modelli prima della prossima esecuzione».
**Prodotto terminato:** se un modello fattura un articolo il cui abbonamento è
terminato presso il destinatario, il rilievo indica la fine e, in caso di
cambio prodotto, l’abbonamento attivo da allora. **Prezzo diverso:** il prezzo
della riga per licenza viene confrontato con il prezzo di vendita
dell’abbonamento (tolleranza 5 %, almeno 1 €).
