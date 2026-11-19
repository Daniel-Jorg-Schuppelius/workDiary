---
title: "Abbonamento ed elenco abbonamenti"
topic: finance.resale.subscription
version: 1
keywords:
    - pagina abbonamento
    - elenco abbonamenti
    - cedere licenze
    - cessione
    - cambio titolare
    - assegnazione rapida
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.board
    - finance.resale.periods
---

La **pagina abbonamento** mostra dati anagrafici, titolare, prezzi e i periodi
di un abbonamento; da qui si assegnano righe di fattura nell'assegnazione
rapida (quantità in licenze del periodo), si cedono licenze o si passa
l'abbonamento a un nuovo titolare con «Subentro». L'**elenco abbonamenti**
filtra per titolare, fornitore e stato, cerca per numero fattura e assegna un
titolare a più abbonamenti in un passaggio; i domini in un conto sub-utente
indicano il loro sub-utente, perché il portale domini li fattura.

**Cedere licenze:** se due aziende condividono la sede e la seconda usa una
parte delle licenze di un contratto, ceda quelle licenze sul contratto
(«Cedere licenze»: titolare, quantità, periodo, prezzo di vendita). Nasce un
abbonamento separato per l’altro titolare con periodi propri; il contratto
pianifica i suoi periodi con il resto. Ogni titolare riceve le proprie
fatture assegnate. Se un successore sostituisce il contratto (import), la
cessione prosegue lì. Un contratto con cessioni si elimina solo dopo aver
rimosso le cessioni. Anche un cambio di titolare nel tempo — un’azienda si
scinde e la nuova rileva i contratti — è una cessione: tutte le licenze del
vecchio periodo al titolare precedente; la riconciliazione lo propone su una
fattura dell’altro cliente come «Cedere il periodo a …», precompilato.

**Concatenare un successore:** Se un abbonamento termina e alla fine inizia un
abbonamento dello stesso titolare e prodotto (ad esempio dopo un cambio di
fornitore), l'abbonamento terminato lo propone come successore nei dati di
base. Concatenati condividono lo storico e l'assegnazione del modello
ricorrente. La panoramica li elenca sotto «Abbonamenti terminati senza
successore». La catena può essere rimossa.
