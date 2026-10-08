---
title: "Conflitti Lexoffice"
topic: admin.lexoffice
version: 2
keywords:
    - Lexware Office
    - conflitto di sincronizzazione
    - dati divergenti
    - risolvere conflitto
    - mantenere valori locali
    - accettare valori esterni
    - allineamento dati
audience:
    - admin
    - buchhaltung
related:
    - admin.plugins
    - articles.lexoffice
    - invoices.manage
---

Qui risolve i conflitti di sincronizzazione con Lexoffice, che nascono
quando un record locale e quello corrispondente in Lexoffice divergono
in uno o più campi. Per ogni conflitto scelga **Applica locale**
(mantiene i valori di WorkDiary), **Applica esterno** (adotta i valori
di Lexoffice) o **Scarta** (ignora il conflitto). Confronti con cura
i dati affiancati prima di decidere, perché le prime due opzioni
sovrascrivono valori; per le fatture la sovranità resta al programma
esterno.

La strategia di conflitto delle impostazioni Lexoffice vale per contatti e
articoli. I conflitti di articoli non compaiono in questa casella, ma
nell'elenco dei conflitti del magazzino (Magazzino → Conflitti): lì mantiene
lo stato locale, applica lo stato Lexoffice o ignora il conflitto — con il
permesso di gestione degli articoli.
