---
title: "Formati di numerazione"
topic: admin.number-formats
version: 2
keywords:
    - serie numerica
    - numero fattura
    - numero cliente
    - codice articolo
    - numerazione progressiva
    - prefisso
    - numero iniziale
    - contatore annuale
    - azzerare contatore
    - numerazione dalla contabilità
    - numero cliente da Lexoffice
    - numero provvisorio
audience:
    - admin
    - teamleitung
related:
    - invoices.manage
    - admin.classifications
    - glossary.core
---

I formati di numerazione definiscono le serie progressive per
organizzazione, ad esempio per ticket, asset, articoli, ordini di
produzione, clienti, fornitori e fatture. Per ogni formato configura
prefisso e separatore, l'inclusione facoltativa dell'anno, il numero
di cifre di riempimento, il valore iniziale e l'azzeramento annuale
del contatore; un'anteprima mostra il prossimo numero generato.
Modifichi formato o valore iniziale con cautela, perché i numeri già
assegnati restano invariati.

## Chi assegna i numeri cliente e fornitore?

Nella colonna **Assegnato da** sceglie per **Cliente** e **Fornitore** se
il numero lo assegna WorkDiary o un programma di contabilità collegato.
Sono proposti i programmi attivi in grado di assegnare numeri: Lexoffice
(cliente e fornitore), sevDesk ed easybill (cliente). orgaMAX non assegna
numeri per WorkDiary.

Se un programma assegna i numeri:

- Un nuovo cliente riceve prima un numero provvisorio (`ENTWURF-…`). Se
  creato nel modulo, viene subito trasferito al programma e ne riceve il
  numero. Un'elaborazione oraria recupera i clienti da importazioni e i
  trasferimenti non riusciti.
- Il campo **Numero cliente** è bloccato per questi clienti.
- Se il numero del programma appartiene già a un altro cliente, resta il
  numero precedente e la **casella di abbinamento** mostra il caso. Dopo
  aver rinumerato l'altro cliente, la sincronizzazione successiva adotta il
  numero.
- I clienti esistenti mantengono il loro numero.
- Se la contabilità gestisce i dati anagrafici, WorkDiary non trasferisce
  nulla; il numero arriva non appena la sincronizzazione dei contatti
  collega il cliente.

Se il numero lo assegna WorkDiary, ogni trasferimento verifica se il numero
appartiene già a un altro contatto nel programma e in tal caso si interrompe
con un messaggio invece di sovrascrivere quel contatto.

I numeri di fattura, nota di credito e storno si impostano nelle
impostazioni del plugin Lexoffice.
