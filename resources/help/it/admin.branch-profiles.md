---
title: "Profili di settore"
topic: admin.branch-profiles
version: 2
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
