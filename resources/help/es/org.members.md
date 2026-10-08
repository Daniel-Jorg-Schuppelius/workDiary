---
title: "Empleados (organización)"
topic: org.members
version: 1
keywords:
    - añadir empleado
    - crear usuario
    - gestión de usuarios
    - ficha del empleado
    - número de personal
    - asignar rol
    - baja del empleado
    - offboarding
    - expediente personal
    - confirmación de lectura
    - modelo de jornada
audience: []
related:
    - admin.roles
    - org.teams
    - payroll.overview
---

Aquí gestiona los miembros de su organización: nombre, número de
personal, correo electrónico y rol (administración, usuario,
contabilidad). Al crear un miembro se establece una contraseña inicial
que la persona debe cambiar en el primer inicio de sesión; la lista solo
muestra miembros de la propia organización. El bloque de personal y
nómina (identificadores fiscales, seguro médico, tipo y periodo de
empleo, modelo de retribución) también puede mantenerlo la gestión de
personal y la dirección, pero quien solo tenga ese permiso no cambia
identidad, rol ni contraseña. El modelo de jornada se mantiene por
separado y es la base de las evaluaciones de horario flexible; crear y
eliminar miembros completos queda reservado a la administración y el
número está limitado por la licencia.

**Baja:** Cuando alguien se va, la cuenta no se borra sin más. La baja se tramita
en un diálogo con fecha efectiva y lista de traspaso: se enumeran los medios de
acceso entregados, los equipos asignados, las tareas abiertas y los fichajes
abiertos, para que nada quede pendiente. Mientras haya medios de acceso
entregados, la baja no puede tramitarse: primero hay que recogerlos. Si la fecha
efectiva es futura, la baja se programa y se tramita ese día. Entonces la cuenta
se desactiva, las sesiones y las claves de API terminan y la licencia queda
libre.

El motivo es sencillo: una cuenta borrada de inmediato se lleva consigo la
trazabilidad; los tiempos registrados, los protocolos firmados y las
aprobaciones deben seguir siendo atribuibles a su autor. Los datos personales
siguen sujetos, con independencia de ello, a las reglas de conservación y
supresión del área de protección de datos.

**Expediente personal:** Los miembros del círculo del expediente personal
pueden **solicitar una confirmación de lectura** para un documento. La persona
afectada confirma la lectura en «Mi expediente personal»; una nueva versión
requiere una nueva confirmación. Allí también **presenta sus propios
documentos**, por ejemplo un certificado. Las presentaciones aparecen en la
lista de empleados en «Presentaciones»: incorporarlas las añade al expediente;
rechazarlas exige un motivo visible para la persona. Nadie decide sobre su
propia presentación.
Se avisa a la persona cuando se solicita una confirmación de lectura o se
decide sobre su presentación, y al círculo del expediente personal cuando
llega una nueva presentación; ese aviso no menciona ni a la persona ni el
documento. Los destinatarios se ajustan en «Reglas de notificación».
