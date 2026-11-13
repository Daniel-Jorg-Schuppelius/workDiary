---
title: "Modifiche offline"
topic: offline.changes
version: 3
keywords:
    - lavorare offline
    - senza connessione
    - senza rete
    - coda di sincronizzazione
    - sincronizzazione
    - modifiche in sospeso
    - voci rifiutate
    - modalità offline
    - non sincronizzato
audience: []
related:
    - attendance.manage
    - forms.fill
---

Le azioni registrate senza connessione (timbrature, commenti alle commesse, moduli) vengono accodate su questo dispositivo e trasferite automaticamente al ritorno della connessione.

**In attesa:** voci non ancora trasferite. Vengono inviate al prossimo contatto online; «Elimina» le rimuove prima dell'invio.

**Rifiutate:** voci non accettate dal server (campo obbligatorio mancante, timbratura già aperta…) — con motivazione. «Riapplica» rimette la voce in coda.

**Importante:** l'elenco esiste solo su questo dispositivo e viene svuotato al logout. File e firme non possono essere registrati offline.

## Cosa funziona senza connessione

Oltre a timbrature, commenti alle commesse e moduli, offline può registrare anche **tempo di progetto** con la barra di inserimento di «Oggi» (durata o da/a, non il timer), **materiale** con «Materiale» su «Oggi» e **correzioni delle Sue timbrature** con l’icona della matita nell’elenco delle timbrature di «Oggi». Queste finestre fanno parte della pagina e quindi si aprono senza rete se «Oggi» è stata caricata prima.

## Decidere i conflitti

Un conflitto significa che la registrazione **non** è stata acquisita perché nel frattempo qualcosa è cambiato o manca un dato obbligatorio: ad esempio una timbratura modificata, una giornata chiusa o una registrazione senza progetto. Decide Lei:

- **Mantieni l’altra versione** o **Elimina**: la Sua versione viene scartata.
- **Invia la mia versione**: solo per una timbratura modificata; la Sua correzione si applica al nuovo stato.
- **Modifica**: adattare i campi (data, durata o motivazione) e inviarli come nuova voce.
- **Giacenza insufficiente**: se per il materiale manca solo la giacenza, «Modifica» offre, con il permesso «Autorizzare giacenze negative», la casella «Consenti giacenza negativa». Se l’organizzazione richiede quattro occhi, l’invio crea una richiesta nella casella delle approvazioni. Senza il permesso il conflitto resta; adatti la quantità o lo scarti.

Ogni decisione viene registrata nel registro di audit. La pagina mostra anche i conflitti aperti nati su un altro dispositivo; i contenuti si trovano lì, quindi qui possono solo essere scartati.
