---
title: "Acceso y seguridad"
topic: customer-portal.access
version: 5
keywords:
    - inicio de sesión
    - iniciar sesión
    - contraseña
    - 2FA
    - autenticación en dos pasos
    - app de autenticación
    - passkey
    - llave de seguridad
    - códigos de recuperación
    - cambiar correo electrónico
    - mantener sesión iniciada
    - perfil
    - contraseña olvidada
    - restablecer contraseña
audience: []
related:
    - customer-portal.overview
---

Su acceso al portal de clientes es una cuenta personal que su proveedor crea para usted. Este tema explica cómo activar el acceso, iniciar sesión, cambiar su correo de inicio de sesión y proteger el acceso con un segundo factor.

## Activar el acceso

Su proveedor le invita por correo electrónico; el asunto es «Su acceso al portal de clientes de …». El correo contiene el botón **Establecer contraseña**. El enlace solo puede usarse una vez y es válido durante siete días; la fecha de caducidad figura en el correo.

1. Haga clic en **Establecer contraseña** en el correo.
2. Introduzca una contraseña en **Nueva contraseña** y repítala en **Repetir contraseña**.
3. Haga clic en **Guardar contraseña**.

La contraseña debe tener al menos 12 caracteres y contener mayúsculas y minúsculas, cifras y caracteres especiales. Las contraseñas procedentes de filtraciones de datos conocidas se rechazan. A continuación se abre la página de inicio de sesión con el aviso **Su contraseña se ha establecido. Ya puede iniciar sesión.**

Si el enlace ha caducado, la página ya no se abre. En ese caso, pida a su proveedor que le envíe de nuevo la invitación.

Si su proveedor ha restablecido su acceso, su contraseña anterior deja de ser válida y se cierran todas las sesiones de su acceso. Recibirá entonces una nueva invitación con el asunto «Su acceso al portal de clientes de …» y establecerá de nuevo su contraseña como se describe arriba. Un segundo factor ya configurado se mantiene.

## Iniciar sesión

En la página **Iniciar sesión** introduzca su **Correo electrónico** y su **Contraseña** y haga clic en **Iniciar sesión**. Con **Mantener sesión iniciada** no tiene que volver a iniciar sesión en cada visita desde este dispositivo – utilice esta opción solo en su propio dispositivo.

- Si el correo o la contraseña no son correctos, aparece **Estas credenciales no coinciden con nuestros registros.** Por motivos de seguridad, el portal no indica qué dato era incorrecto.
- El número de intentos de inicio de sesión está limitado; tras demasiados intentos fallidos debe esperar un rato.
- Si su proveedor ha desactivado su acceso, ya no es posible iniciar sesión.
- Si ha olvidado su contraseña, restablézcala usted mismo mediante **¿Olvidó su contraseña?** debajo del formulario de inicio de sesión.

### Contraseña olvidada

1. En la página **Iniciar sesión** haga clic en **¿Olvidó su contraseña?**.
2. Introduzca su correo de inicio de sesión en **Correo electrónico** y haga clic en **Enviar enlace**.
3. En un plazo de 60 minutos, abra el enlace del correo con el asunto «Restablecer su contraseña del portal de clientes de …» (botón **Establecer contraseña**).
4. Introduzca una contraseña en **Nueva contraseña**, repítala en **Repetir contraseña** y haga clic en **Guardar contraseña**.

Tras el envío, el portal muestra siempre **Si existe una cuenta con este correo, se ha enviado un enlace de restablecimiento.** – aunque no conozca la dirección. Así nadie puede averiguar qué direcciones tienen un acceso. Solo los accesos activos reciben un correo: si su invitación sigue pendiente o su acceso está desactivado, diríjase a su proveedor. El enlace solo sirve una vez; su contraseña actual sigue siendo válida hasta que guarde la nueva. Si usted no ha hecho la solicitud, simplemente ignore el correo.

Para la nueva contraseña rigen las mismas reglas que al activar el acceso. Tras guardar, la página de inicio de sesión muestra **Contraseña cambiada. Inicie sesión.** Todas las sesiones abiertas de su acceso, también en otros dispositivos, quedan cerradas. Un segundo factor ya configurado se mantiene y se solicita como de costumbre en el siguiente inicio de sesión. Varias solicitudes seguidas están limitadas; en ese caso espere un rato.

### Segundo factor al iniciar sesión

Si ha configurado un segundo factor, tras la contraseña aparece la página **Confirmación de dos factores**. Según el método configurado:

- introduzca en **Código** el código de 6 cifras de su aplicación de autenticación y haga clic en **Confirmar**,
- haga clic en **Enviar código por correo electrónico** e introduzca el código recibido; después **Confirmar con código de correo**,
- inicie sesión con **Con passkey / llave de seguridad**,
- o introduzca uno de sus códigos de recuperación mediante **Usar un código de recuperación en su lugar**.

Tras varios códigos incorrectos, la introducción se bloquea brevemente; tras demasiados intentos fallidos, el inicio de sesión vuelve a empezar. **Cancelar** le devuelve a la página de inicio de sesión.

## Perfil y correo de inicio de sesión

En **Perfil**, en la sección **Su acceso**, ve los datos **Nombre**, **Correo de inicio de sesión** y **Cliente** – la empresa a la que está asignado su acceso. El nombre y la empresa solo los puede cambiar su proveedor.

El correo de inicio de sesión lo cambia usted mismo:

1. En **Cambiar dirección de correo** introduzca la **Nueva dirección de correo**.
2. Haga clic en **Enviar enlace de confirmación**. Si no ha iniciado sesión
   recientemente, el portal le pide primero su contraseña en **Confirmar
   contraseña**.
3. En un plazo de 24 horas, abra el enlace del correo enviado a la nueva dirección.

Solo después de hacer clic en el enlace la nueva dirección pasa a ser su correo de inicio de sesión; la dirección anterior recibe una información sobre el cambio. Hasta entonces inicia sesión con la dirección anterior, y el perfil muestra para qué dirección hay una confirmación pendiente y hasta cuándo. Una nueva solicitud sustituye a la pendiente. Si la nueva dirección ya está en uso, no llega ningún correo; el mensaje del portal es siempre el mismo para que nadie pueda sacar conclusiones sobre otras cuentas.

## Configurar la autenticación de dos factores

**Seguridad** abre la página **Autenticación de dos factores**. Arriba a la derecha figura el estado: **Activo**, **Configuración pendiente** o **Inactivo**. La sección **Añadir método** ofrece tres métodos; puede usar varios a la vez.

### Aplicación de autenticación

1. Haga clic en **Mostrar código QR**.
2. Escanee el código QR con su aplicación (por ejemplo Google Authenticator, Aegis o 1Password) o escriba la **Clave** mostrada.
3. Introduzca el código de 6 cifras de la aplicación y haga clic en **Confirmar**.

### Código por correo

1. Haga clic en **Activar código por correo**. El portal envía un código a su correo de inicio de sesión.
2. Introduzca el código y haga clic en **Confirmar**. Si no ha llegado ningún correo, solicite un código nuevo con **Reenviar código**.

### Clave de seguridad / passkey (FIDO2)

Haga clic en **Añadir passkey** y siga las instrucciones de su navegador o dispositivo – con una passkey, su smartphone o una llave de hardware. Si su inicio de sesión fue hace tiempo, el portal le pide antes su contraseña en la página **Confirmar contraseña**.

### Códigos de recuperación

Al configurar el primer método, el portal muestra una sola vez sus **Códigos de recuperación**. Cada código funciona exactamente una vez y sustituye al segundo factor al iniciar sesión. Guarde los códigos en un lugar seguro; el portal no vuelve a mostrarlos. Si la aplicación de autenticación está configurada, puede generar un juego nuevo: en **Regenerar códigos de recuperación** introduzca en el campo **Código de aplicación actual** el código de la aplicación y haga clic en **Generar nuevo**. Los códigos anteriores dejan de ser válidos.

Si ha perdido todos los métodos y códigos de recuperación, diríjase a su proveedor. Puede restablecer su segundo factor; recibirá un correo electrónico titulado «Su segundo factor se ha restablecido» y después iniciará sesión solo con su contraseña. Si la autenticación de dos factores es obligatoria, configurará en ese momento un nuevo método.

## Eliminar factores o desactivarlo todo

La sección **Factores activos** muestra los métodos configurados. El código por correo y las passkeys se eliminan uno a uno con **Eliminar** (icono de papelera). La aplicación de autenticación solo puede desactivarse junto con todo lo demás.

En **Desactivar todo** usted desactiva por completo la autenticación de dos factores: introduzca un **Código de aplicación o de recuperación** y haga clic en **Desactivar**. Se eliminan todos los factores y códigos de recuperación.

## Cuando la autenticación de dos factores es obligatoria

Su proveedor puede exigir un segundo factor para todos los accesos. En ese caso:

- Si todavía no tiene un segundo factor, tras iniciar sesión el portal le lleva a la página **Autenticación de dos factores** con el aviso **Su organización exige la autenticación de dos factores. Configúrela ahora.** Las demás páginas solo se abren cuando hay un método configurado.
- El último factor restante no se puede eliminar.
- **Desactivar todo** no aparece; en su lugar, un aviso indica que no es posible desactivarla.
