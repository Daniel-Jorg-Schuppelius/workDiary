---
title: "Plugins"
topic: admin.plugins
version: 2
keywords:
    - extensiones
    - complementos
    - add-ons
    - integraciones
    - activar plugin
    - desactivar plugin
    - probar conexión
    - comprobación de estado
    - errores de plugin
    - desactivación automática
    - registro de errores
audience:
    - admin
related:
    - admin.handbook
    - admin.toggl
    - admin.openproject
    - admin.lexoffice
    - admin.remote-support
---

Aquí gestiona los plugins e integraciones instalados. Los plugins
amplían WorkDiary con conexiones externas (p. ej. Toggl, OpenProject,
Lexoffice, mantenimiento remoto).

Importante: los plugins se controlan **por organización**. La
activación, la configuración, el estado de salud y los errores se
aplican en cada caso a su organización: un plugin puede tener un
estado completamente distinto en otra organización.

Vista general (lista):

- **Estado**: activo, inactivo o desactivado automáticamente.
- **Salud (health)**: ok / limitado / defectuoso, junto con el momento
  de la última comprobación.
- **Acciones por plugin**: configurar, activar/desactivar, ejecutar de
  inmediato la comprobación de salud y, en caso de desactivación
  automática, restablecer y reactivar.

Configurar (editar):

- Configuración de cada plugin (p. ej. token de API, endpoints).
  Contraseñas/tokens: un campo vacío deja sin cambios el valor
  existente.
- **Probar conexión** lanza una comprobación de salud sin guardar.

Comprobación de salud y desactivación automática:

- La comprobación de salud verifica la accesibilidad y el
  funcionamiento y actualiza el resultado por organización. Se ejecuta
  manualmente o de forma programada (cron).
- Si se producen errores repetidos, el plugin se **desactiva
  automáticamente** al alcanzar el umbral, solo para la organización
  afectada. Así, el funcionamiento no se ve afectado para las demás.
- Una vez solucionada la causa, restablece el contador de errores y
  reactiva el plugin.

Registro de errores (errores de plugins):

- Lista de todos los errores registrados con momento, plugin, fase
  (arranque/ejecución/comprobación de salud), clase de excepción y
  mensaje.
- Filtros por plugin, fase y estado (abierto/confirmado).
- En la vista de detalle: mensaje completo, contexto y traza de pila
  (stacktrace).
- Los errores pueden marcarse como **confirmado** (con la persona
  responsable y la marca de tiempo); se conservan para garantizar la
  trazabilidad.

Permisos: estas secciones están reservadas a los administradores y
requieren un contexto de organización.

Riesgos: un plugin desactivado detiene su sincronización: las
importaciones/exportaciones y las comprobaciones de salud quedan en
pausa hasta que se reactive. Compruebe el estado de salud después de
cada cambio de configuración.
