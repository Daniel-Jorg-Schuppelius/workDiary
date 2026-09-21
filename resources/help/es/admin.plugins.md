---
title: "Plugins"
topic: admin.plugins
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.toggl
    - admin.openproject
    - admin.lexoffice
    - admin.remote-support
---

Aquí administra los plugins e integraciones instalados (p. ej. Toggl,
OpenProject, Lexoffice, soporte remoto). Los plugins se controlan
**por organización**: activación, configuración, estado de salud y
errores valen solo para su organización. En la lista ve estado y
salud y puede configurar, activar/desactivar, ejecutar un
health-check o reactivar tras una desactivación automática; con
**Probar conexión** verifica la configuración sin guardar. Si un
plugin falla repetidamente se **desactiva automáticamente** solo para
la organización afectada; tras corregir la causa, restablezca el
contador de errores y reactívelo. El registro de errores muestra cada
incidencia con detalle y permite marcarla como **confirmada**; revise
el estado de salud después de cada cambio de configuración.
