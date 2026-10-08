---
title: "Soporte remoto"
topic: admin.remote-support
version: 3
keywords:
    - AnyDesk
    - TeamViewer
    - acceso remoto
    - sesión remota
    - asistencia remota
    - mantenimiento remoto
    - ID de dispositivo
    - informe de sesión
    - escritorio remoto
    - control remoto
audience:
    - admin
related:
    - admin.support
    - admin.plugins
    - assets.fleet
---

El mantenimiento remoto recoge los informes de sesión de AnyDesk y
TeamViewer y los convierte en entradas de tiempo. Las sesiones se
asignan a un dispositivo (activo, p. ej. puesto de trabajo, servidor,
portátil) mediante el ID de equipo (ID de AnyDesk/TeamViewer). Con
**Importar sesiones** puede además cargar sesiones de AnyDesk a través
de la importación central.

La página **Mantenimiento remoto – conexiones sin asignar** tiene dos
pestañas; el campo de búsqueda encuentra ID de equipo, alias, equipo o
nota.

Pestaña **Equipos sin asignar**:

- Aquí se reúnen los ID que aparecen en los informes pero que aún no
  están asignados a ningún dispositivo de la organización, con el
  número de sesiones, la duración y el periodo.
- Si hay una **Propuesta** (cliente o dispositivo coincidente), la
  acepta con **Aplicar**.
- **Dispositivo existente**: en **Seleccionar dispositivo** elija un
  dispositivo existente y pulse **Asignar**; las sesiones guardadas se
  registran de inmediato como entradas de tiempo.
- **Nuevo dispositivo**: indique **Nombre**, **Categoría**, **Cliente**
  y, opcionalmente, **Cliente final**, y pulse **Crear y asignar**.
- **Dispositivo de varios clientes**: esta casilla, presente en ambas
  pestañas, marca un dispositivo que se usa para varios clientes. Sus
  sesiones no se registran entonces automáticamente, sino por cliente
  en la segunda pestaña.
- **Descartar**: rechaza todas las conexiones de un ID; no se
  registran.

Pestaña **Asignar sesiones** (dispositivos de varios clientes):

- Marque las sesiones, elija **Cliente**, opcionalmente **Cliente
  final** y **Proyecto**, y pulse **Imputar los marcados**: así el
  tiempo llega al cliente correcto.
- **Contabilizar selección internamente** registra las sesiones sin
  cliente en el proyecto de mantenimiento interno.
- **Descartar los marcados** descarta sesiones individuales.

Seguridad y riesgos:

- Las credenciales de API de los proveedores se guardan en la
  configuración del plugin de la organización. El sistema lee informes
  de sesión; no concede ningún acceso remoto directo.
- Los dispositivos de varios clientes requieren una asignación
  cuidadosa de cada sesión para evitar imputaciones erróneas entre
  clientes.
- **Las conexiones y sesiones descartadas no se registran**; la página
  no ofrece ninguna forma de recuperarlas.

Permiso: la página está reservada a los administradores.
