---
title: "Diseñador de procedimientos"
topic: procedures.designer
version: 3
keywords:
    - instrucción de trabajo
    - crear checklist
    - PNT
    - procedimiento operativo estándar
    - plantilla de proceso
    - flujo de trabajo
    - pasos obligatorios
    - principio de cuatro ojos
    - paso condicional
    - publicar versión
audience: []
related:
    - procedures.run
---

En el **Diseñador de procedimientos** usted define procesos obligatorios
(instrucciones de trabajo, listas de control) que después se ejecutan en
los pedidos. Las plantillas se encuentran en **Sistema** → **Reglas y
procesos** → **Plantillas de procedimiento**; **Editar** abre el
diseñador de una plantilla.

## Plantilla y versiones

- Una **Plantilla** (**Nueva plantilla**) tiene un **Código** único, un
  **Nombre**, un **Ámbito** opcional (p. ej. `it`, `hvac`) y una
  **Descripción**; en el diseñador se añade el **Nivel de riesgo**.
- Los pasos pertenecen siempre a una **Versión**. Mientras una versión
  sea un **Borrador**, puede editar los pasos libremente y guardarlos con
  **Guardar**; una **Nota de cambio** registra qué ha cambiado.
- **Publicar** marca la versión como válida e **inmutable**. Las
  correcciones requieren una **Nueva versión**; los pedidos en curso y
  antiguos conservan la versión que usaron.

## Pasos

Con **Añadir paso** o **Insertar desde la biblioteca** (desde la
**Biblioteca de pasos**) se añaden pasos. Cada paso tiene un **Código**,
una **Etiqueta**, una **Descripción** opcional y un **Tipo**, por
ejemplo «Confirmación», «Texto», «Número/medición», «Elección», «Foto»,
«Archivo», «Registro de copia de seguridad», «Firma», «Entrada de
material», «Serie de mediciones», «Aprobación (doble control)» o
«Tiempo de espera». Además se puede configurar:

- **Obligatorio**: debe tener un estado final antes de cerrar la
  ejecución.
- **Bloqueante**: bloquea los pasos siguientes hasta que este se
  complete.
- **Cuatro ojos**: exige la contrafirma de una segunda persona.
- **Prueba** («Copia de seguridad», «Archivo», «Foto», «Medición»,
  «Firma» o «Ninguno») y, opcionalmente, **Rol requerido** y
  **Cualificación**.
- **Condición: paso** y **Condición: valor** (si-entonces): el paso solo
  es relevante cuando otro paso tiene un valor determinado.

## Asignación automática

Mediante **Tipos de pedido** y **Etiquetas** usted determina para qué
pedidos se propone automáticamente la plantilla. En la página de detalle
del pedido, las plantillas publicadas que coinciden aparecen en la
tarjeta **Procedimientos** bajo «Procedimientos sugeridos para este
pedido:» como botón de inicio.
