---
title: "Candidature e gare d'appalto"
topic: applications.overview
version: 2
keywords:
    - recruiting
    - selezione del personale
    - gestione candidati
    - annuncio di lavoro
    - colloquio di lavoro
    - talent pool
    - rifiutare candidato
    - assunzione
    - partecipazione a gare
    - bando
    - negoziazione contratto
audience: []
modules:
    - module.applications
related:
    - documents.manage
---

Il modulo gestisce due fascicoli preliminari, prima che nascano
commesse operative o dati dei dipendenti:

**Candidature a commesse (gare d'appalto):** fascicolo con scadenze,
potenziale di valore, decisione go/no-go, checklist dei documenti e
pacchetti di presentazione versionati (snapshot con hash SHA-256). Le
gare vinte vengono trasformate in modo controllato in un progetto;
quelle perse restano analizzabili con il motivo della perdita.

**Candidature del personale:** fabbisogno di posizioni → pubblicazione
→ fascicolo di candidatura con colloqui, valutazioni e decisione. I
dati dei candidati sono salvati in forma crittografata e visibili solo
all'area del personale (permessi recruiting). I rifiuti avviano
automaticamente la prenotazione di cancellazione (per impostazione
predefinita sei mesi dopo la scadenza per ricorsi ai sensi dell'AGG,
configurabile); il talent pool richiede un consenso esplicito e a
termine. Le assunzioni generano una bozza di dipendente — un account
attivo nasce solo con l'invito consapevole. Una decisione è definitiva:
colloqui, proposte di appuntamento e ulteriori decisioni sono poi
bloccati. Gli annunci pubblicati passano ogni giorno a «Scaduto» dopo la
data di scadenza o il termine per le candidature; può ripubblicarli con
una nuova data oppure chiuderli.

La decisione chiude anche i colloqui e le proposte di appuntamento aperti: i
colloqui pianificati vengono contrassegnati come annullati (la nota resta),
i link di appuntamento non ancora scelti scadono subito. L'unica eccezione è
il talent pool: finché il consenso è valido, «Riammettere dal talent pool»
riporta la pratica all'inizio della pipeline; la prenotazione di
cancellazione e il consenso vengono rimossi e il termine di cancellazione
viene fissato di nuovo con la prossima decisione. Senza un consenso valido
la pratica resta nel talent pool finché non scatta la prenotazione di
cancellazione.
**Trattative contrattuali:** passaggio proprio e versionato tra la
decisione di vittoria o di assunzione e la consegna. I punti bloccanti
aperti e le approvazioni mancanti (commerciale + tecnica,
auto-approvazione bloccata) impediscono la conclusione. Un'approvazione vale
per la versione presentata: se dopo la concessione di un livello di
approvazione viene salvata una nuova versione, l'approvazione ricomincia con
un nuovo turno; il turno precedente resta visibile nel fascicolo come
cronologia.

Le fasi di approvazione compaiono anche in «Approvazioni» per il ruolo che
l'organizzazione assegna al tipo di fase — predefinito: commerciale →
Contabilità, tecnica → Capo team, risorse umane → Gestione del personale;
modificabile modificando l'organizzazione, nella sezione «Approvazioni».
Una decisione presa lì ha lo stesso effetto dell'approvazione dalla
pratica; lì una fase può anche essere respinta (con motivazione) — una
nuova versione avvia quindi il turno successivo.

Avvertenza legale: WorkDiary documenta il processo, ma non sostituisce
una consulenza legale — in particolare nessuna valutazione
sull'ammissibilità o sulla convenienza economica delle condizioni
contrattuali.
