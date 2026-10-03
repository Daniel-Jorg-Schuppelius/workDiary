---
title: "Inbox di riconciliazione"
topic: admin.integration-inbox
version: 1
audience: []
related:
    - admin.integrations
    - admin.import
    - contacts.manage
    - finance.open-times
---

L'inbox di riconciliazione raccoglie **le importazioni ricevute che non è
stato possibile associare automaticamente** – da sistemi collegati,
dall'importazione CSV e dalla ricezione e-mail. Nulla viene creato alla
cieca: decide lei per ogni voce.

**Tre casi:**

- **Non associato** – non esiste un record corrispondente a quello in arrivo.
- **Ambiguo** – sono possibili più record.
- **Conflitto di campo** – il record è noto, ma lo stato locale e quello
  remoto si contraddicono. I due stati sono affiancati.

**Decidere:** associa una voce a un record esistente, la crea come nuova o la
scarta. In caso di conflitto di campo sceglie se mantenere lo stato locale o
applicare quello remoto. La decisione resta visibile sulla voce (Associato,
Creato, Mantieni locale, Remoto applicato, Scartato); con il filtro di stato
richiama le voci già gestite.

**Gruppi:** le voci collegate compaiono in alto come gruppo e si decidono in
un solo passaggio:

- **Tempi importati** di un progetto sconosciuto: scegliere o indicare il
  cliente, eventualmente il cliente finale, e il progetto, poi registrare il
  gruppo.
- **Dispositivi sconosciuti** dall'assistenza remota: collegarli a un
  dispositivo e registrare.
- **Numeri di telefono sconosciuti:** assegnarli a un cliente; «Memorizzare
  il numero in modo permanente» vale per le chiamate future, un numero
  condiviso solo per questa.
- **Utenti sconosciuti** di un'importazione tempi: assegnarli a un utente.
- **Appuntamenti ricorrenti** dai calendari: creare tutti come appuntamenti.
- **Ordini** dal catalogo B2B: registrare come incarico.

«Mostra voci» apre il contenuto di un gruppo. Un gruppo può anche essere
scartato per intero.

**Filtri:** le schede separano per origine; il numero indica le voci aperte.
Può inoltre filtrare per stato, caso ed entità. Gli elenchi di scelta molto
lunghi sono limitati a 1000 voci – il campo di ricerca in alto a destra
restringe la selezione.

**Gestisci assegnazioni:** WorkDiary ricorda un'assegnazione effettuata; le
importazioni successive dello stesso record passano senza domande. In
«Gestisci assegnazioni» consulta e rimuove questi collegamenti.

La pagina è aperta a chi è autorizzato a gestire la fatturazione.
