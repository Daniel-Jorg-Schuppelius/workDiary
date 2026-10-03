---
title: "Profili di settore"
topic: admin.branch-profiles
version: 3
audience:
    - admin
related:
    - admin.handbook
    - admin.import
---

I profili di settore installano in un solo passaggio un pacchetto
curato di modelli per il proprio mestiere: tipi di commessa, categorie,
regole obbligatorie, liste di controllo, requisiti dei locali, tag
standard e altro. Nel catalogo cerchi il mestiere adatto, controlli
l'**anteprima dei contenuti** sulla scheda e scelga **Installa**.
L'installazione è **idempotente**: ripeterla non crea duplicati e non
sovrascrive dati personalizzati; **Riapplica** riporta i modelli
importati allo stato del profilo, ma le liste di controllo pubblicate
non vengono mai sovrascritte. Ogni installazione è registrata in modo
verificabile e nuovi mestieri possono essere aggiunti senza modifiche
al codice.

I profili possono essere **combinati**; il primo installato è il
**profilo principale**, che determina il focus di navigazione e le
impostazioni predefinite, mentre la raccomandazione dei moduli riunisce
tutti i profili installati («Imposta come profilo principale» lo cambia).
**Disinstalla** (amministratore della piattaforma) rimuove tipi di
incarico, categorie e tag inutilizzati, disattiva le classificazioni in
uso ed elimina le regole obbligatorie del profilo; i modelli restano. Un
avviso di aggiornamento sulla scheda segnala una versione più recente;
**Importa** accetta un profilo JSON dal catalogo. I tipi di incarico
vengono mostrati nella lingua dell'utente.

**Varianti specifiche del cliente:** una variante si sovrappone a un profilo
di settore – riprende il profilo base, omette elementi e ne aggiunge di
propri, senza modificare il profilo. «Crea variante» richiede profilo base,
codice (minuscole, cifre e trattini) e denominazione. In «Omettere elementi»
spunta ciò che non deve essere creato all'installazione; le voci già presenti
non vengono toccate. Le «Aggiunte» sono un estratto di profilo in formato
JSON, strutturato come un profilo di settore; gli elementi con lo stesso nome
sostituiscono quelli del profilo base. **Installa** applica la variante,
«Aggiorna le voci esistenti» allinea le voci già installate; ogni salvataggio
incrementa la versione. «Esporta come JSON» trasmette la variante;
eliminandola, le voci installate restano.
