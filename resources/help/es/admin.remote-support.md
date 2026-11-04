---
title: "Soporte remoto"
topic: admin.remote-support
version: 4
keywords:
    - AnyDesk
    - TeamViewer
    - RustDesk
    - Splashtop
    - pcvisit
    - token de API
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

El mantenimiento remoto recoge los informes de sesión de AnyDesk,
TeamViewer, RustDesk, Splashtop y pcvisit y los convierte en entradas de
tiempo. Las sesiones se asignan a un dispositivo (activo, p. ej. puesto
de trabajo, servidor, portátil) mediante el ID de equipo del proveedor.
Con **Importar sesiones** puede además cargar exportaciones de sesiones
(AnyDesk, RustDesk Server Pro) a través de la importación central; la
fila de cabecera indica el proveedor y una columna **Proveedor** tiene
prioridad.

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

Configurar proveedores (ajustes del plugin de la organización, un
interruptor por proveedor):

- **AnyDesk**: ID de licencia y contraseña de API de la licencia; el ID
  de equipo es el ID de AnyDesk del ordenador.
- **TeamViewer**: token de script con permiso para informes de conexión;
  el ID de equipo es el ID de TeamViewer.
- **RustDesk**: el servidor RustDesk gratuito no guarda conexiones. Las
  sesiones solo las entrega un servidor API que los clientes hayan
  registrado en «Servidor API». En **Server Pro** introduzca la dirección
  de la consola web y un token de API (Configuración → Tokens → Crear,
  permiso «Audit Log»); en el **servidor API de código abierto**
  (rustdesk-api) la dirección con el puerto más el usuario y la contraseña
  de un administrador, y la zona horaria del servidor solo si no funciona
  en la zona de la organización. Si el servidor está en su propia red,
  active **Permitir direcciones de red privadas**. El ID de equipo es el
  ID de RustDesk del ordenador (sin espacios). Las conexiones aún en
  curso se recogen cuando terminan.
- **Splashtop**: token de API de la consola web (Configuración de la
  cuenta → Web API Tokens; solo propietarios del equipo y
  superadministradores, la Open API forma parte de Splashtop Enterprise).
  Elija la región de su cuenta; el ID de equipo se obtiene del token. Los
  tipos de sesión se eligen separados por comas (predeterminado:
  desatendido y SOS). El ID de equipo es el UUID de equipo de Splashtop;
  la bandeja muestra el nombre del ordenador junto a él. Otros planes usan
  la exportación CSV de la página de registros a través de la importación
  central.
- **pcvisit**: token de API de usuario de la pcvisit Web App
  (configuración de la cuenta) y un token de integrador que pcvisit
  concede al fabricante del software (ajuste del operador; introdúzcalo
  por organización solo con acuerdo propio). Se recogen las sesiones
  cerradas con la duración registrada en pcvisit; el ID de equipo es el
  ID de equipo de pcvisit y el cliente final figura en la nota.

Si un proveedor falla, los demás siguen funcionando; el aviso aparece en
el estado del plugin y en el resultado de la importación.

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
