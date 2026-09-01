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
