---
title: "Lotto di scritture DATEV"
topic: finance.datev-bookings
version: 7
keywords:
    - export DATEV
    - commercialista
    - esportare registrazioni
    - EXTF
    - piano dei conti
    - SKR03
    - SKR04
    - numero debitore
    - chiave di registrazione
    - conto ricavi
    - export contabile
    - scritture contabili
    - controllo fiscale
    - partita IVA mancante
audience: []
modules:
    - module.finance
schema: process
related:
    - invoices.manage
    - finance.transfers
    - finance.reconciliation
    - roles.buchhaltung
---

## Scopo e contesto

Il lotto di scritture DATEV consegna allo studio fiscale fatture
emesse, note di credito e, in opzione, spese approvate di un periodo
chiuso come file DATEV verificabile (formato V700). Principio:
WorkDiary non crea **nessuna** contabilità, ma un lotto di consegna
pulito. Se un software di fatturazione esterno (DATEV o Lexoffice)
guida le fatture, queste **non** appartengono al lotto locale —
vengono escluse automaticamente e mostrate nella vista di controllo.

## Prerequisiti

L'amministrazione registra una volta la configurazione contabile
dell'organizzazione:

- numero consulente e mandante,
- piano dei conti (SKR03 o SKR04) e lunghezza dei conti,
- conto ricavi predefinito e conto separato per ricavi 0 % / esenti,
- la base dell'intervallo dei numeri debitore,
- l'abbinamento delle aliquote (19 %, 7 %, 0 %) alle chiavi di
  registrazione DATEV,
- il flag di blocco (GoBD) e il set di caratteri (di norma
  ISO-8859-1).

Il numero debitore può essere tenuto per cliente; se manca, viene
derivato in modo deterministico dalla base dell'intervallo e dal
numero cliente. Creare, finalizzare e scaricare i lotti spetta al
ruolo **contabilità** (e agli amministratori); la configurazione agli
amministratori.

Per la contabilità locale la sezione **Conti** contiene inoltre il **conto
sconti** e il **conto di storno (perdite su crediti)**: le contropartite usate
quando una partita aperta viene pareggiata con sconto o stornata. Se lasciati
vuoti, vale il valore predefinito del piano dei conti (SKR03 8736 e 2400, SKR04 4736 e 6930). Per i debiti esistono il **conto
sconti ottenuti** (3736 o 5736) e il **conto di storno dei debiti** (altri
ricavi, 2709 o 4839). Se esiste un piano dei conti locale, il conto indicato deve
esistervi; se manca un conto effettivo, la pagina lo segnala.

## Procedura consigliata

1. **Creare il lotto:** scegliere il periodo, includere se serve le
   spese approvate — nasce una **bozza** con i documenti pronti.
2. **Controllare:** l'anteprima mostra la scrittura per documento —
   segno dare/avere, conto debitore e ricavi, chiave, numero
   documento, importo lordo — con il totale. Anagrafiche mancanti
   appaiono come **avviso**, chiavi mancanti come **errore**
   bloccante.
3. **Finalizzare:** solo ora nasce il file DATEV; viene registrata
   un'impronta SHA-256 e i documenti valgono come consegnati. Un
   lotto finalizzato è **immutabile**.
4. **Scaricare** e consegnare allo studio.

![Lotti di scritture DATEV con indicatori, configurazione e creazione lotto](media/buchhaltung/datev-stapel.png)
*La panoramica dei lotti: indicatori, configurazione, anagrafiche EXTF e «Crea lotto».*

## Esempio pratico

A inizio mese la contabilità crea il lotto del mese precedente: due
documenti avvisano di un numero debitore mancante — dopo la modifica
sul cliente gli avvisi spariscono, il lotto viene finalizzato e il
CSV va allo studio con la sua impronta.

## Errori tipici

- **Voler consegnare due volte la stessa fattura:** i documenti
  finalizzati sono bloccati — le correzioni passano da nota di
  credito o documento correttivo nel lotto successivo.
- **Ignorare gli avvisi:** le anagrafiche mancanti emergono
  altrimenti in studio.
- **Aspettarsi i giustificativi nel lotto:** PDF/foto non ne fanno
  parte; restano sulla pratica e vanno allo studio separatamente.

## Effetti e prossimi passi

Contano le fatture emesse e pagate con data documento nel periodo; le
note di credito diventano scritture inverse. Dopo la consegna: curare
l'abbinamento dei pagamenti ed esportare il periodo successivo solo
dopo la sua chiusura.

## Trasmissione e relazione di accompagnamento

Dopo la finalizzazione, **Registra trasmissione** annota cosa accade al lotto: **trasmesso** allo studio e lì **confermato** o **rifiutato**. Un lotto rifiutato viene trasmesso di nuovo dopo il chiarimento; le correzioni dei documenti passano sempre dal lotto successivo. Ogni passaggio compare con data, persona e nota facoltativa nella cronologia del lotto, e l’elenco mostra lo stato attuale. Le note vanno scritte senza dati personali, perché la cronologia non è modificabile.

La **relazione di accompagnamento (PDF)** riassume il lotto per lo studio: periodo, numero di consulente e di cliente, numero di registrazioni e storni, totale, checksum del file, totali per conto ricavi e per codice fiscale e la cronologia.

## Trasferimento DATEV dal giornale

Se WorkDiary tiene il libro mastro, il lotto di registrazioni DATEV nasce dalle
registrazioni definitive del giornale (scheda **Trasferimento DATEV** in
**Chiusura dei periodi**). Ogni registrazione diventa scritture DATEV contro
una riga di ancoraggio – di solito il conto crediti, debiti o di liquidità –,
così DATEV la registra una sola volta. Una riga con codice IVA parte al lordo
con la sua **Chiave fiscale DATEV**; le righe IVA vengono omesse perché DATEV
registra l’imposta da sé. Con l’inversione contabile (§ 13b, acquisto
intracomunitario) l’importo resta al netto e DATEV registra IVA a debito e a
credito. Su un conto automatico (campo **Automatismo DATEV** del conto) DATEV
calcola l’imposta dal conto e il lotto non vi imposta alcuna chiave. Se
l’automatismo non corrisponde alla registrazione, la chiave 40 lo sospende e
l’imposta resta una scrittura a sé. Anche uno sconto con rettifica d’imposta
parte come un’unica scrittura.

## Vendite senza imposta

Se una fattura non riporta imposta, il lotto la registra secondo il suo regime
fiscale, come la contabilità: cessione intracomunitaria (SKR03 8125 / SKR04 4125),
prestazione verso un altro Paese UE (8336/4336), inversione contabile nazionale
(8337/4337), esportazione (8120/4120), vendita non imponibile in un Paese terzo
(8338/4338). Sono conti automatici DATEV; il lotto non vi indica alcuna chiave
fiscale. Le vendite esenti nazionali restano sul conto impostato per i ricavi
esenti.

## Controllo fiscale prima della finalizzazione

L'anteprima di una bozza mostra il **controllo fiscale** del lotto con
collegamento al documento. Gli **errori** bloccano **Finalizza**: una scrittura
con aliquota ma senza chiave BU su un conto che non è automatico; una
cessione intracomunitaria esente o un'inversione contabile in un altro paese
UE senza partita IVA del cliente; un conto disattivato del piano dei conti
locale. Gli **avvisi** segnalano scritture la cui aliquota differisce dal
codice del conto automatico e l'inversione contabile nazionale senza partita
IVA. Sono conti automatici quelli con codice IVA predefinito nel piano dei
conti locale; senza piano locale, quelli del modello del piano impostato. Se
un errore è corretto in un caso particolare, una persona con **Sbloccare il
controllo fiscale prima dell'esportazione DATEV** lo sblocca con motivazione;
lo sblocco resta nel registro del lotto e vale anche se lo stesso documento si
trova in una nuova bozza. Il controllo non modifica il lotto.
