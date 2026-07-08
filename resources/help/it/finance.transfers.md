---
title: "Trasferimento fatturazione"
topic: finance.transfers
version: 1
audience: []
related:
    - exports.payroll
    - admin.surcharge-rules
    - roles.buchhaltung
    - glossary.core
---

Il trasferimento di fatturazione invia **tempi** e **materiali** fatturabili
al sistema di fatturazione principale: la fattura nasce nel programma esterno
(DATEV o Lexoffice), WorkDiary fornisce solo posizioni verificate con prova
di trasferimento. Flusso: creare il trasferimento come "Bozza" scegliendo
canale ("Prestazioni/Tempo" o "Prodotti/Materiale") e destinazione
(Lexoffice, DATEV o export CSV), verificare le posizioni, **confermare** e
**eseguire**. Lo stato **"Trasferito" è definitivo**: le posizioni incluse
sono bloccate e le correzioni avvengono solo tramite trasferimenti di storno
o differenza. Trasferimenti di tempo e materiale hanno permessi separati.
