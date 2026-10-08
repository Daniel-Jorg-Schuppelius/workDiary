---
title: "Chiusura giornaliera"
topic: time-entries.day-close
version: 3
keywords:
    - chiudere la giornata
    - fine giornata
    - aggiungere pausa
    - bilancio giornaliero
    - saldo giornaliero
    - lacune orarie
    - pausa obbligatoria
    - timbratura aperta
    - richiedere correzione
    - registrare ore mancanti
audience: []
related:
    - time-entries.start
    - attendance.manage
    - time-accounts.flex
---

La **Chiusura giornaliera** raccoglie nella pagina **Oggi** (menu
**Operatività quotidiana** → **Inserimento** → **Oggi**) tutto ciò che
riguarda una giornata lavorativa: **Timbrature**, pause, **Registrazioni
ore**, i controlli in **Lacune e avvisi** e il **Bilancio** (tra l’altro
**Presenza (lorda)**, **Pausa obbligatoria**, **Saldo del giorno** e
**Saldo del mese corrente**).

Ecco come procedere:

1. **Verificare**: apra la pagina a fine giornata; con **Giorno
   precedente** e **Giorno successivo** passa ad altri giorni. Lacune e
   incongruenze compaiono nella sezione **Lacune e avvisi**.
2. **Integrare**: registri i tempi mancanti nella barra di inserimento in
   alto (scegliere un progetto, indicare **Durata** o **Da / A**,
   **Registra**). Assegni a un progetto i blocchi di presenza non ancora
   imputati in **Registrazione rapida** con **Registra**; lì `Ctrl` +
   `Invio` registra il blocco e passa al successivo. Le timbrature stesse
   sono modificabili solo tramite una richiesta di correzione.
3. **Chiudere**: se non restano avvisi ⛔, chiuda la giornata con **Chiudi
   giornata**. **Salva** conserva lo stato senza chiudere la giornata.

Gli avvisi ⛔ bloccano la chiusura: timbratrice ancora aperta, presenza
non imputata (oltre 5 minuti) o pausa obbligatoria non rispettata. Le
segnalazioni ⚠ non bloccano, ad esempio un saldo giornaliero oltre ±2
ore, più di 10 ore di lavoro netto, un’interruzione della presenza senza
pausa o registrazioni fatturabili senza commento.

Dopo la chiusura la giornata è bloccata per Lei. Se Le serve una
modifica, richieda un’approvazione tramite **Richiedi correzione**
(motivazione di almeno 20 caratteri). Decidono le persone con il permesso
**Approvare le correzioni delle chiusure giornaliere** (per impostazione
predefinita i capi team) o gli amministratori, con **Approva** o
**Rifiuta**. Dopo l’approvazione sono modificabili solo le registrazioni,
le timbrature restano bloccate. Chi dispone del permesso **Riaprire una
chiusura giornaliera** può anche sbloccare una giornata chiusa senza
richiesta con **Riapri giornata**; la motivazione viene registrata nel
registro di audit.

Le giornate di un mese già approvato sono completamente bloccate; lì ogni
modifica passa per l’approvazione mensile.
