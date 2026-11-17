---
title: "Conflitti Lexoffice"
topic: admin.lexoffice
version: 9
keywords:
    - Lexware Office
    - conflitto di sincronizzazione
    - dati divergenti
    - risolvere conflitto
    - mantenere valori locali
    - accettare valori esterni
    - allineamento dati
    - conflitto sync
    - specchio documenti
    - sync documenti
audience:
    - admin
    - buchhaltung
related:
    - admin.plugins
    - articles.lexoffice
    - invoices.manage
    - admin.integration-inbox
    - inventory.conflicts
---

Qui risolve i conflitti di sincronizzazione con Lexoffice. Un conflitto
si verifica quando un record locale (WorkDiary) e il contatto Lexoffice
corrispondente divergono in uno o più campi e nelle impostazioni di
Lexoffice la **Strategia di conflitto** è impostata su **Verifica
manuale** (valore predefinito). I conflitti si gestiscono nella **Inbox
di riconciliazione**: aprendo questa pagina vi accede già filtrato
sull'origine **Lexoffice** e sul caso **Conflitto di campo**.

Nella Inbox di riconciliazione:

- Per ogni conflitto i campi divergenti sono affiancati come
  **Locale** e **Remoto**.
- Sono interessati clienti e fornitori, cioè i contatti provenienti da
  Lexoffice.
- Con il filtro di stato può richiamare anche i conflitti già evasi.

Soluzioni per ciascun conflitto:

- **Adotta il remoto**: aggiorna il record locale con i valori di
  Lexoffice dei campi divergenti.
- **Mantieni locale**: conserva i valori locali; i valori divergenti di
  Lexoffice non vengono acquisiti.
- **Scarta**: chiude il conflitto senza modifiche (ad es. in caso di
  dati volutamente diversi); riceve lo stato **Scartato**.

Rischi: **Adotta il remoto** sovrascrive valori locali. Verifichi con
attenzione i dati messi a confronto prima di decidere. Tenga presente
che per le fatture la titolarità della fatturazione spetta al programma
esterno – WorkDiary vi trasmette i dati.

La strategia di conflitto vale per i contatti. Gli articoli li gestisce
Lexoffice: la sincronizzazione applica ogni versione più recente di
Lexoffice, quindi non esistono conflitti di articoli.

**Indirizzi con «Vince Lexoffice»:** Lexoffice gestisce gli indirizzi senza un
identificativo proprio; WorkDiary li abbina per tipo e ordine. Un indirizzo di
fatturazione o di consegna creato in WorkDiary riprende alla prima
sincronizzazione l’indirizzo Lexoffice corrispondente invece di essere creato
una seconda volta: l’indirizzo di fatturazione principale riprende il primo di
Lexoffice. Dopo, solo quello è principale; gli altri indirizzi di WorkDiary
restano come aggiuntivi.

**Tipo d'imposta al trasferimento:** fatture e bozze di fattura della
consegna fatturazione portano il tipo d'imposta del loro caso fiscale:
prestazione a un'impresa UE come «prestazioni esterne § 13b UE», § 13b
nazionale come «lavori edili § 13b», cessione intracomunitaria, cessione
all'esportazione, prestazione non imponibile in Germania come «servizio verso
paese terzo», piccola impresa come «esente», con lo 0 % per posizione e la
dicitura come nota fiscale. Tranne «esente», Lexoffice accetta questi tipi
d'imposta solo con un contatto creato lì. Se una prestazione non imponibile
si trova nell'UE (ad esempio un immobile in un altro paese UE), Lexoffice non
ha un tipo d'imposta adatto: il trasferimento viene bloccato con il motivo. Il
giustificativo dai tempi sul cliente trasferisce solo tempi imponibili; gli
altri casi passano per la consegna fatturazione.

Specchio documenti: il sync dei documenti rispecchia i documenti di
Lexoffice in WorkDiary. Vengono rispecchiati anche i documenti di contatti
Lexoffice non ancora collegati ad alcun cliente o fornitore – inizialmente
senza cliente né fornitore; non appena il contatto è collegato, il sync
successivo li assegna. Un cliente viene impostato solo sui documenti di
vendita e un fornitore solo sui documenti di acquisto, anche se un contatto
è collegato a entrambi. Il sync delle righe memorizza inoltre l'importo
netto e, per una nota di credito, il suo riferimento alla fattura.
L'esecuzione si avvia manualmente nelle **impostazioni Lexoffice** (Plugin →
Lexoffice) con **Sincronizza documenti adesso**: recupera in background i
documenti nuovi e modificati, il sync completo giornaliero resta attivo; il
pulsante richiede il permesso **Sincronizza i giustificativi con Lexoffice** e credenziali salvate.

Autorizzazione: la Inbox di riconciliazione è accessibile agli
amministratori e al ruolo **Contabilità**.

**Titolarità dei dati:** se, secondo la titolarità dei dati, un altro programma gestisce clienti e fornitori, la sincronizzazione dei contatti non acquisisce campi e non crea contatti; il collegamento e il numero di contatto Lexoffice restano. Lo stato trattenuto attende nella posta in arrivo delle integrazioni **Acquisisci una volta**.
