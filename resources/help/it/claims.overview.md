---
title: "Reclami e garanzia"
topic: claims.overview
version: 4
keywords:
    - reclamo cliente
    - contestazione
    - garanzia legale
    - cortesia commerciale
    - reso
    - RMA
    - gestione resi
    - difetto prodotto
    - denuncia dei vizi
    - rivalsa sul fornitore
    - assistenza post-vendita
audience: []
modules:
    - module.claims
related:
    - documents.manage
---

Il modulo gestisce reclami, casi di garanzia, decisioni di cortesia
commerciale e resi come fascicoli tracciabili — dalla ricezione,
attraverso valutazione e decisione, fino alle conseguenze su
magazzino, assistenza e fatturazione.

**Fascicolo:** ogni reclamo riceve un proprio numero (REK-…),
scadenze, responsabili e collegamenti a commessa, progetto, asset,
articolo, numero di serie, fattura e fornitore. I moduli specialistici
restano l'elemento primario — il fascicolo collega, non sovrascrive
nulla.

**Collegare gli oggetti interessati:** nella finestra «Nuovo reclamo» e
tramite «Modifica» nel fascicolo si scelgono fattura, ordine, progetto,
ticket di assistenza, asset, verbale e fornitore, e con il modulo magazzino
anche articolo e ordine d’acquisto. Con un cliente selezionato, gli
elenchi mostrano solo i suoi oggetti. Un numero di serie noto a magazzino
viene collegato automaticamente al fascicolo. Su
fattura, ordine, asset e ticket di assistenza, «Apri reclamo» apre la finestra con oggetto e
cliente già compilati.

**Valutazione e decisione:** tipo di pretesa (garanzia del produttore,
garanzia legale o contrattuale, cortesia commerciale, danno da
trasporto, uso improprio, errore del fornitore) con motivazione
obbligatoria. Il quadro dei fatti (verifica dei numeri di serie,
scadenze, data della denuncia dei vizi secondo il § 377 HGB nel caso
B2B) viene congelato come snapshot. Le decisioni richiedono una
valutazione attiva e sono auditabili; una decisione automatica sulla
pretesa non esiste, per scelta deliberata.

**Resi (RMA):** i resi ricevono un numero RMA, l'entrata merci finisce
in quarantena (giacenza bloccata/di controllo), la verifica documenta
l'esito e il riscontro dei numeri di serie. La decisione di impiego
(reimmagazzinamento, riparazione, restituzione al fornitore,
rottamazione, smaltimento) registra in modo idempotente tramite il
giornale di magazzino.

**Riferimento di magazzino del reso:** annuncio ed entrata merci accolgono
variante di articolo, quantità, magazzino, numero di serie e lotto,
precompilati da articolo e numero di serie del fascicolo. Con variante,
magazzino e quantità, l’entrata merci registra la giacenza in quarantena;
la decisione di destinazione la trasferisce o la scarica. Senza variante o
senza modulo magazzino il reso viene solo documentato e il messaggio
indica «registrata senza movimento di giacenza». I resi dal portale
clienti portano con sé variante e numero di serie; il magazzino si
completa all’entrata merci.

**Misure e ordine di follow-up:** rilavorazione, riparazione, sostituzione o
intervento si creano in «Misure» con scadenza e persona responsabile. Ogni
misura può indicare il proprio ordine di follow-up: un ordine del cliente della
pratica che Lei può aprire. L'intervento passa per la pianificazione e i giri
tramite questo ordine; la pratica lo collega, e Lei può modificarlo o
rimuoverlo insieme allo stato della misura.

**Conseguenze commerciali:** riduzione, nota di credito, storno,
correzione, fattura sostitutiva o rimborso vengono proposti, approvati
secondo il principio dei quattro occhi e solo allora eseguiti. I
documenti nascono nel modulo di fatturazione (nota di credito/storno
come bozza) con codice motivo strutturato — non esiste un tipo di
documento dedicato.

**Fattura della conseguenza:** nella finestra «Proponi conseguenza» si
sceglie la fattura, precompilata con quella del fascicolo. Se emette Lei
le fatture, ogni conseguenza con documento richiede questa fattura: lo
storno presuppone una fattura emessa e non pagata, la nota di credito una
fattura pagata. Se un sistema esterno gestisce le fatture, il documento di
rettifica nasce lì e Lei ne riporta poi il numero.

**Rivalsa sul fornitore:** pretesa propria verso il fornitore a monte
con riferimento a ordine/fattura in entrata, termine di risposta e
recupero dei costi.

La finestra «Crea rivalsa» accoglie fornitore, ordine d’acquisto, fattura
in entrata, articolo, numero di serie, condizioni di garanzia, importo
richiesto e un termine di risposta proprio in giorni. Senza indicazione
vale il termine standard; decorre dallo stato «presentato».

**Analisi:** il report qualità mostra quota, cause, articoli
interessati, fornitori, costi, tempi di lavorazione ed errori
ricorrenti; gli stati del report possono essere congelati come
evidenza.

**Portale clienti:** i clienti vedono lo stato dei propri casi e
possono trasmettere integrazioni — le valutazioni interne e gli
importi restano invisibili.

**Lotto nella pratica:** per gli articoli gestiti a lotti scegliete il lotto interessato nella finestra del reclamo. Senza articolo scelto, la pratica riprende l’articolo del lotto; il riconoscimento di schemi «stesso lotto» valuta il campo.

## Rifiutare una proposta

Chi approva le conseguenze commerciali può anche **rifiutare** una proposta. Si conclude senza documento e non può essere né approvata né eseguita; resta possibile una nuova proposta.
