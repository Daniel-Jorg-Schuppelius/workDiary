---
title: "Sistema heredado (Legacy)"
topic: legacy.overview
version: 4
keywords:
    - sistema antiguo
    - datos antiguos
    - migración de datos
    - servicio de guardia
    - servicio de urgencias
    - acceso call center
    - archivo histórico
    - entradas antiguas
    - usuarios del sistema antiguo
    - modo heredado
    - empleados del sistema antiguo
    - portal de soporte
    - clientes del portal
    - pedidos de clientes del sistema antiguo
    - aviso por correo
related:
    - auth.login
    - admin.tenants
---

El área Legacy es un puente hacia el sistema antiguo: mantiene
disponibles sus datos y funciones hasta que estén completamente
migrados a WorkDiary. El acceso solo es posible para usuarios con un
identificador del sistema antiguo asignado y para administradores.
Incluye el **diario** (vista semanal, crear/editar/eliminar entradas),
**servicio de urgencia y guardias**, el **archivo** de solo lectura, la
**gestión de usuarios** del sistema antiguo y un acceso propio de
**call center** con plan de urgencias, además del **portal de soporte**
con los pedidos y accesos de los clientes. Las funciones de lectura están
siempre disponibles; las acciones de escritura y el cambio de contraseña
solo si el acceso de escritura al sistema antiguo está activado. Los
administradores disponen además de un panel de migración para trasladar
los datos.

## Central y Empleados

En el **Modo heredado** – que se activa en **Configuración** de la cabecera si
usted tiene acceso a ambas áreas – la navegación principal muestra **Vista
semanal**, **Lista de trabajo** y **Central**.

**Central** es el panorama de situación del sistema antiguo:

- Mosaicos **Problemas**, **Abierto**, **Confirmado** y **Hecho (7d)**, además
  de **Vencido**, **Vence hoy** y **Próximos 7 d**; un clic abre la lista de
  trabajo con el filtro correspondiente.
- El **Plan semanal** con **Servicio de urgencia** y **Disponibilidad**, a
  partir de ayer; se navega con **Semana anterior**, **Próxima semana** y
  **Semana actual**.
- **Fin de semana y festivos**, **Nuevas entradas (14 días)**, **Principales
  responsables (abiertos)**, **Próximos días festivos (30 días)** y **Avisos
  abiertos**.

Los planes de servicio los ve todo el mundo. Los datos del diario de todas las
personas los ven los administradores del sistema antiguo y el rol
**Contabilidad**; los demás solo ven los suyos. El acceso del call center lleva
a la misma página.

**Empleados** está en el menú de administración (icono **Administración** de la
cabecera) en **Personal** y lista los usuarios del sistema antiguo con
**Nombre** y **Correo electrónico**:

- **Nuevo empleado** crea una persona con **Nombre**, **Correo electrónico** y
  **Contraseña**; al editar, la contraseña no cambia si el campo queda vacío.
- Las tres primeras cuentas del sistema antiguo (administradores) no aparecen y
  aquí no se pueden modificar.
- **Eliminar** solo es posible mientras no existan entradas de diario, de
  servicio de urgencia o de disponibilidad de la persona.
- Crear, modificar y eliminar requieren acceso de escritura al sistema antiguo.

**Permiso:** la página **Empleados** está abierta a los administradores del
sistema antiguo y a la operación de la plataforma; el rol de administrador de la
organización por sí solo no basta.

## Portal de soporte

**Portal de soporte** en la navegación principal del modo heredado muestra los
pedidos que los clientes hicieron en el portal de clientes del sistema
antiguo:

- Se filtra por **Año** (está preseleccionado el año más reciente con
  pedidos), **Cliente** y texto de búsqueda; los mosaicos **Nuevo**,
  **Revisado**, **Cita fijada**, **Cita vencida**, **Terminado** y **Archivado**
  filtran la lista.
- **Editar** mantiene cliente, teléfono, emplazamiento, descripción, **Cita**,
  **Técnico**, **Mensaje para el cliente**, **Nota interna**, **Duración** y
  **Estado**. Una cita nueva cambia el estado a **Cita fijada**, salvo que se
  elija terminado o archivado. El cliente ve el mensaje, no la nota.
- **Crear pedido** registra un pedido para un cliente, por ejemplo tras una
  llamada.
- **Aviso por correo** activa la notificación de nuevos pedidos de clientes y
  fija el destinatario.
- **Archivo** lista los pedidos archivados; **Archivar** mueve allí los pedidos
  con estado **Archivado** anteriores a un día de corte. Después los clientes
  ya no los ven.

**Clientes del portal** en el menú de gestión administra los accesos de los
clientes con **Empresa**, **Nombre de usuario** y **Contraseña** (de 4 a 10
caracteres). Los clientes con pedidos en curso no se pueden eliminar.

Los clientes inician sesión en la página de acceso propia del portal de
clientes, ven solo sus propios pedidos con cita y respuestas, hacen pedidos
nuevos y cambian su contraseña. Si el acceso de escritura al sistema antiguo
está desactivado, el portal es de solo lectura.

**Permiso:** el portal de soporte y los clientes del portal están abiertos a los
administradores del sistema antiguo y a la operación de la plataforma.
