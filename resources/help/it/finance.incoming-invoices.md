---
title: "Fatture ricevute"
topic: finance.incoming-invoices
version: 3
keywords:
    - fattura fornitore
    - fattura d'acquisto
    - fatture in entrata
    - casella delle fatture
    - fatture ricevute
    - ricevere XRechnung
    - ZUGFeRD
    - Factur-X
    - validare fattura elettronica
    - assegnare fattura
    - fornitore collettivo
    - cliente collettivo
    - documento non riconosciuto
    - approvazione fatture
    - debiti verso fornitori
    - autorizzazione al pagamento
    - EN 16931
    - consegna a Lexware
    - categoria contabile
    - doppione
    - fattura duplicata
    - cartella fatture
audience: []
modules:
    - module.vertrieb
related:
    - invoices.manage
    - finance.datev-bookings
---

Le **Fatture ricevute** (menu Fatturazione → Fatture ricevute) accolgono le
fatture, le assegnano a fornitori o clienti e le accompagnano attraverso la
verifica e l’autorizzazione al pagamento, senza intaccare la sovranità di
fatturazione del Suo software contabile o di fatturazione principale.

**Canali di ricezione:** le fatture arrivano tramite la casella delle
fatture, il caricamento di file, Peppol o l’archiviazione cloud. Tutti i
canali seguono la stessa elaborazione: controllo dei duplicati, controllo di
sicurezza, lettura della fattura elettronica o riconoscimento da PDF o
immagine, validazione e scostamenti. L’originale invariato viene archiviato
come documento di tipo fattura nel DMS.

**Casella delle fatture:** in Amministrazione → Dati e interfacce → Ricezione
e-mail, apra la casella con «Modifica» (o ne crei una nuova) e attivi nella
finestra, sotto «Utilizzo», l’interruttore «Casella delle fatture». Nell’elenco
delle caselle compare poi il contrassegno «Fatture». Ogni e-mail viene valutata
per fatture, non per allegati:

- Una XRechnung (XML) è l’originale. Un PDF della stessa fattura inviato
  insieme viene allegato come file di accompagnamento.
- Altri allegati, come condizioni generali o documenti di trasporto,
  diventano file di accompagnamento.
- I loghi incorporati e le immagini di firma non vengono elaborati.
- Se un’e-mail non contiene alcuna fattura riconoscibile, l’allegato diventa
  un **documento non riconosciuto**: l’originale viene conservato e Lei
  inserisce i valori.
- Se al server mancava il riconoscimento del testo all’arrivo dell’e-mail,
  l’azione di massa «Riconosci di nuovo» nell’elenco dei documenti da
  assegnare ripete il riconoscimento dei documenti non riconosciuti
  selezionati a partire dall’originale conservato. I valori inseriti e i
  documenti trasmessi o rifiutati restano invariati.
- Le e-mail senza alcun allegato di fattura (per esempio solo con un link di
  download) finiscono nella casella di assegnazione dell’Amministrazione,
  contrassegnate come casella delle fatture.
- Un’e-mail inoltrata come allegato viene aperta; la fattura che contiene
  conta come un allegato diretto.

**Recupero ed e-mail esistenti:** le e-mail vengono recuperate a partire dal
giorno indicato in «Recupera dal», dall’ultimo punto raggiunto — anche quelle
già lette. Scelga preferibilmente una cartella dedicata in cui una regola
smisti le fatture (sottocartelle separate da «/», ad es. Posta in
arrivo/Fatture). «Recupera e-mail precedenti» nell’amministrazione delle
caselle recupera fatture più vecchie; la stessa fattura non viene creata due
volte. Se un’e-mail con fattura si trova in un’altra casella, «Acquisisci come
fattura in entrata» nella inbox di associazione ne acquisisce gli allegati.

**Riconoscimento:** una fattura elettronica (XRechnung o ZUGFeRD/Factur-X)
fornisce valori vincolanti. Per PDF e foto i valori vengono riconosciuti e
sono una proposta che Lei verifica sull’originale. Se una fattura del B2B
nazionale non è una fattura elettronica, compare un avviso: è ammessa solo in
via transitoria, fino alla fine del 2026 o, per i piccoli emittenti, fino
alla fine del 2027. L’avviso non blocca nulla; le fatture di importo minimo
fino a 250 € sono escluse.

**Direzione:** se Lei è l’acquirente, si tratta di un documento in entrata con
un fornitore come controparte. Se è il venditore — per esempio con copie di
fatture di un negozio o di una cassa, o con note di credito in
autofatturazione —, si tratta di un documento in uscita con un cliente come
controparte. I documenti in uscita non compaiono mai nelle proposte di
pagamento, nei lotti di pagamento o nelle trattenute. Se una fattura indica
un acquirente estraneo, il controllo segnala «non indirizzata a noi»; viene
segnalata anche la copia di una propria fattura già registrata.

**Elenco di lavoro:** le schede «Da assegnare» e «Da verificare» mostrano
tutti i documenti aperti, «Tutti» il periodo selezionato. La voce di menu
conta i documenti da assegnare. La contabilità riceve una notifica al mattino
finché resta qualcosa da assegnare.

**Assegnazione:** un documento viene assegnato automaticamente solo se
esattamente una parte corrisponde a un identificativo esatto del documento:
partita IVA, codice fiscale o IBAN. Se ne corrispondono più, il documento
resta da assegnare e il controllo indica i candidati. Gli identificativi
propri della Sua organizzazione non contano mai come corrispondenza. Con
«Assegnare» sceglie Lei:

- una parte esistente (i suggerimenti compaiono per primi),
- il fornitore collettivo o il cliente collettivo,
- una nuova parte, precompilata dai dati del documento,
- «Non è una fattura» — il documento viene rifiutato con una motivazione.

Lì può anche correggere la direzione. Con «Ricordare il mittente» il sistema
assegna le future e-mail di questo mittente alla stessa parte, purché il
documento stesso non indichi un’altra parte. Per i documenti non riconosciuti
e riconosciuti inserisca numero, data e importi con «Inserire i valori».
Assegni insieme nell’elenco più documenti della stessa parte.

Con «Trattare allo stesso modo anche gli altri documenti aperti» sbriga tutti i
documenti aperti della stessa controparte in un passaggio, sia nell’assegnazione
sia nel rifiuto. In «Non è una fattura» decide cosa accade ai futuri documenti
di questa controparte: «archiviare automaticamente come non è una fattura»
oppure «non acquisire affatto (scartare)». Scartare rimuove il documento, il
file e gli allegati; resta la voce di audit con checksum, numero, venditore,
mittente e motivo. WorkDiary crea a tale scopo una regola della posta in arrivo.

Le **regole della posta in arrivo** sono regole di automazione dell’attivatore
«Fattura in entrata ricevuta». Le vede tramite «Regole» nel menu della pagina,
insieme alle regole mittente, le disattiva, le attiva e le elimina; gli
amministratori modificano condizioni e azioni nella pagina delle automazioni.
Le condizioni verificano tra l’altro venditore (`seller_name_key`,
normalizzato), partita IVA (`seller_vat_id`), mittente (`sender_email`,
`sender_domain`), oggetto (`mail_subject`), nomi degli allegati
(`attachment_names`), nome file (`file_name`), paese (`country`), importo
lordo (`amount_gross`) e riconoscimento (`recognition`); gli operatori
comprendono «è», «contiene», «inizia con», «termina con» e «corrisponde a»
(espressione regolare). Azioni: scartare, archiviare come non è una fattura,
assegnare a una parte. Vince la prima regola corrispondente; le regole si
applicano al recupero, al caricamento e al nuovo riconoscimento.

I documenti rifiutati si trovano nella scheda «Rifiutato», dove può eliminarli
definitivamente. Trascorso il periodo impostato (30 giorni per impostazione
predefinita), la conservazione ne propone la distruzione; l’eliminazione
avviene solo dopo l’approvazione.

**Riconoscimento di PDF e scansioni:** WorkDiary legge etichette e valori
anche quando si trovano in colonne separate o in una riga di intestazione
sopra la riga dei valori. Se manca la data della fattura, vale «Luogo, data»
nell’intestazione o una semplice «Data»; una data di consegna viene solo
proposta e segnalata nella finestra «Inserire i valori». Solleciti, bolle di
consegna, conferme d’ordine, preventivi ed etichette di spedizione vengono
riconosciuti come tali: l’elenco mostra «Sembra», la finestra di assegnazione
preseleziona «Non è una fattura» e i solleciti indicano le fatture interessate
con un link. Gli amministratori misurano il riconoscimento con
`incoming-invoices:measure-recognition` sui testi archiviati dei documenti.

**Modelli di documento:** Tramite «Modello» nella pagina del documento traccia
sul documento renderizzato rettangoli per numero fattura, data, importi,
partita IVA e venditore; il valore riconosciuto compare subito. Il modello
viene salvato con un’impronta di intestazione e piè di pagina della prima
pagina, così corrisponde a ogni ulteriore documento dello stesso fornitore, su
richiesta subito a quelli aperti. Ogni correzione in «Inserire i valori»
apprende: il valore inserito viene individuato sul documento e memorizzato
come zona; dopo la seconda corrispondenza la zona appresa vale come confermata
e prevale sull’euristica. I modelli si trovano sotto «Regole» e possono
esservi eliminati. Le zone esistono solo per documenti PDF.

**Fornitore collettivo e cliente collettivo:** per fornitori e clienti
occasionali ogni organizzazione dispone di un contatto collettivo. Il nome
della parte reale resta sul documento. Un contatto collettivo non viene mai
trasferito a un sistema contabile come contatto proprio, non può essere
unito, non riceve accesso al portale né una propria fattura. Per l’inversione
contabile (§ 13b), le operazioni intracomunitarie e i paesi terzi è bloccato:
lì serve un vero contatto aziendale.

**Controllo dell’IBAN:** se l’IBAN della fattura differisce da tutte le
coordinate bancarie registrate del fornitore, la pagina lo segnala e il
pagamento richiede una conferma. Le fatture al fornitore collettivo
richiedono sempre questa conferma. Una fattura non modifica mai i dati
anagrafici.

**Duplicati:** un contenuto di file identico viene registrato una sola volta
per organizzazione, anche tra canali diversi (un caricamento dopo una
ricezione via e-mail resta un duplicato).

**Possibile doppione:** se la stessa fattura arriva una seconda volta come
file diverso — ad esempio come PDF via e-mail e come XRechnung tramite Peppol
—, la ricezione fatture lo riconosce dal numero di fattura insieme
all’identificativo fiscale dell’emittente o alla parte assegnata. L’entrata
successiva non viene trasmessa alla contabilità finché Lei non conferma
«Nessun doppione» o la rifiuta. Il solo numero non basta: «1001» esiste presso
molti fornitori.

**Validazione e coerenza:** ogni fattura elettronica viene validata rispetto
allo schema XML e, se configurato, alle regole KoSIT (EN 16931); viene
indicato se i controlli erano disponibili. Inoltre il controllo degli
scostamenti avvisa in modo visibile — mai in silenzio — di totali
contraddittori (imponibile + imposta ≠ totale) e di imposta indicata senza
identificativo fiscale dell’emittente.

**Workflow di verifica:** un documento viene approvato, messo in domanda o
rifiutato (il rifiuto solo con motivazione). L’autorizzazione al pagamento è
possibile solo dopo l’approvazione. Ogni decisione viene registrata con
persona e orario.

**Consegna alla contabilità:** se Lexware Office o DATEV Unternehmen online è
collegato e lì la consegna è attivata, un documento parte non appena la sua
controparte è nota. L’approvazione resta il presupposto del pagamento, non
della consegna. Prima il sistema verifica: nessun documento non riconosciuto
senza valori, non rifiutato, indirizzato a noi, non una copia di una propria
fattura, totali coerenti e nessun contatto collettivo per l’inversione
contabile, le operazioni intracomunitarie o i paesi terzi.

- Lexware Office riceve il giustificativo «da verificare» con l’originale e,
  per una XRechnung, anche con il PDF inviato. Se lì esiste già un
  giustificativo con lo stesso numero per lo stesso contatto, viene solo
  collegato.
- Gli importi vengono inviati solo con una categoria contabile — sul
  fornitore o sul cliente, oppure come predefinita nelle impostazioni del
  plugin —, in euro e con le aliquote 0, 5, 7, 16 o 19 %. Altrimenti il
  giustificativo parte senza importi e la contabilità li completa in Lexware.
- DATEV Unternehmen online riceve l’originale come immagine del
  giustificativo.
- Se manca il numero di fattura, la consegna cerca in Lexware per contatto,
  data del giustificativo e importo. Una corrispondenza non viene collegata
  automaticamente: Lei sceglie «Collega a questo giustificativo» oppure «Altro
  giustificativo – crea nuovo».
- Tramite l’interfaccia non è possibile cercare i giustificativi esistenti in
  DATEV. Non invii quindi le stesse fatture anche tramite l’e-mail di
  caricamento DATEV; lì la protezione dai doppioni spetta solo alla ricezione
  fatture.
- Un giustificativo creato lì non si può più eliminare tramite l’interfaccia.
- Non invii le stesse fatture anche all’indirizzo e-mail dei giustificativi
  di Lexware: i giustificativi riconosciuti lì inizialmente non hanno numero e
  sfuggono al controllo dei duplicati.

Lo stato per destinazione è indicato nella pagina di dettaglio; le schede
«Consegna in sospeso» e «Consegna non riuscita» raccolgono ciò che è bloccato.
Il sistema ripete le consegne non riuscite ogni ora fino a cinque volte;
«Riprova» le avvia in qualsiasi momento. Senza un sistema contabile
collegato, «Trasferisci alla contabilità» registra la consegna come prova dopo
l’approvazione; una seconda chiamata non cambia nulla.

**Download XML:** l’XML della fattura può essere estratto dall’originale in
qualsiasi momento (per ZUGFeRD dall’allegato PDF). Ogni download viene
registrato con una somma di controllo come prova.
