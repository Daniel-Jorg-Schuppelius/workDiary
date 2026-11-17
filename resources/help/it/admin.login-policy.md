---
title: "Criteri di accesso: orari, reti dell’ufficio, passkey e blocco di emergenza"
topic: admin.login-policy
version: 2
keywords:
    - criteri di accesso
    - orari di accesso
    - fascia oraria
    - rete dell’ufficio
    - intervallo IP
    - CIDR
    - lavoro da remoto
    - approvare dispositivo
    - passkey
    - passkey obbligatoria
    - blocco di emergenza
    - disconnettere tutti
audience:
    - admin
related:
    - admin.security
    - account.two-factor
---

I criteri di accesso stabiliscono **quando** e **da dove** i membri della
vostra organizzazione possono accedere. Gli amministratori
dell’organizzazione sono sempre esenti da orari e reti, così che
l’organizzazione non possa escludere se stessa; il team della piattaforma e
gli account del portale clienti non sono mai interessati.

**Orari di accesso**: una fascia oraria per giorno della settimana nel fuso
orario dell’organizzazione, eventualmente solo per ruoli selezionati.
Fuori dalla fascia nessun nuovo accesso riesce – il messaggio indica la
prossima apertura – e le sessioni in corso terminano alla fine della
fascia. Una fascia può superare la mezzanotte (22:00–06:00). I token API
personali seguono gli orari; terminali di timbratura e integrazioni no.

**Reti dell’ufficio**: un elenco di reti consentite (IPv4/IPv6, anche come
CIDR) e di nomi DNS di connessioni dinamiche; il server risolve i nomi ogni
cinque minuti. Chi non ha il permesso «Accedere dall’esterno delle reti
dell’ufficio» accede solo da queste reti. Con il permesso si può accedere
anche dall’esterno, ma solo da un **dispositivo approvato**: un nuovo
dispositivo presenta una richiesta di approvazione all’accesso, che gli
amministratori dell’organizzazione approvano o respingono qui. Il
dispositivo è riconosciuto tramite un cookie firmato, non tramite
l’indirizzo IP. Il proprio dispositivo lo approva un’altra persona.

**Effetto «Solo registrare»**: orari e reti possono essere prima soltanto
registrati. In fondo alla pagina compare allora per una settimana chi la
regola avrebbe escluso. Passate ad «Applicare» solo quando l’elenco è
corretto – altrimenti reti inserite in modo errato escluderebbero interi
uffici.

**Passkey obbligatoria**: per i ruoli selezionati l’organizzazione richiede
l’accesso con passkey. Chi non ne ha ancora una la configura dopo il
prossimo accesso in «Autenticazione a due fattori». Un codice di recupero
resta la via di emergenza se manca il dispositivo.

**Blocco di emergenza**: in caso di emergenza, i titolari o gli
amministratori dell’organizzazione terminano, indicando un motivo e
reinserendo la password, tutte le sessioni e tutti i token API personali
dell’organizzazione tranne la propria sessione. In seguito possono accedere
solo gli amministratori dell’organizzazione e le persone con il permesso
«Blocco di emergenza», finché qualcuno con questo permesso o il team della
piattaforma non revoca il blocco. Tutti gli amministratori
dell’organizzazione vengono avvisati; terminali di timbratura, dispositivi
di localizzazione e integrazioni continuano a funzionare, le altre
organizzazioni non sono interessate.

**Accessi personali**: i token API e le connessioni IA (MCP) non hanno un
cookie del dispositivo. Fuori dalle reti dell’ufficio funzionano solo con il
permesso apposito «Usare gli accessi personali anche fuori dalle reti
dell’ufficio»; orari e blocco di emergenza valgono sempre.

**Dispositivi e nomi DNS nel dettaglio**: un’approvazione respinta o revocata
resta registrata: lo stesso dispositivo non la richiede di nuovo finché non
«dimenticate» il rifiuto. Ogni persona ha al massimo tre richieste aperte.
Ogni organizzazione può inserire al massimo dieci nomi DNS; vengono risolti
in background con un limite di tempo, una risoluzione riuscita vale al
massimo 24 ore e gli indirizzi interni delle risposte DNS contano solo se il
gestore lo consente. «Applicare» richiede almeno una fascia oraria o una
rete.

**Passkey obbligatoria e SSO**: se qualcuno accede con un codice di recupero
nonostante la passkey obbligatoria, vengono avvisati la persona e tutti gli
amministratori dell’organizzazione; l’ultima passkey si può rimuovere solo
dopo averne configurata una nuova. Le sessioni avviate tramite SSO non sono
soggette alla passkey obbligatoria: lì il fornitore di identità decide il
fattore, la leva è l’obbligo SSO.
