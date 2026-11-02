---
title: "Periodi di fatturazione"
topic: finance.resale.periods
version: 1
keywords:
    - periodi
    - calcolare proposte
    - bozza di fattura
    - azioni di massa
    - rinuncia
    - contestato
    - fatturazione retroattiva
    - pagamento anticipato
    - data limite
    - esecuzione ricorrente
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.plan
    - finance.resale.board
---

La **pagina dei periodi** elenca i periodi di fatturazione scaduti di tutti
gli abbonamenti: qui si decide per periodo (confermare, assegnare, rinunciare,
contestare), si avvia il calcolo delle proposte, si creano bozze di fattura e
si apre la proposta di modelli. I riquadri di stato e il filtro «Solo
problemi» restringono l'elenco.

**Periodi:** la pagina dei periodi mostra i periodi scaduti di tutti gli
abbonamenti con riquadri di stato (aperto, fatturato, parziale, rinunciato,
contestato). «Calcola proposte» confronta i periodi aperti con le righe di
licenza delle fatture rispecchiate e crea proposte; Lei le conferma, collega a
mano una riga (solo fatture dello stesso destinatario, solo mesi di licenza
liberi) oppure rinuncia con un motivo («cortesia»).
Confermare richiede una proposta aperta; rinunciare o contestare un periodo
scaduto non ancora deciso: azione singola e azione di massa verificano lo
stesso.
Nella finestra di collegamento compaiono prima le fatture nella finestra del
periodo, sotto tutte le altre del destinatario; il campo di ricerca trova
ciascuna dal suo numero, e una già usata indica dove è collegata. Anche gli
elenchi di abbonamenti e periodi cercano per numero di fattura. I periodi decisi non
vengono più toccati dalla pianificazione; «riapri» li riapre. Se una fattura
collegata viene poi stornata in Lexoffice, l’esecuzione successiva azzera i
mesi del collegamento, annota lo storno e riapre il periodo, così la fattura
sostitutiva può essere collegata. Gli abbonamenti di dominio sono coperti
dalle righe che nominano il dominio (ad esempio «dominio .de incl. gestione
(lastkraft-logistik.de)»), indipendentemente dalla classificazione dell’articolo.
I domini che un subrivenditore regola tramite il portale del fornitore sono
coperti dal giornale contabile del modulo domini: un documento per sottoutente
e mese, numerato «numero cliente-MM-AAAA» (inserisca il numero cliente nella
pagina del sottoutente); il destinatario è il cliente del sottoutente.

**Proposte sicure e incerte:** L’esecuzione delle proposte (ogni ora e dopo
ogni importazione) conferma da sola le corrispondenze sicure — un documento
la cui data fattura è al massimo 14 giorni prima o 45 giorni dopo l’inizio
del periodo e che copre il periodo per intero con il numero di licenze
corretto; la conferma compare nel registro come «confermata
automaticamente». Tutto il resto resta proposta e porta «incerta» con il
motivo nel suggerimento. Le proposte valgono senza conferma:
la pagina dei periodi mostra per impostazione predefinita «Solo problemi»
(aperto, parziale, contestato); le proposte non confermate si consultano
come campione tramite il filtro e lo stato della panoramica. Un clic sul numero del
documento apre il documento: righe, periodo di prestazione, testo fattura,
consumo per riga e il confronto con il periodo (data nella finestra,
prestazione, articolo); così vedete prima di confermare se la fattura
appartiene al periodo.

**Azioni di massa:** Nella pagina dei periodi selezionate le righe con le
caselle e confermate tutte le proposte in una volta, rinunciate con un
motivo per tutte («Rinuncia / contesta …») o fate creare una bozza per
destinatario fattura della selezione. I periodi decisi e futuri vengono
saltati e contati; ogni periodo riceve la stessa voce di registro
dell’azione singola. Non c’è preselezione.

**Bozza di fattura:** da tutti i periodi aperti di un destinatario fattura
nasce con un clic una bozza — con fatturazione Lexoffice come bozza in
Lexoffice (nulla viene finalizzato; verifica ed emette lì), con
fatturazione locale come bozza di fattura locale con righe e collegamenti
proposti. Una riga per abbonamento e periodo, cliente finale nella
descrizione, quantità in mesi per gli articoli mensili. I periodi ricordano
la bozza: un secondo clic non ne crea una seconda ma indica quella in
sospeso con numero e data; solo quando la bozza è diventata fattura o il
periodo è deciso tornano liberi; fino ad allora mostrano «In bozza». La finestra elenca solo destinatari con
periodi aperti e prezzo di vendita e indica sotto ciò che è già in una
bozza. La creazione richiede il diritto *Creare bozze di fattura dai
periodi*.
Anche due clic simultanei (doppio clic, seconda finestra del browser) creano
una sola bozza: il secondo segnala che una bozza è già in creazione.

**Fatturazione retroattiva e pianificazione anticipata:** la proposta di
fattura raggruppa i periodi aperti consecutivi di un abbonamento in un'unica
riga — sempre per gli articoli mensili, per gli abbonamenti annuali con una
licenza («3 anno» per tre anni aperti); i periodi parzialmente coperti e le
lacune aprono una nuova riga. Così gli anni passati si fatturano in un'unica
bozza. Per i pagamenti anticipati la finestra ha una data limite (solo
scaduti, +1, +2, +3 anni): la pianificazione crea i periodi fino a tale data,
e l'esecuzione giornaliera conserva i periodi con timbro di bozza. Con
sovranità di fatturazione esterna, l'esecuzione ricorrente può creare ogni
mese, il primo giorno, un trasferimento per destinatario al suo programma di
contabilità (interruttore e anticipo nella finestra «Fatturazione»); gli
abbonamenti con un modello ricorrente attivo restano esclusi.
