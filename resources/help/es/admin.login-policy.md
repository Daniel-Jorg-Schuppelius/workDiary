---
title: "Política de inicio de sesión: horario, redes de la oficina, passkey y bloqueo de emergencia"
topic: admin.login-policy
version: 2
keywords:
    - política de inicio de sesión
    - horario de acceso
    - franja horaria
    - red de la oficina
    - rango IP
    - CIDR
    - teletrabajo
    - aprobar dispositivo
    - passkey
    - passkey obligatoria
    - bloqueo de emergencia
    - cerrar todas las sesiones
audience:
    - admin
related:
    - admin.security
    - account.two-factor
---

La política de inicio de sesión determina **cuándo** y **desde dónde**
pueden iniciar sesión los miembros de su organización. Los administradores
de la organización están siempre exentos de horarios y redes para que la
organización no pueda bloquearse a sí misma; el equipo de la plataforma y
las cuentas del portal de clientes nunca se ven afectados.

**Horario de acceso**: Una franja horaria por día de la semana en la zona
horaria de la organización, opcionalmente solo para roles seleccionados.
Fuera de la franja no se puede iniciar sesión – el mensaje indica la
próxima apertura – y las sesiones abiertas terminan al cerrarse la franja.
Una franja puede pasar la medianoche (22:00–06:00). Los tokens de API
personales siguen el horario; los terminales de fichaje y las
integraciones no.

**Redes de la oficina**: Una lista de redes permitidas (IPv4/IPv6, también
como CIDR) y nombres DNS de conexiones dinámicas; el servidor resuelve los
nombres cada cinco minutos. Quien no tenga el permiso «Iniciar sesión
fuera de las redes de la oficina» solo inicia sesión desde estas redes.
Con el permiso también desde fuera, pero solo desde un **dispositivo
aprobado**: un dispositivo nuevo presenta una solicitud de aprobación al
iniciar sesión, que los administradores de la organización aprueban o
rechazan aquí. El dispositivo se reconoce por una cookie firmada, no por
su dirección IP. El propio dispositivo lo aprueba otra persona.

**Efecto «Solo registrar»**: El horario y las redes pueden registrarse
primero. Al final de la página aparece entonces durante una semana a quién
habría bloqueado la regla. Cambie a «Aplicar» solo cuando la lista sea
correcta; de lo contrario, redes mal introducidas bloquearían oficinas
enteras.

**Passkey obligatoria**: Para roles seleccionados la organización exige
iniciar sesión con passkey. Quien aún no tenga una la configura tras el
siguiente inicio de sesión en «Autenticación de dos factores». Un código
de recuperación sigue siendo la vía de emergencia si falta el dispositivo.

**Bloqueo de emergencia**: En caso de emergencia, los titulares o los
administradores de la organización terminan, indicando un motivo y
volviendo a introducir la contraseña, todas las sesiones y los tokens de
API personales de la organización excepto su propia sesión. Después solo
pueden iniciar sesión los administradores de la organización y las
personas con el permiso «Bloqueo de emergencia», hasta que alguien con
este permiso o el equipo de la plataforma levante el bloqueo. Se notifica
a todos los administradores de la organización; los terminales de fichaje,
los dispositivos de ubicación y las integraciones siguen funcionando y las
demás organizaciones no se ven afectadas.

**Accesos personales**: los tokens de API y las conexiones de IA (MCP) no
llevan cookie de dispositivo. Fuera de las redes de la oficina solo funcionan
con el permiso propio «Usar accesos personales también fuera de las redes de
la oficina»; el horario y el bloqueo de emergencia se les aplican siempre.

**Dispositivos y nombres DNS en detalle**: una aprobación rechazada o
retirada se mantiene: el mismo dispositivo no vuelve a solicitarla hasta que
usted «olvide» el rechazo. Cada persona tiene como máximo tres solicitudes
abiertas. Cada organización puede indicar como máximo diez nombres DNS; se
resuelven en segundo plano con límite de tiempo, una resolución correcta es
válida como máximo 24 horas y las direcciones internas de las respuestas DNS
solo cuentan si el operador lo permite. «Aplicar» requiere al menos una
franja horaria o una red.

**Passkey obligatoria y SSO**: si alguien inicia sesión con un código de
recuperación pese a la passkey obligatoria, se avisa a la persona y a todos
los administradores de la organización; la última passkey solo puede
eliminarse cuando haya una nueva. Las sesiones iniciadas mediante SSO no
están sujetas a la passkey obligatoria: allí el proveedor de identidad
decide el factor y el instrumento es la obligación de SSO.
