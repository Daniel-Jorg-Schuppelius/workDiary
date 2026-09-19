---
title: "Job pianificati"
topic: admin.scheduler
version: 1
audience:
    - admin
related:
    - admin.diagnostics
    - admin.operations
---

Questa pagina mostra tutti i job ricorrenti in background della
piattaforma – dall'housekeeping alla sincronizzazione delle
integrazioni fino alle escalation delle scadenze.

**Registry invece di proliferazione:** tutti i job pianificabili
provengono da una registry centrale con un **piano predefinito**
fisso. Solo i job registrati lì compaiono qui e possono essere
gestiti – comandi arbitrari non possono essere pianificati da questa
pagina, per scelta deliberata.

**Panoramica:** per ogni job sono visibili il piano effettivo con la
sua **origine** (predefinito, impostazione o ripianificazione
manuale), l'ultima esecuzione con il relativo esito, un contatore
degli errori e la prossima scadenza. Così si riconosce a colpo
d'occhio se un job è bloccato o fallisce in modo persistente.

**Filtrare e ordinare:** sopra la tabella è possibile restringere
l'elenco per nome, chiave o comando, per criticità, ultimo esito e
origine del piano – ad esempio a tutti i job non riusciti, mai eseguiti
o spostati dalla finestra operativa. L'interruttore **Solo sospesi**
mostra ciò che è attualmente fermo. Le colonne Job, Ultima esecuzione,
Prossima scadenza ed Errori consecutivi si ordinano con un clic; i job
senza valore (mai eseguiti, sospesi) restano sempre in fondo.

**Ripianificare con binari di sicurezza:** ogni job definisce quali
cadenze sono ammesse per esso (ad es. ogni ora oppure ogni giorno a un
orario preciso). La ripianificazione è possibile solo entro queste
cadenze ammesse – un job critico non può così essere impostato per
errore su un ritmo inadeguato. Le espressioni cron libere restano
riservate al gestore. Tramite **Ripristina** un job torna in qualsiasi
momento al suo piano predefinito.

**Finestra operativa:** se il server non funziona 24 ore su 24 – ad
esempio perché si spegne di notte –, imposta in **Impostazioni**
l'inizio e la fine della finestra operativa (una fine alle 00:00
indica la mezzanotte). I job a orario fisso al di fuori della finestra
vengono quindi eseguiti insieme nelle prime due ore di funzionamento,
nel loro ordine originale e mai prima del previsto. La panoramica
mostra l'orario effettivo e indica da quale orario un job è stato
spostato; il watchdog verifica rispetto a quell'orario. Le cadenze
orarie e più brevi restano invariate.

**Pausa ed esecuzione di prova:** i job possono essere messi in pausa
e ripresi in seguito – un job in pausa non va più in scadenza, ma
resta visibile nella panoramica. Un'**esecuzione di prova** avvia il
job immediatamente fuori programma; tra due esecuzioni di prova vale
un breve intervallo di blocco, affinché le esecuzioni non si
sovrappongano.

**Registri di esecuzione:** ogni esecuzione viene protocollata con
inizio, durata ed esito. I registri vengono conservati per un periodo
impostabile (per impostazione predefinita 30 giorni) e poi ripuliti
automaticamente.

**Watchdog:** un apposito job di sorveglianza controlla lo scheduler
stesso: se le esecuzioni dovute non avvengono o gli errori si
accumulano, ne nascono attività operative o avvisi. Così anche uno
scheduler completamente fermo viene notato – non solo quando mancano
le analisi.

**Raccomandazione:** modificare i piani con moderazione e osservare le
esecuzioni successive dopo ogni ripianificazione. Un contatore degli
errori stabilmente elevato è un caso per la diagnostica, non per la
pausa.
