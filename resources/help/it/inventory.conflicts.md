---
title: "Conflitti con sistemi esterni (giacenze e articoli)"
topic: inventory.conflicts
version: 3
keywords:
    - differenza di giacenza
    - errore di sincronizzazione
    - sincronizzazione fallita
    - gestionale
    - ERP
    - registrazione di compensazione
    - conflitto articolo
    - allineare giacenze
    - dati non allineati
    - Lexware Office
    - Lexoffice
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
modules:
    - module.lager
related:
    - inventory.stock
    - warehouses.manage
---

Se un sistema esterno detiene la titolarità delle giacenze (ad esempio
un gestionale di magazzino), WorkDiary vi rispecchia ogni movimento di
magazzino registrato localmente. Questa pagina mostra i casi in cui il
rispecchiamento è definitivamente fallito — sono il luogo per la
rielaborazione di merito.

**Trasferimento con idempotenza:** ogni movimento genera al massimo un
incarico di consegna in una coda persistente. Se la stessa operazione
viene avviata più volte, nasce comunque un solo trasferimento — le
registrazioni doppie nel sistema esterno sono così escluse. Gli errori
temporanei vengono ritentati automaticamente.

**Quando nasce un conflitto:** se la consegna di un movimento fallisce
definitivamente — ad esempio perché il sistema esterno la rifiuta —
nasce un conflitto. La registrazione locale resta valida, ma la
giacenza esterna diverge. Ogni conflitto compare qui con il riferimento
al movimento sottostante e attende una decisione consapevole.

**Risoluzione:** per ogni conflitto esistono due vie. *Mantenere lo
stato locale* accetta espressamente la divergenza e chiude il conflitto
senza ulteriori registrazioni — sensato quando lo stato locale è
corretto nel merito. *Compensare* pareggia il movimento locale con una
contro-registrazione di pari importo nella stessa giacenza. Non si
elimina mai a posteriori né si esegue un rollback tecnico; il giornale
di magazzino resta senza lacune e ogni decisione viene registrata con
persona e momento.

**Conflitti di articoli:** lo stesso elenco mostra gli articoli
modificati localmente il cui stato differisce nel sistema esterno
collegato (ad esempio Lexware Office) — con la strategia di conflitto del
plugin impostata su «Verifica manuale». Per ogni conflitto sono affiancati
l'articolo, i campi divergenti ed entrambi i valori. Tre vie: *Mantieni
locale* chiude il conflitto; lo stato locale resta e viene trasmesso al
sistema esterno alla prossima sincronizzazione. *Applica lo stato del
sistema esterno* (ad esempio «Applica lo stato di Lexoffice») recupera
l'articolo di nuovo dal sistema esterno e sovrascrive la modifica locale.
*Ignora* chiude il conflitto senza riconciliazione — entrambi gli stati
restano come sono; se l'articolo differisce ancora alla prossima
sincronizzazione, viene creato un nuovo conflitto.

**Permessi e filtri:** la scheda «Conflitti» nella barra delle schede del
magazzino mostra il numero di conflitti aperti. Per la consultazione è
sufficiente il permesso di lettura delle giacenze o quello di lettura degli
articoli; senza permesso sulle giacenze vede solo i conflitti di articoli,
senza permesso sugli articoli solo i conflitti di giacenza. La risoluzione
dipende dal tipo: i conflitti di giacenza richiedono il permesso di
registrazione, perché la compensazione è una vera registrazione di
magazzino; i conflitti di articoli richiedono il permesso di gestione degli
articoli. L'elenco può essere filtrato per conflitti aperti o per tutti i
conflitti e per tipo (giacenza, articolo).

I conflitti aperti dovrebbero essere verificati tempestivamente: finché
esistono, la giacenza locale e quella esterna divergono — con
conseguenze su disponibilità, proposte d'ordine e valorizzazione.
