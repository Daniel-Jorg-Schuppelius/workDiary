---
title: "Conti del tempo"
topic: time-accounts.overview
version: 2
keywords:
    - conto aggiuntivo
    - conto tempo libero
    - riposo compensativo
    - contatore turni notturni
    - ore di indennità
    - saldo del conto
    - semaforo
    - giornale delle registrazioni
    - storno
    - esportare i conti
    - lavoro extra
    - confronto periodi
audience: []
related:
    - time-accounts.flex
---

I conti del tempo aggiuntivi tracciano grandezze temporali selezionate
come conti dedicati — ad esempio un contatore dei turni notturni svolti,
un conto di tempo libero per il lavoro extra o ore di indennità
accumulate. Orario flessibile e ferie restano nel conto dell'orario di
lavoro.

La panoramica mostra per conto il saldo attuale con semaforo (le soglie le
definisce l'organizzazione), la media mensile e una tendenza semplice.
«Vedi giornale» mostra ogni registrazione con data, quantità, fonte e
nota — le correzioni appaiono come storni, nulla viene sovrascritto.

La valutazione (per i ruoli direttivi) confronta saldo iniziale, movimento
e saldo finale per dipendente in un periodo e si esporta come CSV o PDF.

## Confronto periodi

Il confronto periodi affianca le registrazioni di un conto del tempo per
settimana di calendario o per mese. Lo trova in **Report** → **Team** →
**Confronto periodi**.

- Nella barra dei filtri sceglie il **Conto** (tutti i conti del tempo attivi)
  e la **Granularità**: **Settimana di calendario** (predefinita) o **Mese**.
  La scelta ha effetto immediato.
- Il periodo segue il filtro data nell'intestazione. Vengono mostrate al
  massimo 53 colonne, cioè un anno in settimane.
- Per ogni dipendente la tabella mostra il **Saldo iniziale** (somma di tutte
  le registrazioni precedenti al periodo), la somma per settimana o per mese,
  il **Movimento** nel periodo e il **Saldo finale**. Il saldo finale riporta il colore del semaforo del conto. Tutti
  i valori sono espressi nell'unità del conto.
- Le persone con saldo iniziale e movimento entrambi pari a zero non
  compaiono. Se nel periodo non ci sono valori, la pagina indica «Nessuna
  registrazione nel periodo selezionato.»

**Esportazione:** **PDF** e, sotto **Esportazione**, i formati **CSV** ed
**Excel**. PDF e CSV contengono il conto scelto. Excel fornisce tutti i conti
attivi, ciascuno in un foglio della stessa cartella di lavoro.

**Visibilità:** il ruolo **Amministratore** vede tutti i dipendenti
dell'organizzazione, tutte le altre persone vedono solo la propria riga. Se
non sono configurati conti del tempo attivi, la pagina mostra l'indicazione
«Nessun conto del tempo configurato».
