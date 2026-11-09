---
title: "Migración desde el sistema anterior"
topic: admin.legacy-migration
version: 2
keywords:
    - sistema antiguo
    - datos heredados
    - datos históricos
    - traspaso de datos
    - migración de datos
    - importar datos antiguos
    - cambio de sistema
audience:
    - admin
related:
    - admin.import
    - admin.data-transfer
    - admin.handbook
---

La migración legacy transfiere los datos del sistema anterior a
WorkDiary y muestra el estado de la transferencia por área de datos
(**usuarios**, **entradas de diario**, **guardias**, **intervenciones
de emergencia**), comparando los registros existentes en el sistema
antiguo con los ya importados. Requiere una conexión de base de datos
configurada al sistema anterior; si no está accesible, el área aparece
como «no configurada». La importación se inicia por área y ejecuta en
segundo plano el comando `legacy:import`; los registros ya importados
quedan vinculados por un identificador legacy, de modo que las
ejecuciones repetidas no crean duplicados. El acceso de escritura
depende de la configuración (`legacy_write_enabled`); la página solo la
abre la operación de la plataforma, porque la base de datos del sistema
antiguo pertenece a toda la instalación.

El **portal de soporte** se traspasa a una **organización de destino** a
elegir: los accesos de los clientes se asignan a clientes existentes por
el nombre de la empresa o se crean como clientes; los pedidos y el
archivo pasan a ser tickets de servicio con número KP-… y conservan cita,
técnico, respuesta y nota interna. Las contraseñas no se traspasan; el
acceso al portal de clientes se concede en el nuevo sistema por
invitación. Una nueva ejecución actualiza los tickets ya traspasados. Las
horas del sistema antiguo son hora local y se convierten al importar.
