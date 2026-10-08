---
title: "Editar un registro de tiempo"
topic: time-entries.edit
version: 2
keywords:
    - corregir horas
    - cambiar registro
    - hora incorrecta
    - modificar inicio y fin
    - modificar pausa
    - cambiar de proyecto
    - solicitud de corrección
    - registro bloqueado
    - historial de cambios
    - ajustar horas
    - tiempo administrativo
    - registrar tiempo interno
audience: []
related:
    - time-entries.start
    - reports.customer-analysis
---

Haga clic en una fila de la lista de registro de tiempos para editar la
entrada; los cambios quedan en el registro de auditoría con persona,
momento y valor anterior. Las entradas **ya aprobadas** están
bloqueadas: use las **solicitudes de corrección**. No cambie nunca
solo el fin de un turno — ajuste siempre inicio, fin y pausa como
conjunto para no volver inconsistentes los informes. Cambiar de
proyecto está permitido mientras la asignación anterior no se haya
facturado.

## Tiempo administrativo

El tiempo administrativo es tiempo de trabajo sin proyecto: reuniones,
formaciones, trabajos internos, tiempo de viaje, pausas y otras actividades.
Usted siempre lo registra para sí mismo: el registro se asigna a su propia
cuenta. Registrarlo para otras personas no está previsto aquí.

**Cómo acceder:**

- En la barra lateral mediante **Nuevo …** → **Operativa diaria** →
  **Tiempo administrativo**. La fecha viene rellenada con hoy.
- En la vista del día (**Operativa diaria** → **Registro** → **Hoy**) mediante
  el botón **Tiempo administrativo** arriba a la derecha. La fecha viene
  rellenada con el día mostrado, también con un día anterior si ha vuelto a él
  con **Día anterior**.

**Campos del diálogo «Registrar tiempo administrativo»:**

- **Fecha** y **Duración (minutos)** son obligatorios. La duración está entre
  1 y 1440 minutos; vienen rellenados 30 minutos.
- **Tipo de actividad** (obligatorio): **Administración** (por defecto),
  **Reunión**, **Formación**, **Interno**, **Viaje**, **Pausa** u **Otro**.
- **Categoría (opcional)**: una de las categorías de actividad activas de su
  organización; la lista muestra el tipo de actividad de cada categoría.
- **Período (opcional)**: **Inicio (hora)** y **Fin (hora)**. Un fin sin inicio
  se rechaza. Si el fin es anterior al inicio, cuenta para el día siguiente:
  así registra tiempos que pasan de medianoche. Si indica inicio y fin, la
  aplicación calcula la duración a partir de ellos y sustituye el número de
  minutos introducido.
- **Descripción** (hasta 500 caracteres) y **Etiquetas**.
- Si ya existe un fichaje suyo en la fecha rellenada, el registro se vincula
  con él. El diálogo muestra entonces el aviso «Se vincula con el fichaje
  (desde las …)».

Tras **Registrar** vuelve a la vista del día de la fecha elegida. El registro
aparece allí en **Registros de tiempo** con su tipo de actividad y, si la
eligió, con la categoría.

**Diferencias con el registro de tiempo con proyecto:**

- No hay campo de proyecto; el tiempo se clasifica por tipo de actividad y
  categoría.
- El inicio y el fin son opcionales: basta con una duración.
- El tipo de actividad se limita a los tipos sin relación con un proyecto
  indicados arriba.

**Editar y eliminar:** en la vista **Hoy**, el icono del lápiz (**Editar**)
en la fila de un tiempo administrativo abre el diálogo **Editar tiempo
administrativo**. En el diálogo **Editar tiempo administrativo** cambia
los mismos campos, escribe **Comentarios** y elimina el registro con
**Eliminar** (tras una pregunta de confirmación). Después de guardar o
eliminar se abre la vista del día de la fecha del registro. Solo puede editar
y eliminar sus propios registros y solo mientras no estén bloqueados. Un
registro está bloqueado si

- la ventana de corrección ha caducado (por defecto, 7 días después del día
  del registro),
- el mes ya se ha aprobado para usted,
- la hoja de horas correspondiente está firmada o bloqueada, o
- el registro ya se ha exportado.

El diálogo indica entonces el motivo; los comentarios siguen siendo posibles.

**Permiso:** toda persona con sesión iniciada puede registrar tiempo
administrativo para sí misma. El rol **Administrador** también puede editar y
eliminar registros de otras personas y registros bloqueados; el diálogo indica
entonces que está editando como administrador.
