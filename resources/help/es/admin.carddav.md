---
title: "Conectar una libreta de direcciones CardDAV"
topic: admin.carddav
version: 1
keywords:
    - CardDAV
    - conectar libreta de direcciones
    - contactos de Nextcloud
    - Radicale
    - Baïkal
    - importar contactos
    - comparar contactos
    - contraseña de aplicación
    - vCard
    - sincronización de contactos
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - contacts.manage
    - admin.scheduler
---

La página **CardDAV** conecta WorkDiary con una libreta de direcciones de su
propio servidor CardDAV, por ejemplo Nextcloud, Radicale o Baïkal. WorkDiary
solo lee los contactos y se los propone para asignarlos a sus clientes. Nunca
escribe nada en la libreta de direcciones, no fusiona registros por su cuenta
ni crea clientes. No necesita una cuenta de Microsoft ni de Google.

## Requisitos previos

- El plugin **CardDAV** está activado para su organización en **Plugins**. A
  continuación aparece la entrada **CardDAV** en el menú del sistema (icono de
  engranaje **Sistema**), dentro del grupo **Plugins**.
- Conoce la dirección del servidor CardDAV, un nombre de usuario y una
  contraseña. Si el servidor tiene activo el inicio de sesión en dos pasos
  (habitual en Nextcloud), necesita una contraseña de aplicación, que crea en su
  cuenta del servidor.
- La página está reservada a los administradores de su organización. Cada
  organización tiene exactamente una conexión CardDAV.

## Configurar la conexión

1. Rellene los campos de la sección **Conexión**:
   - **Denominación**: un nombre para la conexión, por ejemplo «Nextcloud
     oficina».
   - **URL base DAV**: en Nextcloud, la dirección hasta /remote.php/dav
     incluido; en Radicale y Baïkal, la raíz del servidor. La dirección debe
     empezar por http:// o https://.
   - **Nombre de usuario** y **Contraseña de aplicación**. La contraseña se
     guarda cifrada y no vuelve a mostrarse. Si deja el campo vacío en una
     conexión existente, sigue valiendo la contraseña guardada.
   - **Permitir direcciones privadas/internas**: actívelo solo si el servidor
     está en su propia red (por ejemplo 192.168.x.x). Sin esta opción,
     WorkDiary rechaza las direcciones internas. La activación queda
     registrada.
   - **Activo**: activa o desactiva la conexión.
2. Haga clic en **Guardar**.
3. Haga clic arriba en **Buscar libretas de direcciones**. WorkDiary consulta el
   servidor y muestra las libretas encontradas en la sección **Libreta de
   direcciones**.
4. Seleccione una libreta y haga clic en **Usar esta libreta**. A partir de ese
   momento es la fuente de sincronización; la página la muestra como «Fuente
   de sincronización actual».

Solo pueden elegirse libretas de la última búsqueda que estén en el mismo
servidor que la URL base. No es posible introducir una dirección cualquiera
como fuente.

## Qué se sincroniza y cuándo

- **Dirección:** solo del servidor CardDAV a WorkDiary.
- **Contenido:** nombre, empresa, dirección de correo, números de teléfono,
  móvil y fax, nota y la dirección postal con el país. Si hay varias
  direcciones de correo o números, WorkDiary prefiere los marcados como de
  trabajo.
- **Momento:** la sincronización se ejecuta automáticamente cada hora. La
  frecuencia se cambia en **Tareas programadas**. **Sincronizar ahora** la
  inicia de inmediato; se ejecuta en segundo plano y después la página muestra
  «Última sincronización …».
- **Solo cambios:** WorkDiary omite los contactos sin cambios. Solo se procesan
  las fichas nuevas o modificadas.

## Asignación a clientes

- Si un contacto coincide de forma inequívoca con un único cliente, WorkDiary
  vincula ambos. Si más adelante cambia un contacto vinculado y sus datos
  difieren de los del cliente, se crea un conflicto de campo en la **Bandeja
  de conciliación**: los datos del cliente nunca se sobrescriben en silencio.
- Todos los demás contactos (sin cliente coincidente o con varios candidatos)
  llegan como propuestas a la **Bandeja de conciliación**. Allí asigna el
  contacto a un cliente, lo crea como registro nuevo o lo descarta.
- Si se elimina un contacto en la libreta de direcciones, WorkDiary descarta su
  propuesta si sigue abierta. Las asignaciones ya realizadas se mantienen.
- La **Bandeja de conciliación** está abierta a las personas autorizadas a
  gestionar la facturación.

## Cambiar, desconectar, empezar de nuevo

- Si cambia la **URL base DAV**, WorkDiary descarta la libreta elegida y el
  estado de sincronización anterior. Busque y elija después la libreta de
  nuevo.
- Si elige otra libreta, la sincronización empieza desde cero.
- **Desconectar** deja la conexión inactiva. Las propuestas ya creadas se
  conservan. Para continuar, vuelva a activar **Activo** y haga clic en
  **Guardar**.

## Problemas habituales

- **Dirección interna rechazada:** el mensaje «La URL base apunta a una
  dirección privada/interna» aparece cuando el servidor está en su propia red.
  Active **Permitir direcciones privadas/internas**.
- **La búsqueda falla:** «La búsqueda de libretas de direcciones falló»
  significa que el servidor no es accesible o que las credenciales no son
  correctas. Compruebe la URL base, el nombre de usuario y la contraseña de
  aplicación.
- **Sin libretas:** «No se encontraron libretas de direcciones en el servidor»:
  la cuenta no tiene libreta, o la URL base apunta al nivel equivocado.
- **Libreta ajena:** «La dirección no pertenece al servidor CardDAV
  configurado»: la libreta elegida está en un servidor distinto al de la URL
  base.
- **Sin sincronización:** si falta **Sincronizar ahora** o WorkDiary indica
  «Sincronización imposible», la conexión está inactiva, no hay libreta
  elegida, o se suspendió tras errores repetidos consecutivos. La página
  muestra el último error en la parte superior.
- **Comprobar el estado:** junto al título de la página figura el último estado
  comprobado de la conexión. **Probar conexión** lo comprueba al momento.
