---
title: "Noleggio attrezzature"
topic: rental.overview
version: 9
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
Il referente viene precompilato dal cliente al momento della scelta e resta modificabile.

**Consegna e restituzione:** protocolli separati documentano stato,
accessori, contatori, foto e firma; la restituzione porta la decisione
successiva (pulizia, riparazione/blocco, reclamo).

**Voci del verbale:** i moduli di consegna e restituzione registrano, oltre
allo stato generale, una lista di controllo, gli accessori, singole voci di
stato e, alla restituzione, il consumo. Punti di controllo e accessori
provengono dal profilo di noleggio; deselezioni un accessorio mancante. La
pratica mostra le voci sotto «Mostra le voci», e così il portale clienti,
senza osservazioni interne.

**Stato minimo:** nel profilo di noleggio Lei definisce la sede di
appartenenza, lo stato minimo e lo stato attuale. Ogni restituzione riprende
lo stato dal verbale. Se è inferiore al minimo, l'apparecchio non può essere
prenotato né usato come sostituto finché Lei non adegua lo stato nel profilo
dopo la riparazione.

**Pulizia:** «Pulizia necessaria» nel verbale di restituzione crea una
finestra di pulizia; durante questa l'apparecchio non può essere assegnato.
«Pulizia completata» nella pratica lo libera prima della fine della finestra.

**Prenotazioni provvisorie:** se un'altra pratica ha una prenotazione
provvisoria nel periodo, la pratica mostra un avviso con le pratiche
interessate. La prenotazione resta possibile.

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

## Prenotazione diretta con pagamento anticipato online

Se in «Organizzazione e impostazioni» è impostato un **Pagamento anticipato per
prenotazione diretta** (o l’oggetto di noleggio ha una quota propria), la
prenotazione diretta nel portale clienti porta alla pagina di pagamento del
fornitore di pagamento:

- La pratica viene riservata subito e trattiene l’attrezzatura fino al
  **termine di pagamento**. Fino ad allora la prenotazione risulta **pagamento
  in sospeso** in **Richieste di noleggio**.
- Al ricevimento del pagamento (notifica del fornitore o ritorno del cliente) la
  prenotazione viene **accettata**; solo allora Lei riceve la notifica.
- Senza pagamento entro il termine la prenotazione **scade**, la pratica viene
  annullata e l’attrezzatura torna libera. Se un pagamento arriva solo dopo,
  WorkDiary non conferma nulla ma Glielo segnala per un rimborso o una nuova
  prenotazione.
- La pratica mostra il pagamento anticipato versato. Al trasferimento delle
  posizioni di noleggio in fattura viene assegnato alla fattura e la copre. Lo
  storno di quella fattura lo libera; la fattura successiva lo scomputa.
- Se un programma di contabilità gestisce le fatture, il pagamento anticipato
  è considerato scomputato con il trasferimento delle posizioni di noleggio: la
  pratica e il registro del trasferimento indicano l’importo da scomputare lì
  dalla fattura, e un trasferimento successivo non lo conta di nuovo.
- Se WorkDiary tiene la contabilità, la casella di contabilizzazione registra
  il pagamento anticipato alla data del pagamento come acconto ricevuto, con
  IVA: l’imposta sorge con l’incasso (tassazione minima per cassa), non solo
  con la fattura. La fattura di WorkDiary che lo scomputa compensa l’acconto
  — il suo credito è solo il residuo aperto, e l’IVA dell’acconto viene
  compensata con l’imposta della fattura, così che sorga una sola volta. Una
  fattura di storno annulla la compensazione; l’acconto torna aperto finché
  la fattura successiva non lo scomputa. Un rimborso dopo la
  contabilizzazione riporta pagamento e fattura nella casella di
  contabilizzazione (stornare e contabilizzare di nuovo). Per questo
  l’assegnazione dei conti richiede il ruolo «Acconti ricevuti» (incluso nei
  modelli SKR03 e SKR04).

**Condizione minima alla consegna:** la condizione minima del profilo di noleggio vale anche alla consegna. Se la condizione è peggiorata dopo la prenotazione, WorkDiary rifiuta la consegna finché l’apparecchio non è riparato e la condizione aggiornata nel profilo.

## Condonare una cauzione

Una cauzione richiesta e non ancora ricevuta può essere **condonata** con una motivazione. Mostra poi **Rinunciato**; la motivazione è annotata nel registro del noleggio.
