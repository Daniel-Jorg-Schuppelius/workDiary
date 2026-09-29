---
title: "Conformità alla legge sull'orario di lavoro (ArbZG)"
topic: reports.arbzg-compliance
version: 1
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.overview
    - reports.drilldown
---

Il report di conformità ArbZG confronta il **tempo di lavoro
effettivamente registrato** (presenze al netto delle pause) per
collaboratore e giorno con le soglie della legge tedesca sull'orario di
lavoro; è la vista a consuntivo, distinta dalla verifica del piano
turni. Vengono controllati: orario massimo giornaliero (standard 10 h),
riposo minimo tra due giornate (standard 11 h), pause obbligatorie
(30 min da 6 h, 45 min da 9 h) e orario massimo settimanale (avviso
oltre 48 h). Le soglie provengono dalle impostazioni di conformità
dell'organizzazione. Ogni voce rimanda alla **chiusura giornaliera** del
giorno interessato; le giornate con correzione approvata sono marcate
come **corrette** e la lista è esportabile in CSV o PDF.

**Orario notturno e minori:** L’orario notturno (predefinito dalle 23 alle 6,
nelle panetterie dalle 22 alle 5) si imposta nelle impostazioni di conformità;
la media secondo il § 3 non conta più i giorni festivi come lavorativi. Se per
il collaboratore è registrata una data di nascita, la valutazione controlla
anche i giorni precedenti al 18º compleanno secondo la legge tedesca sulla
tutela del lavoro minorile: al massimo 8 h al giorno e 40 h a settimana, pause
(30 min da 4,5 h, 60 min da 6 h), 12 h di riposo, nessun lavoro tra le 20 e le
6, al massimo 5 giorni lavorativi a settimana. Il lavoro nel fine settimana e
quello notturno dai 16 anni compaiono come avviso, perché la legge prevede
eccezioni per settore.

Il termine di registrazione MiLoG (sette giorni) si misura dalla registrazione
originale: per le timbrature, il momento della timbratura anche se un
dispositivo offline la trasmette più tardi; per le importazioni, la colonna
«erfasst am» (registrato il). Le importazioni senza questo dato non vengono
verificate rispetto al termine.
