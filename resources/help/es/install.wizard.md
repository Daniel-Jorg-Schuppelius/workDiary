---
title: "Instalación"
topic: install.wizard
version: 3
keywords:
    - asistente de instalación
    - configuración inicial
    - primera instalación
    - setup
    - requisitos del sistema
    - configurar base de datos
    - crear administrador
    - configuración SMTP
    - servidor de correo
    - notificaciones push
    - VAPID
audience: [admin]
related:
    - admin.tenants
    - auth.login
---

El asistente de instalación le guía paso a paso por la configuración
inicial de WorkDiary. Cada paso guarda sus valores de inmediato, de modo
que una interrupción puede repetirse en cualquier momento sin riesgo.
Con **Siguiente** pasa al paso siguiente y con **Atrás**, al anterior.
En cuanto finaliza la instalación, el asistente queda bloqueado y ya no
se puede abrir.

Los pasos de un vistazo:

- **Requisitos**: comprueba si el servidor cumple todos los requisitos
  para el **Controlador de base de datos** elegido. Tras corregir los
  puntos marcados, vuelva a comprobar con **Actualizar**.
- **Aplicación**: **Nombre de la aplicación**, **URL de la aplicación**,
  **Entorno**, **Idioma** y **Zona horaria**. Si aún no existe una clave
  de aplicación, se genera automáticamente; una clave existente no se
  modifica.
- **Base de datos**: **Controlador** y datos de conexión. **Conectar y
  migrar** prueba la conexión, configura la base de datos y crea los
  roles y permisos. Active la opción para vaciar la base de datos antes
  de la migración solo si la base debe quedar vacía o si se interrumpió
  un intento anterior.
- **Administrador**: creación de la primera organización (**Nombre de la
  organización**) y de la cuenta de administrador con **Crear
  administrador**.
- **Correo electrónico**: canal de envío (**Mailer**) y remitente de los
  correos. Con «log», los correos solo se registran y no se envían; con
  «smtp» introduce el **Host SMTP**, el puerto, las credenciales y el
  **Cifrado**, además de la **Dirección del remitente** y el **Nombre del
  remitente**.
- **Integraciones**: accesos opcionales como la **Clave de API de
  Lexoffice** y el par de claves para **Push web (VAPID)**, que **Generar
  clave** crea automáticamente. Todo puede completarse más adelante.
- **Cierre**: **Finalizar la instalación** bloquea el asistente, descarta
  los ajustes almacenados en caché para que los nuevos valores se
  apliquen de inmediato y lleva al inicio de sesión. Después, el
  administrador vuelve a iniciar sesión con normalidad.
