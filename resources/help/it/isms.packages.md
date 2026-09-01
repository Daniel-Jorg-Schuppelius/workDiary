---
title: "Pacchetti di audit e link per auditor"
topic: isms.packages
version: 1
audience: []
modules:
    - module.isms
related:
    - isms.audits
    - isms.conformity
    - isms.overview
    - glossary.core
---

I **pacchetti di audit** congelano lo stato dei dati ISMS a una data di
riferimento come snapshot, base solida per auditor esterni. Crea il pacchetto
come "Bozza" (titolo, data di riferimento, ambito, eventuale norma come
filtro), poi **finalizza**: viene generato lo snapshot JSON con hash SHA-256
e registrato chi ha finalizzato e quando; l'integrità è verificabile in ogni
momento contro l'hash salvato. Un **link per auditor** apre una **vista web in sola
lettura** del pacchetto finalizzato (hash SHA-256 in copertina, file JSON
scaricabile dalla vista) — sempre lo stato **congelato** alla
finalizzazione, mai i registri correnti; a tempo limitato (1–90 giorni),
revocabile in qualsiasi momento e **mostrato
per intero una sola volta** alla creazione. I pacchetti finalizzati sono
immutabili e lo stato dei dati corrisponde al momento della finalizzazione,
non a una ricostruzione retroattiva. Creazione e gestione richiedono diritti
di gestione ISMS; il download dell'auditor avviene senza account WorkDiary.
