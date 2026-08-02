---
title: "Valore cliente"
topic: reports.customer-value
version: 1
audience: []
related:
    - reports.customer-analysis
    - reports.customer-retention
---

Il report sul valore cliente risponde: **di quali clienti vive
l'azienda, dove c'è rischio di concentrazione, quali clienti A sono a
rischio?**

- **Segmenti RFM**: ogni cliente riceve punteggi per quintili 1–5 per
  **R**ecency (giorni dall'ultima prestazione), **F**requency (giorni
  di attività nel periodo) e **M**onetary (ricavi). Ne derivano i
  segmenti *Champions*, *Fedeli*, *Da sviluppare*, *Nuovi*,
  *A rischio* e *Inattivi*.
- **Concentrazione**: quota dei ricavi dei top 5/top 10 e indice di
  Herfindahl-Hirschman (HHI). Sotto 1500 non critico, sopra 2500
  concentrazione elevata.
- **Clienti A a rischio**: ricavi elevati (M ≥ 4) ma nessuna
  prestazione dalla soglia impostata — con andamento dei ricavi su
  12 mesi.

I **ricavi** derivano dagli snapshot dei tempi fatturabili (stessa
fonte del report di redditività); gli importi fatturati sono solo un
valore secondario.
