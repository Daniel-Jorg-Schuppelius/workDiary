---
title: "Squadre, giornate e formazioni"
topic: club.matches
version: 1
audience: []
modules:
    - module.club
related:
    - club.groups
    - club.events
    - club.attendance
---

Gli sport di squadra e di racchetta (calcio, pallamano, basket, pallavolo,
hockey, tennistavolo, tennis …) si basano su gruppi, appuntamenti e presenze.
Uno sport è un **profilo sportivo**, cioè configurazione e non un caso speciale
nel sistema: famiglia sportiva, ruoli, dimensioni della rosa (campo/panchina),
formato del risultato (gol, punti per periodo, set), singolo/doppio, data di
riferimento della categoria d'età, discipline e tipi di risorse.
L'associazione adatta i profili o ne crea altri; le regole federali non sono
programmate in modo fisso.

**Squadre:** Una squadra è un gruppo contrassegnato come «squadra» con un
profilo sportivo (proprio o della sezione). La categoria d'età è un'etichetta
libera (es. U15); i criteri d'età di una squadra vengono verificati alla data
di riferimento del profilo entro la stagione, non al giorno di calendario.

**Stagioni e rose:** Le stagioni sono periodi con nome (es. 2026/27). Per
squadra e stagione esiste una rosa con validità per persona, numero di maglia,
ruolo e, negli sport di racchetta, l'ordine di forza mantenuto manualmente. Le
stagioni precedenti restano invariate. I **giocatori ospiti** di un club
partner compaiono nella rosa con il club di provenienza; sono persone di tipo
«ospite» senza assegnazione di quota, senza accesso e senza appartenenza a un
gruppo.

**Giornate:** Una giornata è un appuntamento dell'associazione con dati
sportivi: squadra, avversario (senza anagrafica cliente o utente),
competizione, casa/trasferta, luogo, orario di ritrovo e responsabile. La
squadra è il gruppo destinatario dell'appuntamento; si possono aggiungere altri
gruppi.

**Disponibilità e formazione:** I soci rispondono nel portale disponibile, non
disponibile o «forse» — una risposta non è una convocazione. La dirigenza
compone la formazione (campo/panchina con ruolo e numero; negli sport di
racchetta coppie di singolo e doppio) e la rilascia. Dimensioni della rosa e
ruoli vengono dal profilo. Una persona in singolo e doppio resta una persona.
Prima del rilascio vengono mostrati i conflitti: impiego contemporaneo in
un'altra formazione o rifiuto esplicito. Rilasciare nonostante i conflitti
richiede una motivazione e viene registrato. I convocati diventano partecipanti
dell'appuntamento; la presenza effettiva viene registrata a parte nel foglio
presenze.

**Ruoli dell'appuntamento:** Arbitro, cronometrista/giuria, trasporto, servizio
campo/spogliatoio o direttore di tiro vengono assegnati per appuntamento — a un
socio, a un utente del personale o come nome esterno. Un socio con un ruolo
conta come partecipazione associativa, non come posto in rosa.

**Risultato:** Il risultato viene inserito manualmente nel formato del profilo
(gol, punti per periodo con totale, set con punteggio). Marcatori e note vanno
nel commento. Le modifiche vengono registrate; nessuna classifica automatica da
risultati incompleti.

**Importazione calendario:** CSV o ICS producono una **lista di proposte** che
non crea nulla prima della conferma. La dirigenza verifica avversario, luogo e
orario e accetta o scarta ogni proposta; le righe note vengono saltate a una
nuova importazione, i possibili duplicati di giornate esistenti vengono
segnalati. Colonne CSV (riga di intestazione, ordine libero): data, ora, fine
facoltativa, avversario e casa/trasferta — oppure casa e ospite come nomi delle
squadre — più luogo e competizione. La sincronizzazione federale non è inclusa.

## Pacchetti iniziali per sport

Nella pagina **Sport** un **pacchetto iniziale** crea uno sport in un solo passo:
profilo sportivo, sezione, gruppi o squadre tipici e impianti, più, a seconda dello
sport, un sistema di gradi (arti marziali), cavalli da scuola (equitazione) o un
requisito di presenza (tiro). Sono inclusi undici sport: arti marziali, tennis
tavolo, hockey, equitazione, calcio, pallamano, pallacanestro, pallavolo, tennis,
atletica e tiro a segno. Un pacchetto è configurazione, non un caso speciale: tutto
ciò che crea può essere modificato o eliminato in seguito, le voci esistenti con lo
stesso nome restano intatte e non sono integrate regole federali né soglie legali.

Il settore dimostrativo **Associazione sportiva** installa tutti gli undici
pacchetti e li riempie con persone, appuntamenti, presenze, quote, giornate di gara,
competizioni, esami e lezioni di equitazione fittizi.
