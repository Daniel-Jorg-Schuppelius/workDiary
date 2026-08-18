---
title: "Sincronizzazione offline"
topic: admin.offline-sync
version: 1
audience: []
related:
    - admin.metrics
---

Chi lavora in giro senza rete registra in una **outbox del dispositivo**;
appena torna la connessione, il dispositivo trasmette i comandi. Questa pagina
mostra **ogni comando trasmesso con il suo esito** — la risposta alla domanda
quali dati siano nati offline e se siano arrivati.

## I quattro esiti

- **Applicato** — il comando è nei dati. Il caso normale.
- **Duplicato** — lo stesso dispositivo ha inviato due volte lo stesso comando
  (tipico dopo un’interruzione a metà trasmissione). Non è un errore: il
  comando è stato applicato la prima volta, la ripetizione riconosciuta e
  scartata.
- **Conflitto** — i dati sono cambiati nel frattempo; il comando **non** è
  stato applicato.
- **Respinto** — il comando era invalido (ad esempio una timbratura in uno
  stato non ammesso); la colonna degli errori ne indica il motivo.

**Conflitto e Respinto sono la ragione di questa pagina:** quelle
registrazioni *non* sono arrivate nei dati. I contatori del filtro esiti
contano sempre l’intero insieme — un filtro impostato non li nasconde.

## I due orari

**Rilevato (offline)** è l’ora del dispositivo, **Trasmesso** l’arrivo sul
server. La distanza fra i due è la latenza offline — un giorno è normale in
esterno, una settimana segnala un dispositivo che non sincronizza.
