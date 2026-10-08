---
title: "Requisiti e SoA"
topic: isms.requirements-soa
version: 3
keywords:
    - dichiarazione di applicabilità
    - applicabilità
    - catalogo dei requisiti
    - requisiti normativi
    - Allegato A
    - ISO 27001
    - ISO 9001
    - ISO 27701
    - importare catalogo
    - giustificazione esclusione
audience: []
modules:
    - module.isms
related:
    - isms.overview
    - isms.controls
    - isms.conformity
    - glossary.core
---

Qui Lei gestisce il catalogo dei requisiti e la **dichiarazione di
applicabilità (DdA)** per ambito di applicazione. Trova la pagina in
**SGSI** → **Governance** → **Requisiti & DdA**.

Procedura tipica:

1. **Carica catalogo normativo**: scegliere e caricare un **Profilo
   normativo** – ISO/IEC 27001:2022 con l’Allegato A completo, inoltre
   ISO/IEC 27701, ISO 9001, ISO 22301, ISO 45001, ISO 37301 e ISO/IEC
   42001 con i capitoli principali da 4 a 10, nonché il NIST
   Cybersecurity Framework 2.0. Vengono caricati solo numero e titolo
   breve, nessun testo normativo. Un nuovo caricamento non sovrascrive
   mai i requisiti esistenti né le dichiarazioni DdA già compilate. In
   alternativa, **Importa OSCAL** acquisisce un catalogo da un file JSON.
2. Facoltativamente aggiungere requisiti propri con **Aggiungi
   requisito**; la loro **Origine** è allora «Requisito proprio» invece
   di «Catalogo di riferimento».
3. **Crea dichiarazioni DdA**: crea le dichiarazioni mancanti per tutti i
   requisiti dell’ambito scelto; quelle esistenti restano invariate. Poi
   compili ogni dichiarazione con **Modifica dichiarazione DdA**.
4. Usare la vista stampabile **DdA** (**Stampa / salva PDF**) per
   evidenze e audit; **Esporta (CSV)** ed **Esporta (JSON)** forniscono i
   dati come file.

Campi importanti per requisito: **Norma**, **Edizione**, **N. rif.**
(ad es. «A.5.1») e un **Titolo** proprio – volutamente nessun testo
normativo.

Per ogni dichiarazione DdA:

- **Applicabile** sì/no – in caso di «no» è obbligatoria una
  **Giustificazione** e lo **Stato di attuazione** diventa
  automaticamente **«Non applicabile»**.
- **Stato di attuazione**: «Aperto», «Parzialmente attuato», «Attuato»,
  «Non applicabile».
- **Nota di evidenza**: riferimento a un’evidenza o a un documento.

Autorizzazioni: il permesso **Vedere i registri SGSI (rischi, misure,
DdA)** consente la consultazione. L’importazione del catalogo e la
gestione richiedono **Gestire il SGSI (rischi, misure, importazione
catalogo)**.

Passi successivi: colleghi i requisiti a **Misure** neutrali rispetto
alle norme (colonna **Misure collegate**) – così nasce il ponte tra il
«cosa» della norma e il «come» della Sua attuazione.
