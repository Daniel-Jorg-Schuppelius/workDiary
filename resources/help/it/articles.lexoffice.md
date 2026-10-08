---
title: "Prodotti e servizi Lexoffice"
topic: articles.lexoffice
version: 2
keywords:
    - Lexware Office
    - articoli Lexoffice
    - sincronizzare articoli
    - catalogo prodotti
    - listino prezzi
    - conflitto di sincronizzazione
    - importazione articoli
    - servizi
    - aliquota IVA
audience: []
modules:
    - module.vertrieb
related:
    - articles.master
    - invoices.manage
    - glossary.core
---

Questa pagina mostra l'elenco di prodotti e servizi sincronizzato da
Lexoffice, in sola lettura: la gestione avviene in Lexoffice, mentre un
pull-sync mantiene aggiornata la cache locale. Per ogni voce vede
denominazione, numero articolo, tipo, unità, prezzo unitario netto e
aliquota IVA; può cercare, filtrare per tipo e stato e ordinare. Con i
permessi adeguati può avviare manualmente la sincronizzazione, che
riporta quante voci sono state create, aggiornate o archiviate;
Lexoffice deve essere configurato per l'organizzazione.

La strategia di conflitto delle impostazioni Lexoffice (Lexoffice vince,
locale vince, verifica manuale) vale anche per la sincronizzazione degli
articoli: con «verifica manuale», gli articoli modificati localmente il cui
stato differisce in Lexoffice compaiono come conflitti nell'elenco dei
conflitti del magazzino (Magazzino → Conflitti). Lì decide per ogni
articolo se mantenere lo stato locale, applicare lo stato Lexoffice o
ignorare il conflitto. La sincronizzazione manuale riporta il numero di
nuovi conflitti.
