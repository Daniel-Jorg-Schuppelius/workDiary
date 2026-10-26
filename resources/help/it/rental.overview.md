---
title: "Noleggio attrezzature"
topic: rental.overview
version: 3
keywords:
    - locatore
    - casa vacanze
    - locazione
    - noleggio macchine
    - noleggio utensili
    - parco noleggio
    - contratto di noleggio
    - cauzione
    - prenotare attrezzatura
    - restituzione
    - verbale di consegna
    - calendario disponibilità
    - listino noleggio
    - telematica
audience: []
modules:
    - module.rental
related:
    - claims.overview
---

Il modulo gestisce il noleggio di apparecchi e macchine come pratiche
tracciabili — dalla prenotazione alla restituzione, cauzione e
fatturazione comprese.

**Parco attrezzature:** un profilo di noleggio rende noleggiabile un
asset (gruppo, tempi cuscinetto, accessori, listino predefinito).

**Disponibilità:** il calendario mostra prenotazioni, noleggi e finestre
di manutenzione. Doppie prenotazioni e apparecchi bloccati vengono
impediti in modo visibile.

**Pratica di noleggio:** ogni operazione riceve un numero (VER-…); la
versione del listino applicata viene congelata come snapshot.

**Consegna e restituzione:** protocolli separati documentano stato,
accessori, contatori, foto e firma; la restituzione porta la decisione
successiva (pulizia, riparazione/blocco, reclamo).

**Fatturazione:** le voci vengono approvate e fatturate localmente o
trasferite al sistema di fatturazione principale. La cauzione è
un'operazione finanziaria separata.

**Luogo d'impiego e geofence:** Quando le posizioni delle attrezzature
arrivano tramite importazione (ad esempio da un'esportazione telematica),
WorkDiary verifica durante un noleggio se l'attrezzatura si trova nel luogo
d'impiego: presso la sede del noleggio con il raggio impostato nelle
impostazioni, altrimenti nei geofence del cliente. Se lo lascia, vengono
avvisati la persona responsabile e la direzione del team.

## Appartamenti e case vacanze

Il profilo **Locatori** consiglia il modulo locazioni. La vista degli appartamenti usa il periodo globale e mostra anche quelli liberi. Le locazioni registrano inquilino, inizio, fine facoltativa, canone, spese e cauzione. Una locazione confermata blocca fino all’ultimo giorno incluso, senza limite se la fine è vuota. Imposti la fine all’uscita; annullare rimuove l’intera occupazione.

Accettare una richiesta turistica crea una prenotazione confermata. La tariffa giornaliera rappresenta il prezzo per notte, con pulizia finale facoltativa. Capacità, soggiorno minimo e orari sono verificati. Pubblichi l’appartamento e abiliti le richieste per il cliente. Canoni e cauzioni sono dati amministrativi del MVP, senza fatturazione automatica.

Il profilo include inoltre tre procedure (**Consegna appartamento**, **Verifica di riconsegna**, **Cambio ospiti**), il **verbale di consegna dell’appartamento** con letture dei contatori e chiavi, modelli di contratto per la locazione abitativa con e senza canone indicizzato e per le condizioni di affitto di una casa vacanze, con scadenze come il rendiconto annuale delle spese, e campi personalizzati dell’appartamento (superficie, vani, piano, animali, attestato energetico). Una locazione richiesta si conferma, rifiuta o annulla; una confermata si può annullare. Le locazioni annullate o rifiutate restano invariate.
