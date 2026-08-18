---
title: "Radar de anuncios de licitación"
topic: tenders.radar
version: 1
audience: []
related:
    - applications.overview
---

El radar examina los **anuncios de licitación públicos federales alemanes** en
busca de licitaciones que encajen con la propia empresa. La fuente es el
servicio oficial de anuncios (oeffentlichevergabe.de), que publica todos los
anuncios obligatorios como datos abiertos con licencia CC0 — sin registro ni
credenciales de portal.

**Los perfiles de búsqueda** definen qué se busca. Dos sistemas de códigos
sostienen la búsqueda: **CPV** indica *qué* se contrata, **NUTS** indica
*dónde*. Ambos son jerárquicos, así que bastan los prefijos — `45` abarca todas
las obras de construcción, `DEA` toda Renania del Norte-Westfalia. Las palabras
clave buscan además en título, descripción y órgano de contratación; **las
palabras de exclusión pesan más**: una coincidencia allí descarta el anuncio
aunque todo lo demás encaje. Los anuncios sin valor indicado nunca quedan
excluidos por los límites de valor — de lo contrario se perdería todo lo que no
declara su importe.

**La descarga es diaria y recupera el día anterior.** Un día de publicación
solo está completo al día siguiente; descargar hoy dejaría huecos. Los anuncios
corregidos llegan como nueva versión y la anterior se conserva.

**La bandeja de resultados propone, no decide.** Lo que no encaja se oculta y
se conserva como prueba; lo que encaja se convierte en un expediente de
licitación con título, órgano de contratación, CPV, región, plazo y fuente
precargados. **Compruebe después el tipo de procedimiento y el umbral** — la
fuente abierta nombra el procedimiento solo de forma aproximada, y de ella no
puede deducirse con seguridad ni el tipo de procedimiento alemán ni la
situación respecto a los umbrales.
