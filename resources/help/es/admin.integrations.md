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
