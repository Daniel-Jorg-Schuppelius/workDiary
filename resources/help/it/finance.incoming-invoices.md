---
title: "Fatture ricevute"
topic: finance.incoming-invoices
version: 2
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

**Casella delle fatture:** in Amministrazione → Ricezione e-mail, una casella
entra a far parte delle fatture ricevute con l’interruttore «Casella delle
fatture». Ogni e-mail viene valutata per fatture, non per allegati:

- Una XRechnung (XML) è l’originale. Un PDF della stessa fattura inviato
  insieme viene allegato come file di accompagnamento.
- Altri allegati, come condizioni generali o documenti di trasporto,
  diventano file di accompagnamento.
- I loghi incorporati e le immagini di firma non vengono elaborati.
- Se un’e-mail non contiene alcuna fattura riconoscibile, l’allegato diventa
  un **documento non riconosciuto**: l’originale viene conservato e Lei
  inserisce i valori.
- Le e-mail senza alcun allegato di fattura (per esempio solo con un link di
  download) finiscono nella casella di assegnazione dell’Amministrazione,
  contrassegnate come casella delle fatture.

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

**Validazione e coerenza:** ogni fattura elettronica viene validata rispetto
allo schema XML e, se configurato, alle regole KoSIT (EN 16931); viene
indicato se i controlli erano disponibili. Inoltre il controllo degli
scostamenti avvisa in modo visibile — mai in silenzio — di un numero di
fattura già registrato per lo stesso emittente, di totali contraddittori
(imponibile + imposta ≠ totale) e di imposta indicata senza identificativo
fiscale dell’emittente.

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
