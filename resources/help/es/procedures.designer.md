---
title: "Diseñador de procedimientos"
topic: procedures.designer
version: 4
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

## Configuración por tipo de paso

Según su **Tipo**, un paso muestra más campos:

- «Elección»: **Opciones de selección**, separadas por comas. Sin
  opciones, el paso admite texto libre durante la ejecución.
- «Número/medición» y «Serie de mediciones»: **Unidad**, **Límite
  inferior** y **Límite superior** (tolerancia).
- «Tiempo de espera»: **Tiempo de espera fijo (segundos)**; vacío, la
  duración se introduce durante la ejecución.
- «Registro de copia de seguridad»: **Alcance**, **Almacenamiento**,
  **Método de verificación**, **Tamaño mínimo (KB)** y **Antigüedad máxima
  de la copia (minutos)**. «Se elige durante la ejecución» deja el dato a
  la persona ejecutora.
- Cualquier otro paso: **Requiere copia de seguridad previa** lo bloquea
  hasta que un paso de copia de la misma ejecución esté completado y su
  prueba confirmada, con antigüedad máxima si se desea.
- Pasos de cuatro ojos: **Rol de la segunda persona**, **Cualificación de
  la segunda persona** y **La persona ejecutora puede refrendar ella
  misma** (normalmente desactivado).

**Cualificación** es la abreviatura de una cualificación de los empleados
o, en su defecto, su nombre; se comprueba si la persona la posee vigente
hoy. La **Biblioteca de pasos** ofrece los mismos campos en
**Configuración por tipo de paso**. Los datos de perfiles sectoriales o
importaciones que el diseñador no muestra se conservan al guardar.

El nivel de riesgo «Crítica» exige al menos un paso de cuatro ojos: sin él
no se puede publicar la versión, y una ejecución solo se cierra con una
aprobación de cuatro ojos completada.

## Asignación automática

Una plantilla se propone cuando coinciden **todos** los criterios
definidos: **Tipos de pedido**, **Etiquetas**, **Clientes**, **Categorías
de activos**, **Productos** y **Contratos SLA**. Categoría, producto y SLA
se refieren al activo del pedido. Sin criterios, la plantilla vale para
cualquier pedido.

- En la página de detalle del pedido, las plantillas publicadas que
  coinciden aparecen en la tarjeta **Procedimientos** bajo «Procedimientos
  sugeridos para este pedido:» como botón de inicio.
- Si la plantilla indica una categoría de activo, un producto o un
  contrato SLA, también se ofrece en la ficha del activo bajo
  «Procedimientos previstos para este activo:».
- **Puede ejecutarse en activos bloqueados** permite el inicio en un
  activo bloqueado, por ejemplo para procedimientos de reparación e
  inspección.
