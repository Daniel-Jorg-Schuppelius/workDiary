---
title: "Matrice delle regole fiscali"
topic: finance.tax-rules
version: 1
audience:
    - admin
modules:
    - module.finance
related:
    - invoices.manage
---

La matrice delle regole fiscali è il catalogo versionato da cui la
fatturazione locale determina le proprie aliquote. WorkDiary fornisce
un catalogo di base; le righe proprie dell'organizzazione lo
sovrascrivono — il catalogo fornito resta di per sé invariato.

**Struttura:** ogni regola vale per un paese (facoltativamente una
regione), una categoria (services, goods, shipping, materials,
expenses, construction, media, other) e un tipo di aliquota (standard,
reduced, zero, exempt, reverse_charge, export) — con percentuale,
valido dal/al, indicazione della fonte e nota.

**Logica della data di riferimento:** determinante è la data della
prestazione, non la data della fattura. Viene applicata la regola
attiva più recente valida alla data di riferimento; le righe
dell'organizzazione hanno la precedenza sul catalogo. Se per una
categoria non esiste nulla di specifico, si applica come ripiego la
categoria dei servizi.

**Avvisi:** alla creazione e all'importazione, il controllo delle
sovrapposizioni impedisce che due regole attive dello stesso ambito di
validità si sovrappongano temporalmente. La panoramica avvisa inoltre
delle lacune nelle catene di regole attive — periodi per i quali non
vale alcuna regola.

**Import CSV:** file separato da punto e virgola con le colonne
country, category, rate_type, rate, valid_from, valid_to, source, note
(riga di intestazione ammessa). Le righe con categoria/tipo di aliquota
sconosciuti o con sovrapposizioni vengono segnalate e saltate, il resto
viene importato.

**Disattivare invece di eliminare:** le regole non vengono mai
eliminate, bensì disattivate — dopodiché tornano ad applicarsi il
catalogo o le regole più vecchie. Creazione e disattivazione sono
registrate nell'audit trail; solo le righe proprie dell'organizzazione
possono essere disattivate.

**Congelamento all'emissione:** all'emissione di una fattura, il
contesto fiscale effettivamente utilizzato (aliquota, fonte della
regola, data di riferimento, categoria, dettaglio delle imposte) viene
congelato sul documento. Le successive modifiche delle regole hanno
quindi effetto solo sui nuovi documenti, mai su quelli già emessi.

**Casi speciali con precedenza:** l'impostazione di piccolo
imprenditore (§ 19 UStG) disattiva completamente l'esposizione
dell'imposta. Un'aliquota fiscale predefinita fissa dell'organizzazione
prevale sulla matrice per le operazioni nazionali. I clienti UE con
partita IVA formalmente valida ricevono automaticamente il reverse
charge (0 %), i clienti di paesi terzi la dicitura di esportazione
(0 %) — le righe corrispondenti della matrice forniscono a tal fine il
testo informativo sul documento.
