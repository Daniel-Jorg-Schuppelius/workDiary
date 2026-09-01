---
title: "Conflitti di giacenza (trasferimento esterno)"
topic: inventory.conflicts
version: 1
audience:
    - admin
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

**Permessi e filtri:** per la consultazione è sufficiente il permesso
di lettura delle giacenze; per la risoluzione è inoltre necessario il
permesso di registrazione, perché la compensazione è una vera
registrazione di magazzino. L'elenco può essere filtrato per conflitti
aperti o per tutti i conflitti.

I conflitti aperti dovrebbero essere verificati tempestivamente: finché
esistono, la giacenza locale e quella esterna divergono — con
conseguenze su disponibilità, proposte d'ordine e valorizzazione.
