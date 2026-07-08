---
title: "Ruoli e permessi"
topic: admin.roles
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.security
    - roles.admin
---

La gestione dei permessi si articola in **Permessi** (catalogo in sola
lettura nello schema `risorsa.azione`), **Ruoli** (insiemi di permessi
per organizzazione), **Gruppi** (raggruppamento solo visivo) e
**Membri** (assegnazione dei ruoli). Flusso tipico: crea o copia un
ruolo, ritaglia i permessi, assegnalo ai membri e verifica con un
account di prova. Attenzione: un ruolo senza organizzazione agisce su
tutta la piattaforma ed è riservato al gestore — non va mai assegnato
tramite permessi delegabili. Applica il principio del minimo
privilegio; nei moduli sensibili non esiste bypass per gli admin.
