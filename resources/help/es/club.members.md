---
title: "Socios de la asociación"
topic: club.members
version: 1
audience: []
modules:
    - module.club
related:
    - club.groups
    - admin.import
---

El registro de socios recoge a todas las personas de su asociación, puedan
iniciar sesión o no. Los niños sin correo propio son socios igual que los
adultos con cuenta de usuario; se puede vincular una cuenta de la misma
organización sin que se cree un rol de personal.

**Número de socio y datos maestros:** Cada socio recibe un número correlativo
por organización. Al crearlo puede indicar un número (por ejemplo de una lista
antigua) o dejar el campo vacío. Los hermanos con la misma dirección o un
correo familiar común siguen siendo socios distintos: el correo no es clave de
duplicados.

**Historial de afiliación:** El tipo (activo, pasivo, colaborador, en pausa)
se aplica por periodo. «Cambiar tipo / pausa» cierra el periodo en curso el
día anterior a la fecha de efecto y abre el nuevo; los periodos anteriores
siguen visibles. «Registrar baja» fija el último día de afiliación, finaliza
todas las asignaciones a grupos en ese día y rechaza las solicitudes
abiertas. Los justificantes no se eliminan.

**Representantes:** Asigne a los tutores de forma expresa por socio, con
contacto, cuenta de usuario opcional, acciones permitidas y validez. Una
cuenta puede representar a varios niños y solo ve a estos. Una revocación
termina el acceso; la asignación se conserva como justificante.

**Quién ve qué:** El registro lo ven las personas con el permiso «Ver registro
de la asociación» o «Administrar la asociación». Los responsables de grupo
solo ven a los socios de sus propios grupos; un socio vinculado ve su propio
registro.

**Importación CSV inicial:** A través del centro de importación («Socios de
la asociación») puede cargar una lista antigua con vista previa y lista de
errores. El número de socio es la clave de coincidencia: una segunda carga
del mismo archivo no crea duplicados, sino que actualiza los datos maestros.
El tipo y la fecha de alta de los socios existentes quedan reservados al
historial.
