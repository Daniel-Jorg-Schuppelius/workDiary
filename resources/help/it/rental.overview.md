---
title: "Noleggio attrezzature"
topic: rental.overview
version: 2
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
