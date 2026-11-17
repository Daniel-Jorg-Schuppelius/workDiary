---
title: "Deseos de las organizaciones"
topic: admin.wishes
version: 1
keywords:
    - vista de plataforma deseos
    - agrupar deseos
    - canal del fabricante
    - deseo al fabricante
    - respuesta de la operación
audience:
    - admin
related:
    - wishes.overview
    - admin.settings
---

La vista de plataforma muestra todos los deseos que las organizaciones han
reenviado a la operación, solo para operadores de la plataforma. Se ven el
texto depurado, el tipo, la página, la versión de la aplicación, el número de
votos y la organización. Quién escribió o apoyó el deseo y el texto original
permanecen en la organización.

## Agrupar, estado y respuesta

- **Agrupar:** asigne deseos similares a un deseo principal del mismo tipo. La
  lista solo muestra deseos principales; los votos del grupo se suman.
- **El estado y la respuesta de la operación** se fijan en el deseo principal.
  Valen para todo el grupo y aparecen en el deseo de cada organización como
  «Respuesta de la operación de la plataforma»; autores y votantes reciben un
  aviso.

## Canal del fabricante (instalación autogestionada)

Si el fabricante opera la instalación, esta vista ya es el fabricante. En una
instalación autogestionada puede enviar deseos concretos al fabricante por
correo, solo si activa el canal en los **ajustes del sistema** con
`wishes.vendor_channel` (desactivado por defecto; sin él, ningún deseo sale de
la instalación). La dirección del fabricante está en `wishes.vendor_address`;
si está vacía, se aplica la dirección de contacto registrada.

1. Si es necesario, adapte el **texto para el fabricante** y guárdelo.
2. Compruebe la **vista previa**: destinatario, dirección de respuesta, asunto
   y exactamente el texto que se envía: referencia, tipo, texto, página,
   versión de la aplicación, número de votos y usted como remitente. La
   organización y los nombres nunca se incluyen.
3. **Enviar al fabricante** envía exactamente este correo. Si algo ha cambiado
   entretanto (por ejemplo, el número de votos), la aplicación pide una nueva
   vista previa.

La respuesta del fabricante le llega por correo. Introdúzcala en **Respuesta
del fabricante**; la organización la ve en el deseo.
