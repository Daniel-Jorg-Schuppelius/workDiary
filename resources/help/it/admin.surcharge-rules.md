---
title: "Regole di maggiorazione"
topic: admin.surcharge-rules
version: 1
audience:
    - admin
    - buchhaltung
related:
    - exports.payroll
    - finance.transfers
    - admin.handbook
    - glossary.core
---

Le regole di maggiorazione definiscono supplementi notturni, per
weekend, festivi e per fasce orarie personalizzate; nell'export delle
presenze i tempi vengono suddivisi di conseguenza e riportati per tipo
di retribuzione. Crea una regola con codice, denominazione e tipo
(«Notte», «Sabato», «Domenica», «Festivo» o «Personalizzato»),
imposta la **percentuale** (0–999,99 %), opzionalmente il numero del
tipo di retribuzione per DATEV/Lexware, validità, priorità e stato
attivo. In caso di sovrapposizione vince la percentuale più alta (non
si somma); le modifiche valgono solo per gli export futuri.
