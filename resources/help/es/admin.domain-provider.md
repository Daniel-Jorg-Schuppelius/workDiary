---
title: "Conectar DomainReselling"
topic: admin.domain-provider
version: 2
keywords:
    - gestión de dominios
    - nombres de dominio
    - revendedor de dominios
    - DNS
    - registros DNS
    - registro de dominio
    - renovación de dominio
    - registrador
    - cartera de dominios
audience:
    - admin
    - geschaeftsfuehrung
modules:
    - module.domain
related:
    - admin.plugins
    - admin.integrations
---

WorkDiary conecta una **cuenta de DomainReselling** por organización y
gestiona sus dominios de forma controlada: leer la cartera, asignar
clientes, mantener plazos y DNS, y someter las acciones de alto riesgo a
aprobación. Esta página configura la conexión; el trabajo de dominios se
realiza después en el módulo «Dominios».

**Elegir el entorno:** Cada conexión funciona en *OT&E* (el entorno de
prueba/piloto) o en *producción*. Las cuentas nuevas comienzan en OT&E; el
paso a producción se habilita solo tras un piloto superado y confirmado de
forma real, de modo que ningún registro real acabe por error en una prueba.

**Credenciales:** El usuario y la contraseña se almacenan cifrados y nunca
aparecen en URL, registros ni diagnósticos. Opcionalmente indica un usuario
predeterminado (s_user): el contexto bajo el que se ejecutan los comandos de
un subusuario autorizado.

**Probar y sincronizar:** «Probar conexión» verifica las credenciales
contra la API sin modificar nada. «Sincronizar» trae la cartera actual
(dominios, plazos, modos de renovación, revendedores/subusuarios) a las
proyecciones locales. La sincronización es de solo lectura e idempotente.

**Confirmar el piloto:** Tras una prueba real correcta confirma el piloto;
solo entonces la conexión puede pasar a producción. Mientras el piloto siga
abierto, la comprobación de estado indica «piloto abierto».

**Renovar credenciales:** El icono de llave abre el diálogo «Renovar
credenciales». Introduzca una contraseña nueva; el usuario y el usuario
predeterminado no cambian si deja los campos vacíos. WorkDiary comprueba de
inmediato las credenciales nuevas — la conexión se mantiene con todos los
dominios, asignaciones y justificantes.

**Desconectar:** «Desconectar» no borra nada. Se elimina la contraseña, el
estado pasa a *desconectada* y ninguna sincronización, consulta de eventos
ni comando llega ya al proveedor. Los dominios, contactos, zonas DNS, el
diario de comandos (quién cambió qué en el proveedor), los asientos y los
vínculos de dominio de sus suscripciones de reventa y líneas de compra se
conservan como justificante. Para volver a conectar, guarde credenciales
nuevas mediante «Renovar credenciales»; en cuanto se supera la
comprobación, la conexión vuelve a estar activa.

**Estados:** Una conexión está en *borrador*, *activa*, *bloqueada* o
*desconectada*. Las conexiones bloqueadas muestran un estado visible de
bloqueo en el control de estado — nunca un fallo silencioso. El control de
estado no notifica una conexión desconectada como incidencia; está fuera de
servicio de forma intencionada.
