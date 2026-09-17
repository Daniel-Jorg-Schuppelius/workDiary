---
title: "Gestire i documenti"
topic: documents.manage
version: 1
audience: []
modules:
    - module.documents
related:
    - forms.fill
    - knowledge.articles
    - glossary.core
---

Il modulo documenti gestisce contratti, certificati, rapporti di verifica
e manuali come **file versionati** con metadati, validità e riferimento a
cliente, progetto, incarico o asset. Carica un documento con titolo, tipo,
validità e oggetto di riferimento (diventa la versione 1); in caso di
modifiche carica una **nuova versione** — le versioni precedenti restano
immutate e scaricabili, le correzioni avvengono sempre tramite una nuova
versione. Gli stati sono «Bozza», «Attivo», «Archiviato»; **«Scaduto»**
viene calcolato automaticamente dalla data di fine validità e i documenti
in scadenza possono generare notifiche. Attenzione: **l'eliminazione
rimuove il documento con tutte le versioni** (soft delete, solo con il
relativo permesso).

## Inviare documenti

I documenti — fatture, preventivi, bolle di consegna — possono essere inviati
direttamente dalla pratica. Ogni invio viene registrato con destinatario,
momento e canale, così resta ricostruibile **che cosa è andato a chi e quando**.

La **cronologia degli invii** appartiene al documento, non alla casella di
posta: anche chi non ha accesso all'account di posta vede se e quando è stato
inviato. Un nuovo invio aggiunge una voce invece di sovrascrivere la precedente.
