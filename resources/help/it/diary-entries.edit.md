---
title: "Modificare un incarico"
topic: diary-entries.edit
version: 5
keywords:
    - modificare la commessa
    - dettagli dell'incarico
    - fascicolo
    - cambiare stato
    - chiudere l'incarico
    - completare l'intervento
    - aggiungere allegato
    - aggiungere commento
    - classificazione obbligatoria
    - dimensione allegati
audience: []
related:
    - diary-entries.create
    - protocols.create
---

Tocchi il titolo dell'incarico nell'elenco del registro per aprire la vista
di dettaglio (fascicolo); la modifica diretta avviene nelle singole
sezioni (commenti, allegati, tempi, verbali). Le classificazioni
obbligatorie devono essere compilate prima del passaggio allo stato
**Completato**. Le note di comunicazione sono elencate in ordine
cronologico sotto l'incarico e restano visibili per tutta la sua durata;
gli allegati possono pesare al massimo **25 MB**, i file più grandi vanno
referenziati tramite storage esterno.

La **Durata prevista (HH:MM)** si modifica nella finestra di modifica, sezione
**Periodo**. Se svuota il campo, vale di nuovo la durata dell’intervento di un ordine pianificato, altrimenti la durata della finestra oraria
o dell’appuntamento.

**Ciclo di vita:** restituisca un ordine accettato con **Restituire
l'accettazione** indicando la motivazione; l'ordine torna su «Pianificato».
**Mettere in pausa** e **Annullare** fermano i cronometri in funzione
sull'ordine e registrano il tempo. **Chiudere** richiede che nessun cronometro
sia in funzione: il messaggio indica chi è ancora attivo. Un annullamento
archivia i verbali aperti e interrompe le procedure in corso; i verbali firmati
restano. Al collaudo viene registrata la persona che ha firmato presso il
cliente; se il verbale non riporta la firma del cliente, la finestra chiede il
nome.

**Fatturato:** quando emette una fattura che fattura tempi di un ordine
collaudato, o trasferisce questi tempi al programma di contabilità, l'ordine
passa da solo a «Fatturato» e indica il documento. Se il documento viene
annullato, l'ordine torna su «Collaudato». L'inserimento manuale resta
possibile.

**Materiale:** allo stesso modo una fattura del materiale o il trasferimento del
materiale fattura l'ordine se il materiale figura su un foglio ore con tempi di
questo ordine.

**Filtrare la cronologia:** sopra la cronologia scelga periodo, uno o più tipi,
una persona e «Solo rilevante per il cliente».
