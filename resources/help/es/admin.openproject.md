---
title: "Integración con OpenProject"
topic: admin.openproject
version: 3
keywords:
    - gestión de proyectos
    - paquetes de trabajo
    - work packages
    - registros de tiempo
    - importar horas
    - enviar horas
    - sincronización de tiempos
    - sincronización de proyectos
    - asignaciones
audience:
    - admin
related:
    - admin.plugins
    - admin.toggl
    - admin.import
    - admin.integration-inbox
---

La integración con OpenProject conecta WorkDiary con OpenProject de
forma **bidireccional**: los tiempos se importan **y** los tiempos
registrados pueden devolverse a OpenProject. Las credenciales y
opciones se configuran en los ajustes del plugin (entre ellos **URL de
la instancia**, **Token de API** y **Franja de sincronización
(días)**).

Sincronizar (página **Sincronizar OpenProject**):

- **Sincronizar estructura + tiempos** con **Sincronizar ahora**:
  concilia proyectos y work packages y, a continuación, importa las
  entradas de tiempo de la franja configurada.
- **Sincronizar solo la estructura** con **Comparar estructura**:
  asigna proyectos, work packages y usuarios de OpenProject a los
  proyectos, tareas y usuarios de WorkDiary. Si **Crear
  proyectos/tareas faltantes** está activado, la comparación crea
  automáticamente las entradas que faltan.

Entradas de tiempo sin asignar:

- Lo que no puede asignarse automáticamente va a la **Bandeja de
  conciliación** central; **A la bandeja de conciliación** lleva hasta
  ella y muestra el número de entradas abiertas.
- Allí asigna un grupo a un cliente y un proyecto (o indica uno nuevo)
  y lo registra, o bien lo descarta. Las importaciones futuras se
  asignan automáticamente según las asignaciones guardadas.

Devolución (**Devolver los tiempos**):

- Devuelve a OpenProject los tiempos no exportados de los proyectos
  asignados a un proyecto de OpenProject; las tareas se registran como
  work package si están asignadas. **Período (opcional)** acota la
  ejecución (vacío = todas las entradas abiertas), **Devolver ahora** la
  inicia y **Última devolución** muestra el resultado. Las entradas ya
  devueltas se omiten.
- Requisito: en los ajustes del plugin debe indicarse el **ID de
  actividad de OpenProject (registro)**; de lo contrario no es posible
  la devolución.

Asignaciones (**Gestionar asignaciones**):

- La página **OpenProject – asignaciones** enumera los vínculos
  guardados para proyectos, work packages y usuarios. Con **Reasignar**
  cambia el destino y con **Eliminar** borra una asignación.

Riesgos: la devolución modifica datos en el sistema OpenProject
conectado. Antes de la primera ejecución, revise las asignaciones y el
ID de actividad para evitar imputaciones erróneas.
