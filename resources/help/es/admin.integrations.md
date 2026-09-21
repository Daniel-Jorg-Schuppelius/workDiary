---
title: "Gestionar integraciones"
topic: admin.integrations
version: 1
audience:
    - admin
related:
    - admin.plugins
    - admin.lexoffice
---

Esta ayuda se aplica a todas las páginas de administración de
integraciones — como CalDAV, WebDAV, Todoist, Zammad, Kimai/Clockify,
entrada de correo, telefonía, mensajería de equipo, terminales de
fichaje, envíos y SSO. Todas las conexiones siguen los mismos
principios básicos.

**Terminales de fichaje, quiosco y puntos de check-in:** Al registrar un
terminal se muestran dos direcciones una sola vez: la de ingesta para terminales
físicos y la del quiosco, que convierte el navegador de una tableta en terminal.
Ambas contienen el mismo token; si se pierde, rote el token o bloquee el
terminal. Las tarjetas leídas con el chip NFC de la propia tableta (Chrome en
Android) deben registrarse como identificador hexadecimal sin separadores. Los
puntos de check-in son códigos QR o etiquetas NFC en ubicaciones y vehículos: la
vista de impresión ofrece el código y la misma dirección puede grabarse en una
etiqueta con una app NFC. Un código puede fotografiarse: para acreditar la
presencia, fije un radio. La posición solo se comprueba, no se guarda.

Las tarjetas pueden sustituirse por un **PIN de terminal**: la administración lo
asigna por persona con número de personal; solo se guarda un hash y, tras cinco
intentos fallidos, se bloquea 15 minutos y puede desbloquearse aquí.

**Por organización:** Las integraciones se activan y configuran por
organización. La activación, las credenciales, el estado de salud y el
historial de errores se refieren siempre solo a la organización actual;
en otra organización la misma conexión puede tener un estado
completamente distinto.

**Credenciales:** Los tokens, contraseñas e identificadores de
dispositivo se registran en la configuración del plugin correspondiente.
Los valores sensibles se almacenan cifrados y tras guardarse ya no
aparecen en texto claro — ni en la interfaz ni en el registro de
auditoría.

**Healthcheck y desactivación automática:** Cada conexión se supervisa
de forma continua en busca de errores de conexión. Si los errores se
acumulan por encima del umbral configurable, la conexión se desactiva
automáticamente para que no produzca errores en cascada. Las
integraciones desactivadas automáticamente siguen visibles en la vista
general y quedan marcadas como tales — una vez resuelta la causa (p. ej.
renovado un token caducado) puede volver a activarlas. Un único plugin
defectuoso nunca arrastra consigo a la aplicación: los errores se
registran de forma aislada.

**Datos entrantes — Inbox-First:** Las importaciones no asumen nada a
ciegas. Los registros entrantes llegan primero a la bandeja de entrada
de integraciones, se cotejan con los datos existentes y solo se
incorporan tras una coincidencia inequívoca o su decisión manual. Los
casos dudosos y los conflictos permanecen como entradas abiertas en la
bandeja hasta que usted los resuelva o los descarte.

**Cambios salientes — Outbox:** Los cambios hacia el sistema externo
pasan por una bandeja de salida con reintento automático. Si una
transmisión falla, se vuelve a intentar; los conflictos detectados
(p. ej. si el sistema externo cambió entretanto) vuelven a la bandeja de
entrada para su aclaración. Así no se pierde ningún cambio y nada se
escribe dos veces.

**Recomendación:** Tras configurar una nueva conexión, compruebe el
healthcheck, observe durante unos días la bandeja de entrada en busca de
conflictos inesperados y solo entonces configure procesos automatizados
sobre ella.

## Qué integraciones existen

La oferta crece; la siguiente lista nombra las integraciones disponibles por
finalidad, para que no tenga que adivinar dónde encaja cada cosa:

- **Contabilidad y facturación:** lexoffice, orgaMAX, sevDesk, easybill,
  BuchhaltungsButler, InvoicePlane y el punto de acceso Peppol para enviar
  facturas electrónicas.
- **Telefonía y mensajes:** sipgate y FRITZ!Box para llamadas entrantes y
  salientes, seven.io para SMS a destinatarios críticos.
- **Envíos:** DHL, FedEx y UPS para etiquetas y seguimiento.
- **Archivos y copias de seguridad:** Nextcloud, WebDAV, Dropbox, Google Drive,
  SharePoint y S3 como destino de almacenamiento o respaldo.
- **Calendario, contactos y correo:** Microsoft Graph, Google Calendar, CalDAV,
  CardDAV y Calendly para citas reservadas.
- **Proyectos y tiempos:** Todoist, OpenProject, GitHub, GitLab, Toggl,
  Clockify, Kimai, Zammad.
- **Comercio y ERP:** JTL-Wawi, Billbee, Etsy.

Una integración que falte en esta lista no existe: ante la duda, pregunta en
lugar de guardar credenciales donde no corresponde.
