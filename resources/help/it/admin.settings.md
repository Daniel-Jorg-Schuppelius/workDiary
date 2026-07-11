---
title: "Impostazioni di sistema"
topic: admin.settings
version: 1
audience:
    - admin
related:
    - admin.handbook
---

Questa pagina gestisce in un unico punto tutte le impostazioni
registrate della piattaforma – dalle dimensioni di pagina ai limiti di
upload fino alle soglie operative e di integrazione.

**Registry centrale:** ogni impostazione è registrata con tipo, ambiti
di validità ammessi e regole di validazione. La scrittura avviene
esclusivamente attraverso questo percorso validato – i valori non
validi (ad es. fuori dai limiti min/max) vengono respinti con un
messaggio di errore chiaro prima che possano avere effetto.

**Due ambiti di validità:** a seconda della voce, le impostazioni
valgono **a livello di sistema**, **per organizzazione** o entrambi.
Con il selettore di ambito si cambia la vista; la ricerca filtra per
chiave, l'elenco è ordinato per gruppi.

**Logica di precedenza:** per ogni valore vale un ordine fisso –
l'**impostazione dell'organizzazione** prevale sull'**impostazione di
sistema**, e questa sul **valore predefinito** incorporato
dell'installazione. La panoramica mostra per ogni voce il valore
effettivo con la sua origine, cosicché si riconosce subito se un
valore è predefinito o è stato sovrascritto.

**Ripristino e cronologia:** ogni sovrascrittura può essere
ripristinata singolarmente al valore predefinito. Per le impostazioni
di sistema è inoltre possibile consultare la cronologia delle
modifiche: chi ha impostato quale valore e quando – tracciabile
tramite il registro di audit.

**Valori sensibili:** le voci contrassegnate come sensibili (ad es.
indirizzi webhook con segreti) vengono visualizzate mascherate
nell'interfaccia. Possono essere impostate di nuovo, ma non lette.

**Effetto sui job:** alcune impostazioni influenzano i job pianificati
in background (ad esempio periodi di conservazione oppure orari di
esecuzione). Tali correlazioni sono annotate sulla voce; la modifica
ha effetto alla successiva esecuzione.

**Raccomandazione:** sovrascrivere il meno possibile. Ogni override a
livello di organizzazione rende il comportamento più difficile da
prevedere – impostarlo solo se l'organizzazione deve davvero
discostarsi, documentandone il motivo. Dopo le modifiche, verificare
il valore effettivo visualizzato invece di fidarsi dell'input.
