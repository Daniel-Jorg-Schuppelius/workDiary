---
title: "Cronologia modifiche e confronto versioni"
topic: admin.audit-diff
version: 1
audience: [admin]
related:
    - audit.log
---

La cronologia delle modifiche rende leggibile la catena di audit: per un
record selezionato (membro, cliente, modello orario, tipo di turno,
conto del tempo, organizzazione) la timeline mostra tutte le modifiche
registrate con momento, evento e utente.

Selezionare due stati (A = più vecchio, B = più recente) e confrontare:
la tabella delle differenze mostra per campo il valore prima dello stato
A e dopo lo stato B — in pochi secondi è chiaro da quando esiste un
valore e chi lo ha modificato.

I campi sensibili (password, segreti, numeri fiscali e previdenziali)
sono mascherati. Il confronto è volutamente di sola visualizzazione: le
correzioni restano operazioni verificate — nessun rollback automatico.
