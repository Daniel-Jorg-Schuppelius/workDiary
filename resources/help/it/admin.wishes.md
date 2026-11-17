---
title: "Desideri delle organizzazioni"
topic: admin.wishes
version: 1
keywords:
    - vista piattaforma desideri
    - raggruppare desideri
    - canale del produttore
    - desiderio al produttore
    - risposta della gestione
audience:
    - admin
related:
    - wishes.overview
    - admin.settings
---

La vista piattaforma mostra tutti i desideri che le organizzazioni hanno
inoltrato alla gestione – solo per gli operatori della piattaforma. Si vedono il
testo ripulito, il tipo, la pagina, la versione dell’app, il numero di voti e
l’organizzazione. Chi ha scritto o approvato il desiderio e il testo originale
restano nell’organizzazione.

## Raggruppare, stato e risposta

- **Raggruppare:** assegni desideri simili a un desiderio principale dello
  stesso tipo. L’elenco mostra solo i desideri principali; i voti del
  raggruppamento si sommano.
- **Stato e risposta della gestione** si impostano sul desiderio principale.
  Valgono per l’intero raggruppamento e compaiono nel desiderio di ogni
  organizzazione come «Risposta della gestione della piattaforma»; autori e
  votanti ricevono un avviso.

## Canale del produttore (installazione autogestita)

Se il produttore gestisce l’installazione, questa vista è già il produttore. In
un’installazione autogestita può inviare singoli desideri al produttore via
e-mail – solo se attiva il canale nelle **impostazioni di sistema** con
`wishes.vendor_channel` (disattivato per impostazione predefinita; senza di esso
nessun desiderio lascia l’installazione). L’indirizzo del produttore si trova in
`wishes.vendor_address`; se è vuoto vale l’indirizzo di contatto registrato.

1. Se necessario, adatti il **testo per il produttore** e salvi.
2. Controlli l’**anteprima**: destinatario, indirizzo di risposta, oggetto ed
   esattamente il testo che viene inviato – codice, tipo, testo, pagina,
   versione dell’app, numero di voti e lei come mittente. L’organizzazione e i
   nomi non vengono mai inclusi.
3. **Inviare al produttore** invia esattamente questa e-mail. Se nel frattempo
   qualcosa è cambiato (ad esempio il numero di voti), l’app chiede una nuova
   anteprima.

La risposta del produttore le arriva via e-mail. La inserisca in **Risposta del
produttore**; l’organizzazione la vede nel desiderio.
