---
title: "Gestionar plantillas de formularios"
topic: forms.templates
version: 3
keywords:
    - crear formulario
    - editor de formularios
    - diseñador de formularios
    - crear checklist
    - campos del formulario
    - tipos de campo
    - lista desplegable
    - campo obligatorio
    - activar formulario
    - archivar formulario
    - formularios personalizados
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
modules:
    - module.forms
related:
    - forms.fill
    - glossary.core
---

Las plantillas de formulario definen listas de control y registros sin
código, mediante la definición de campos. Las encontrará en **Sistema** →
**Reglas y procesos** → **Plantillas de formulario** o mediante el botón
**Plantillas de formulario** de la vista general **Formularios**.

Proceso habitual:

1. **Crear plantilla**: **Nombre**, **Descripción**, opcionalmente
   **Válido desde** y **Válido hasta**, así como **Asignación: tipo de
   encargo** y **Asignación: cliente** (con «todos», la plantilla se
   aplica en todas partes). Debajo siguen los **Campos**; con **Añadir
   campo** se añade otro. Para cada campo: **Etiqueta del campo**, **Tipo
   de campo** y **Obligatorio**, según el tipo las **Opciones** (separadas
   por comas), la **Unidad** o el **Rango de valores** (Min, Max),
   opcionalmente un **Texto de ayuda** y una condición **Visible cuando**,
   con la que un campo solo aparece cuando otro campo tiene un valor
   determinado.
2. **Activar**: las plantillas nuevas empiezan con el estado «Borrador»;
   solo con el estado «Activa» se puede rellenar la plantilla.
3. **Archivar**: retira la plantilla de la selección para rellenar; los
   formularios cumplimentados siguen siendo legibles. Una plantilla
   archivada puede volver a activarse.

Tipos de campo: «Texto», «Texto multilínea», «Número», «Casilla»,
«Selección», «Selección múltiple», «Fecha», «Fecha y hora», «Escala»,
«Foto», «Archivo», «Firma», «Sección» y «Medición». Usted no introduce
una clave de campo propia; cada etiqueta de campo solo puede aparecer una
vez por plantilla.

Estados importantes: «Borrador» → «Activa» → «Archivada».

Principio de la instantánea: cada formulario cumplimentado congela la
definición de campos en el momento de rellenarlo. Por eso, los cambios
en los campos solo afectan a **los formularios que se rellenen después**;
los antiguos permanecen inalterados y evaluables. Ni siquiera
**Eliminar** una plantilla hace ilegibles los formularios cumplimentados.

Permisos: puede crear, editar, activar, archivar y eliminar plantillas
de formulario quien tenga el permiso **Gestionar plantillas de
formulario** (de forma predeterminada, los jefes de equipo).

Consejo: el sistema deriva la asignación interna de un campo de su
etiqueta. Por eso, mantenga las etiquetas de campo si desea comparar
formularios cumplimentados de varias versiones de una plantilla.
