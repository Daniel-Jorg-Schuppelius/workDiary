---
title: "Configurare il portale di segnalazione"
topic: whistleblowing.portal
version: 1
audience:
    - admin
modules:
    - module.compliance
related:
    - whistleblowing.cases
    - whistleblowing.report
    - admin.security
    - privacy.overview
---

Qui configuri il portale pubblico di segnalazione della tua organizzazione
(`/compliance/portal`); esiste esattamente un portale per organizzazione e
la gestione richiede il permesso **whistleblowing.settings.manage** più
l'autenticazione a due fattori del canale di segnalazione. Le impostazioni
comprendono **Attivo (`is_enabled`)**, l'ammissione di segnalazioni
**anonime** e **riservate**, il **testo introduttivo**, la **lingua
predefinita** e la **conservazione (mesi)** per la cancellazione controllata
dei casi chiusi. Il link pubblico contiene uno slug casuale non deducibile
dal nome dell'organizzazione; con **Ruota link** ne generi uno nuovo.
Attenzione: dopo la rotazione i link già distribuiti diventano subito non
validi — comunica attivamente il nuovo link.
