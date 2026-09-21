---
title: "Integrazione OpenProject"
topic: admin.openproject
version: 1
audience:
    - admin
related:
    - admin.plugins
    - admin.toggl
    - admin.import
---

L'integrazione OpenProject collega WorkDiary in modo bidirezionale: il
sync della struttura importa progetti, work package e utenti, il sync
dei tempi importa le registrazioni e il push riscrive in OpenProject i
tempi rilevati in WorkDiary. I progetti senza assegnazione automatica
finiscono nella posta in arrivo, dove li assegna a un progetto
esistente, ne crea uno nuovo o li scarta; le assegnazioni salvate
valgono per gli import futuri. Per il push serve una
**attività predefinita** nel plugin, altrimenti fallisce. Verifichi
mapping e attività predefinita prima del primo push, perché la
riscrittura modifica dati nel sistema OpenProject collegato.
