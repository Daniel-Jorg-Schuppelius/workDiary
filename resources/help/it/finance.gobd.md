---
title: "Esportazione GoBD (consegna dei supporti dati)"
topic: finance.gobd
version: 1
audience:
    - admin
modules:
    - module.finance
related:
    - invoices.manage
    - audit.log
---

Per le verifiche fiscali WorkDiary genera la consegna dei supporti dati
secondo la modalità di accesso Z3: un pacchetto di verifica nello
standard descrittivo GDPdU, che il revisore può importare direttamente
nel proprio software di analisi.

**Contenuto del pacchetto:** il pacchetto è un archivio ZIP con un file
index.xml che descrive in forma leggibile dalla macchina tabelle, campi
e formati, oltre a file di dati CSV separati da punto e virgola. Le
aree di dati sono selezionabili singolarmente: fatture emesse,
posizioni di fattura, anagrafica debitori e attestati dei tempi del
periodo di verifica.

**Periodo e verifica preliminare:** per impostazione predefinita è
preimpostato l'anno precedente come periodo di verifica; le date da/a
sono liberamente selezionabili. Prima dell'esportazione, una verifica
preliminare mostra il numero di record per area e avvisa in caso di
anomalie — ad esempio quando nel periodo sono ancora presenti fatture
in bozza o non viene trovata alcuna fattura.

**Set di caratteri:** i file CSV vengono generati a scelta in CP1252
(«ANSI», predefinito e la via più sicura lato revisore), ISO-8859-15 o
UTF-8; il file descrittivo indica il set di caratteri scelto.

**Hash riproducibile:** tutti i dati vengono ordinati e formattati in
modo deterministico. L'hash del pacchetto viene calcolato sui contenuti
dei file (non sul file binario ZIP, che contiene timestamp) — lo stesso
periodo con le stesse aree e lo stesso set di caratteri produce quindi
in modo riproducibile lo stesso hash. Inoltre, per ogni file viene
documentato un hash proprio. In questo modo è possibile dimostrare in
seguito, senza alcun dubbio, che un pacchetto consegnato è rimasto
invariato.

**Elenco delle evidenze di esportazione:** ogni esportazione crea
automaticamente un'evidenza a prova di revisione: chi ha esportato
quando quale periodo con quali aree, inclusi gli hash del pacchetto e
dei file nonché il numero di record. Le ultime esportazioni sono
visibili direttamente sulla pagina; la cronologia completa resta
conservata in modo permanente e integra il log di audit.

L'esportazione legge esclusivamente dati esistenti — non modifica né
documenti né dati anagrafici e può essere ripetuta un numero
illimitato di volte.
