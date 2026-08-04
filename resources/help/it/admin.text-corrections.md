---
title: "Dizionario"
topic: admin.text-corrections
version: 1
audience:
    - admin
---

Il **dizionario** corregge automaticamente gli errori di ortografia
ricorrenti — in modo deterministico e senza IA. Ogni voce è una coppia
«errato → corretto».

- **Effetto**: alla costruzione dei testi di posizione generati
  (trasferimenti di fatturazione, bozze di fattura, anteprima fattura).
  Le registrazioni dei tempi restano invariate.
- **Corrispondenza**: solo parole o frasi intere, senza distinzione tra
  maiuscole e minuscole; la grafia della correzione viene conservata
  (MAIUSCOLO resta MAIUSCOLO, l'inizio frase diventa maiuscolo).
- **Apprendimento**: quando un testo di posizione viene corretto
  manualmente, l'app rileva le sostituzioni di parole 1:1 e propone di
  «memorizzarle» — l'aggiunta avviene solo dopo conferma, mai in modo
  silenzioso. Queste voci appaiono come «Appreso».
- **Disattivare invece di eliminare**: una voce disattivata non ha effetto
  ma resta tracciabile.

La gestione richiede il permesso di configurazione finanziaria, perché le
voci modificano il contenuto delle fatture.
