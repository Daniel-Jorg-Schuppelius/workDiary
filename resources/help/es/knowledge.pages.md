---
title: "Páginas de conocimiento"
topic: knowledge.pages
version: 1
keywords:
    - base de conocimiento
    - wiki
    - manual
    - instrucción de trabajo
    - lectura obligatoria
    - confirmación de lectura
    - incorporación
    - glosario
    - laguna de búsqueda
    - manual de emergencia
    - guía con imágenes
    - ennegrecer
    - OneNote
    - versión
audience: []
modules:
    - module.knowledge
related:
    - knowledge.collections
    - knowledge.articles
    - work-queue.overview
---

Las **páginas de conocimiento** recogen cómo trabaja su organización:
instrucciones de trabajo, guías, decisiones y el manual de emergencia. Las
encontrará en **Trabajo diario** → **Conocimiento y documentación** →
**Conocimiento**, pestaña **Páginas de conocimiento**.

## Estructura como un bloc de notas

- Las **áreas** son los blocs (por ejemplo «Manual» o «TI»). Un área puede
  estar **restringida**: entonces solo la leen los roles, equipos y personas
  seleccionados.
- Las **secciones** estructuran un área; las **páginas** están en ellas y las
  **subpáginas** debajo (hasta tres niveles).
- El texto se escribe en Markdown, con tablas, imágenes de los adjuntos
  (dirección de imagen `anhang:archivo.png`), **cuadros de aviso** (`:::note`, `:::tip`,
  `:::warning`) y **secciones desplegables** (`:::details Título`), cada uno
  hasta una línea `:::`. El HTML introducido se muestra como texto.

## Edición, versiones y aprobación

1. **Editar** abre el texto; cada cambio guardado es una nueva **versión**. Si
   alguien ha guardado la página mientras tanto, al guardar se avisa de un
   conflicto: nada se sobrescribe sin aviso.
2. La **persona responsable** **aprueba** la versión. Hasta entonces, los
   lectores siguen viendo la última versión aprobada.
3. En **Versiones** compara dos versiones línea por línea y recupera una
   anterior como nueva versión.

## Mantenerla al día

En **Responsabilidad y ubicación** fija la persona responsable, un **intervalo
de revisión** y una **fecha de validez**. **Sigue vigente** confirma la
página y fija la próxima fecha de revisión. Las revisiones pendientes, el fin
de la validez y las aprobaciones pendientes aparecen en la **cola de
trabajo**; pasada la fecha de validez, la página se considera **obsoleta**.

## Lectura obligatoria

En **Lectura obligatoria** asigna una página a todos los miembros, a un rol, a
un equipo o a una persona, si lo desea como parte de la **lista de
incorporación**. Quien debe leerla confirma con **Leído y entendido**; una
versión aprobada nueva exige una nueva confirmación. La vista general muestra
quién ha confirmado qué versión. Su propia lectura está en **Mi lectura** y en
la cola de trabajo.

## Encontrar

La búsqueda abarca título y texto, y también el **glosario**: una abreviatura
o un sinónimo encuentra también el término. Los términos sin resultados llegan
a la administración como **lagunas de búsqueda**, contados sin su nombre. Las
páginas aparecen también en la búsqueda global. La marca **modificada** señala
las páginas que han cambiado desde su última visita.

## Vincular

Con **Vincular** asocia una página a un cliente, proyecto, activo, encargo,
ticket o procedimiento. En el cliente, el proyecto y el activo aparece luego
en el cockpit; en el encargo, el ticket y el procedimiento, en la tarjeta
**Páginas de conocimiento**. Como otros contenidos, las páginas pueden
incluirse en **colecciones**.

## Guías con imágenes

Una **guía con imágenes** consta de pasos con captura de pantalla y texto. En
un paso coloca marcas de **flecha**, **número**, **marco** y **texto**.
**Ennegrecer** escribe las zonas elegidas en la imagen; el original sin
ennegrecer se descarta y ya no se puede recuperar, ni siquiera en versiones
anteriores. **Reproducir** muestra la guía paso a paso.

## Manual de emergencia y PDF

Las páginas marcadas para el **manual de emergencia** lo forman: **Manual de
emergencia (PDF)** emite todas las páginas de emergencia aprobadas con
versión, aprobación y responsable, para tenerlo disponible incluso si la
aplicación falla. Cada página se puede exportar **como PDF** por separado.

## Papelera e importación

Las páginas eliminadas permanecen 30 días en la **papelera** y se pueden
restaurar; después se eliminan definitivamente. Solo las personas autorizadas
pueden eliminar definitivamente. **Importar Markdown** adopta archivos como
borradores; las carpetas de Obsidian y los blocs de OneNote se adoptan con la
importación del acceso **Conocimiento** y el destino **Página de
conocimiento**.
