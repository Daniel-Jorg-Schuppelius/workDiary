---
title: "Conectar DomainReselling"
topic: admin.domain-provider
version: 1
audience:
    - admin
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

**Confirmar el piloto:** Tras una prueba real correcta confirmas el piloto;
solo entonces la conexión puede pasar a producción. Mientras el piloto siga
abierto, la comprobación de estado indica «piloto abierto».

**Rotar credenciales y desconectar:** El usuario/contraseña pueden
restablecerse en cualquier momento (rotación) sin volver a crear la
conexión. Al desconectar se elimina la conexión; los datos de proyección ya
leídos se conservan como evidencia.

**Estados:** Una conexión está en *borrador*, *activa* o *bloqueada*. Las
conexiones bloqueadas muestran un estado bloqueado visible en la
comprobación de estado, nunca un fallo silencioso.
