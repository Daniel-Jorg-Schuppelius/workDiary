---
title: "Chiusura e analisi"
topic: accounting.closing
version: 4
keywords:
    - chiusura mensile
    - chiusura annuale
    - bloccare periodo
    - riaprire periodo
    - conto economico
    - report finanziari
    - previsione di liquidità
    - budget vs consuntivo
    - report IVA
    - export verifica fiscale
    - ripartizione costi
    - export DATEV
    - controllo fiscale DATEV
    - conto automatico
    - BU 40
    - partita IVA mancante
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
related:
    - accounting.overview
    - accounting.posting
---

I **periodi** si chiudono in due passi: *provvisorio* è un segnale,
*definitivo* è un blocco. Un controllo preliminare verifica bozze aperte e
registrazioni non in pareggio.

La **riapertura** richiede un diritto proprio, una motivazione e viene
registrata nella catena di prova.

Le **analisi** leggono solo registrazioni definitive. IVA e contabilità per
cassa sono **anteprime** verificabili: l'MVP non trasmette nulla al fisco.

**Senza contabilità locale:** se l’organizzazione tiene la contabilità in un
programma contabile, questi report si aprono comunque ma mostrano un avviso
in alto: valutano solo le registrazioni in WorkDiary e restano quindi vuoti
o incompleti. Fatturato e partite aperte del programma contabile si trovano
nell’analisi di fatturazione, nel flusso documenti e nel riquadro **Partite
aperte**.

**Esportazione dei report:** PDF, CSV ed Excel dei report finanziari,
dell'analisi di gestione e del budget richiedono, oltre al permesso di
lettura, il permesso **Esporta i report**; senza di esso i pulsanti di
esportazione non compaiono. Gli amministratori possono sempre esportare.

**Trasferimento**: il pacchetto di verifica contiene piano dei conti, giornale,
righe, partite aperte e periodi; il trasferimento DATEV nasce dalle
registrazioni definitive.

**Analisi di gestione, budget e liquidità:** Dalle stesse registrazioni
definitive nascono l'**analisi di gestione** (ricavi, costi e risultato per
gruppi), il **confronto con il budget** per conto e centro di costo — i valori
dell'anno precedente possono essere ripresi come punto di partenza — e la
**previsione di liquidità**. Tutte e tre sono analisi, non una seconda
contabilità: ciò che vede lì è quanto risulta dal giornale. Le correzioni si
fanno perciò sempre sulla registrazione, mai sul rapporto.

**Ripartizioni, approvazione del budget e voci pianificate:** Le chiavi di
ripartizione distribuiscono proporzionalmente i costi di un centro di costo
ausiliario su altri centri; l'analisi di gestione può mostrarlo «dopo il
ribaltamento», mentre le registrazioni restano invariate. Un budget approvato è
bloccato contro modifiche finché un'integrazione motivata non lo riapre; se una
registrazione supera il budget mensile, lo segnala ma viene comunque
registrata. Nella previsione di liquidità Lei aggiunge **voci pianificate** (ad
esempio acconti d'imposta o rate di finanziamento); ogni lunedì l'app registra
lo stato della settimana e «piano/consuntivo» lo confronta poi con i
movimenti bancari effettivi.

**Controllo fiscale prima dell'esportazione DATEV:** **Controllare ed
esportare** nella consegna DATEV mostra prima i rilievi del periodo, ciascuno
con collegamento alla registrazione. Gli **errori** bloccano l'esportazione:
una riga di ricavo o costo senza codice IVA benché la registrazione riporti
IVA a debito o a credito; una cessione intracomunitaria o un'inversione
contabile in un altro paese UE senza partita IVA del partner; una
registrazione su un conto disattivato. Gli **avvisi** sono da rivedere:
scritture su cui l'esportazione annulla l'automatismo del conto con BU 40 (con
numero e conto), un'aliquota diversa dal codice del conto automatico e
l'inversione contabile nazionale senza partita IVA. Il controllo legge i conti
automatici del piano dei conti e non modifica il file. Un errore scompare non
appena la registrazione o i dati anagrafici sono corretti. Se nel vostro caso
è corretto, una persona con il permesso **Sbloccare il controllo fiscale prima
dell'esportazione DATEV** lo sblocca con motivazione; lo sblocco resta nella
catena di prova della contabilità e vale solo per gli errori mostrati.
