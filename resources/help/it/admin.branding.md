---
title: "Branding"
topic: admin.branding
version: 3
keywords:
    - white label
    - corporate design
    - identità visiva
    - logo aziendale
    - caricare il logo
    - logo scuro
    - colori aziendali
    - dati aziendali
    - piè di pagina
    - dati bancari fattura
audience:
    - admin
related:
    - admin.handbook
    - admin.tenants
    - invoices.manage
---

Nel branding definisce l'aspetto dell'organizzazione corrente: loghi,
nome visualizzato, dati aziendali e colori. Le impostazioni valgono per
l'interfaccia web e per i documenti PDF generati (ad esempio le fatture)
e vengono salvate per organizzazione.

Carica i **loghi** in alto in questa pagina, nella scheda **Logo** –
separatamente dal modulo sottostante; non serve salvare il modulo per
questo:

- **Logo (variante chiara)** è il logo predefinito.
- **Logo (variante scura, facoltativo)** compare nello schema di colori
  scuro e nei PDF per cui sceglie la variante scura. Se manca una delle
  due varianti, viene usata l'altra; senza un logo proprio compare il
  logo di WorkDiary.
- Scelga un file e clicchi su **Carica**: la nuova immagine sostituisce
  subito quella precedente. **Rimuovi** elimina il logo dopo una
  conferma.
- Sono ammessi PNG, JPG e WEBP fino alla dimensione indicata sotto il
  campo (2 MB di default); SVG non viene accettato.

Nel modulo sottostante gestisce:

- **Dati anagrafici**: nome visualizzato dell'app (titolo del browser e
  intestazione) e slogan.
- **Contatto**: indirizzo, telefono, e-mail e sito web.
- **Legale e piè di pagina PDF**: partita IVA, codice fiscale, titolare
  del conto, banca, IBAN, BIC, registro delle imprese e il testo del piè
  di pagina dei documenti PDF.
  Indichi il registro delle imprese insieme al tribunale del registro, ad
  esempio «HRB 12345, Amtsgericht Musterstadt»; la fattura elettronica lo
  riporta come identificativo di registrazione dell’organizzazione (BT-30),
  congelato all’emissione.
- **Colori**: colore primario e colore accento; sostituiscono i colori
  di base dell'interfaccia.
- **Configurazione PDF per tipo documento**: variante del logo (chiara,
  scura o nessun logo) e se compaiono **Contatto nell'intestazione** e
  **Piè di pagina**.

Impaginazione, carta intestata e stile delle tabelle dei PDF si
impostano nel design dei documenti; l'avviso in fondo alla pagina porta
lì se ha il permesso di aprirlo.

Nota: IBAN e BIC vengono verificati e normalizzati al salvataggio (spazi
rimossi, maiuscole). I campi svuotati tornano ai valori predefiniti del
sistema. Serve il permesso «Modifica il branding» (gli amministratori lo
hanno sempre); modifica solo il branding della propria organizzazione.
