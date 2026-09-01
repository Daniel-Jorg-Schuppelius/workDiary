---
title: "Règles de majoration"
topic: admin.surcharge-rules
version: 1
audience:
    - admin
    - buchhaltung
modules:
    - module.lohn
related:
    - exports.payroll
    - finance.transfers
    - admin.handbook
    - glossary.core
---

Les règles de majoration définissent les suppléments de nuit, de
week-end, de jour férié et de plages horaires personnalisées ; lors de
l'export des temps, les présences sont découpées en conséquence et
ventilées par type de salaire. Créez une règle avec code, libellé et
**type** (nuit, samedi, dimanche, jour férié ou personnalisé), puis
indiquez le **pourcentage** et, en option, le numéro de type de
salaire pour DATEV/Lexware, la validité, la priorité et l'état actif.
En cas de chevauchement, le **pourcentage le plus élevé** l'emporte —
sans cumul. Les modifications ne s'appliquent qu'aux exports futurs ;
seules les personnes explicitement autorisées peuvent gérer ces
règles.
