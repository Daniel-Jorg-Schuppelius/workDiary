---
title: "Archivio WebDAV"
topic: admin.webdav
version: 1
keywords:
    - WebDAV
    - Nextcloud
    - ownCloud
    - copiare documenti
    - archivio file
    - archiviare fatture
    - archiviare verbali
    - password app
    - regole cartelle
    - conflitto di copia
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - documents.manage
    - invoices.manage
    - protocols.sign
    - backup-targets.overview
---

La pagina **WebDAV** (titolo della pagina **Archivio WebDAV**) copia come file
i documenti approvati e, se lo desidera, le fatture emesse e i verbali firmati
in un archivio WebDAV esterno, per esempio Nextcloud o ownCloud. Per ogni file
WorkDiary conserva una prova di trasferimento (checksum, ora, destinazione).
WorkDiary resta il sistema di riferimento: non esiste un canale di ritorno, e
le modifiche ai file copiati nell’archivio appaiono come conflitto invece di
essere riprese in silenzio. Trova la pagina nel menu di sistema
(l’ingranaggio **Sistema** nella barra in alto) sotto **Plugin** → **WebDAV**,
non appena il plugin è attivo.

## Prerequisiti

- Il plugin è attivato per la Sua organizzazione: **Sistema** → **Plugin** →
  **Plugin**, poi **Attiva** sulla voce WebDAV. L’archivio vero e proprio si
  configura nella pagina **WebDAV**, non nella finestra del plugin.
- La pagina è riservata agli amministratori.
- Servono un account nell’archivio con diritto di scrittura sulla cartella di
  destinazione e una password per app (Nextcloud: Impostazioni → Sicurezza →
  Password app).
- L’archivio deve essere raggiungibile pubblicamente. WorkDiary rifiuta gli
  indirizzi di una rete interna.
- Per ogni organizzazione esiste esattamente un archivio WebDAV.

Questa pagina non è una destinazione per i backup. Una destinazione di backup
WebDAV si configura sotto **Destinazioni di backup cloud**.

## Configurare l’archivio

Nella sezione **Archivio** compila:

- **Etichetta**: un nome a scelta.
- **URL della collection**: la cartella WebDAV completa in cui WorkDiary
  scrive, per Nextcloud per esempio …/remote.php/dav/files/UTENTE/WorkDiary.
  L’indirizzo deve iniziare con http:// o https://; crei prima la cartella
  nell’archivio.
- **Nome utente** e **Password app**: la password è obbligatoria al primo
  salvataggio e viene memorizzata cifrata; in seguito un campo vuoto mantiene
  la password memorizzata.
- **Cartella predefinita**: sottocartella per i documenti senza una propria
  regola di cartella (precompilata con Dokumente).
- **Attivo**: attiva o disattiva l’archivio.
- **Contenuti replicati**: **Documenti (DMS)**, **Fatture (PDF)**, **Verbali
  (PDF)**.
- **Tipo di documento → cartella**: una sottocartella per tipo di documento,
  vedi sotto.

**Salva** applica i dati. Quando l’archivio è attivo, la pagina ne mostra lo
stato (per esempio **Stato ok**) e **Verifica connessione**.

## Che cosa viene copiato e quando

- **Documenti:** un documento viene copiato non appena ha lo stato **Attivo**
  con un file, e di nuovo a ogni nuova versione. Le modifiche ai dati senza
  nuova versione non provocano un caricamento. L’archivio copia sempre i
  documenti approvati finché è attivo – anche se **Documenti (DMS)** non è
  spuntato.
- **Fatture (PDF):** con questa casella spuntata ogni fattura viene depositata
  una volta come PDF al passaggio a **Emessa**.
- **Verbali (PDF):** con questa casella spuntata ogni verbale viene depositato
  come PDF alla firma (stato **Firmato**).
- Il trasferimento avviene in background tramite una coda e viene ripetuto in
  caso di errori di connessione. WorkDiary non ricarica contenuti invariati.
- **Copia ora** rimette in coda tutti i documenti attualmente approvati –
  utile dopo la configurazione. Questo pulsante non comprende fatture e
  verbali; questi vengono copiati solo a partire dalla configurazione, al
  momento dell’emissione o della firma.
- Non esiste un’esecuzione pianificata; la copia segue le modifiche in
  WorkDiary.

## Cartelle e nomi dei file

- I documenti finiscono nella cartella del loro tipo definita in **Tipo di
  documento → cartella**, altrimenti nella **Cartella predefinita** – entrambe
  relative all’URL della collection. Il file si chiama document- seguito dal
  numero del documento e dall’estensione originale, per esempio
  document-42.pdf.
- La scelta dei tipi di documento mostra attualmente la sigla inglese, per
  esempio contract per i contratti o invoice per le fatture. Ci sono sempre
  tre righe libere; WorkDiary scarta le righe senza tipo o senza
  sottocartella.
- Le fatture finiscono in invoices/anno/numero-fattura.pdf, i verbali in
  protocols/anno/protocol-numero.pdf – direttamente sotto l’URL della
  collection, non nella cartella predefinita.
- WorkDiary crea da sé le sottocartelle mancanti.

## Conflitti

Prima di caricare una nuova versione, WorkDiary controlla se il file
nell’archivio è stato modificato dall’ultima copia. In caso affermativo non
sovrascrive nulla e crea un conflitto nell’Inbox di riconciliazione:
«Modifica esterna rilevata — copia sospesa». Lì sceglie:

- **Sovrascrivi remoto**: il file nell’archivio riceve lo stato di WorkDiary;
  la modifica esterna va persa.
- **Importa come nuova versione**: lo stato dell’archivio diventa la nuova
  versione del documento in WorkDiary.
- **Scollega copia**: questo documento non viene più copiato in modo
  definitivo; l’archivio resta attivo per tutti gli altri.

L’Inbox di riconciliazione è accessibile agli amministratori e alla
contabilità.

## Disconnettere

**Disconnetti** disattiva l’archivio. I file già copiati restano
nell’archivio. Per riattivarlo imposti **Attivo** e salvi.

## Errori frequenti

- «L'URL della collection deve iniziare con http:// o https://.»: inserisca
  l’indirizzo completo.
- «Un nuovo archivio richiede una password app.»: al primo salvataggio manca
  la password.
- **Stato difettoso** con «Archivio WebDAV non raggiungibile o credenziali non
  valide.»: controlli URL della collection, nome utente e password app e che la
  cartella esista. Un errore WebDAV con RuntimeException indica spesso un
  indirizzo di una rete interna.
- «Nessun archivio WebDAV attivo.» con **Copia ora**: l’archivio è disattivato
  o incompleto.
- Nell’archivio mancano fatture o verbali: la casella corrispondente in
  **Contenuti replicati** non era spuntata al momento dell’emissione o della
  firma.
- Un documento non viene più aggiornato: c’è un conflitto aperto nell’inbox,
  oppure la sua copia è stata scollegata.
