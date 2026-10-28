# Publicar AlquilaHN gratis, paso a paso

Usaremos GitHub para el código, Render Free para Laravel, Aiven MySQL Free para datos y Cloudinary Free para fotos. El subdominio `onrender.com` incluye HTTPS. No necesitas comprar dominio ni instalar Docker en tu PC para que Render construya el proyecto.

Los planes tienen cuotas: [Render Free](https://render.com/docs/free) suspende el servicio tras 15 minutos sin visitas y tarda en despertar; [Aiven MySQL Free](https://aiven.io/docs/products/mysql/concepts/mysql-free-tier) ofrece 1 GB de disco; [Cloudinary Free](https://cloudinary.com/documentation/developer_onboarding_faq_free_plan) limita el consumo mensual. Elige Free, no una prueba temporal de pago. Render presenta su servicio gratuito para proyectos personales y pruebas, sin las garantías de un plan de producción.

Para el SEO hay un límite concreto: mientras el servicio Free está suspendido, Render responde a /robots.txt con "disallow all" sin despertar la aplicación. La optimización SEO del código se mantiene, pero esta modalidad gratuita no ofrece el mismo comportamiento de rastreo que un alojamiento siempre activo. Revisa también los límites de transferencia y builds; con método de pago registrado, algunos excesos pueden generar cargos. Consulta y configura los límites de gasto de tu cuenta antes de desplegar.

## 1. Subir el código a GitHub

1. Crea una cuenta en https://github.com si no tienes una.
2. Pulsa **+ → New repository**. Nombre sugerido: `alquilahn`. Puede ser **Private**. No añadas README, licencia ni gitignore: ya tienes archivos del proyecto. Crea el repositorio vacío.
3. Abre PowerShell y ejecuta lo siguiente, reemplazando `TU_USUARIO` por el usuario real de GitHub:

```powershell
cd E:\Proyectos\Alquiler
git init
git add .
git status --short
git commit -m "Preparar AlquilaHN para Render"
git branch -M main
git remote add origin https://github.com/TU_USUARIO/alquilahn.git
git push -u origin main
```

Si Git pide identificar al autor, define tu nombre y correo **para este repositorio**, y repite `git commit`:

```powershell
git config user.name "Tu nombre"
git config user.email "TU_CORREO_O_CORREO_NOREPLY_DE_GITHUB"
```

`git push` puede abrir el acceso de GitHub en el navegador. Completa ese acceso tú mismo. GitHub no acepta la contraseña normal como contraseña Git: usa el acceso del navegador/Git Credential Manager. No pegues tokens en el chat ni en el código.

El .gitignore excluye .env, SQLite, vendor, node_modules y certificados en /certs. **Incluye** composer.lock, package-lock.json, Dockerfile, render.yaml y el código. Guarda certificados descargados fuera de la carpeta del proyecto o en /certs. No subas secretos por la interfaz Upload files.

## 2. Crear MySQL Free en Aiven

1. Entra en https://console.aiven.io y crea tu cuenta.
2. Crea un servicio **MySQL**, elige el plan **Free** y una región cercana a la de tu servicio Render cuando esté disponible.
3. Espera a que esté funcionando. En Overview/Connection information copia a tu gestor de contraseñas o al panel de Render: **Host**, **Port**, **Database name**, **User** y **Password**. Usa el puerto indicado, que puede ser distinto de 3306.
4. Descarga **CA certificate**. Guárdalo, por ejemplo, en Descargas; no lo publiques.
5. En PowerShell convierte ese certificado (ajusta la ruta real) y copia el resultado para Render:

```powershell
[Convert]::ToBase64String([IO.File]::ReadAllBytes("C:\Users\lagos\Downloads\ca.pem"))
```

Si el servicio tiene restricciones de red, autoriza las IP de salida de la región de Render que muestre su panel. Mantén TLS y verificación del certificado activados. No uses la base SQLite local en Render.

## 3. Crear Cloudinary Free

1. Entra en https://cloudinary.com y crea una cuenta Free.
2. En la consola obtén **Cloud name**, **API Key** y **API Secret**. El panel puede ofrecer copiar directamente **CLOUDINARY_URL**.
3. Su formato es `cloudinary://API_KEY:API_SECRET@CLOUD_NAME`. Cópialo únicamente al campo CLOUDINARY_URL de Render. No se necesita preset unsigned.

## 4. Preparar la clave Laravel

En PowerShell, muestra una clave nueva para producción:

```powershell
C:\php85\php.exe artisan key:generate --show
```

Copia el valor completo, incluido `base64:`, en APP_KEY de Render. Este comando con --show no cambia tu .env local. Conserva la clave entre despliegues. No la pegues en el chat ni en GitHub.

## 5. Crear el servicio web en Render

1. Entra en https://dashboard.render.com y conecta GitHub.
2. Elige **New → Web Service**, selecciona tu repositorio `alquilahn` y la rama `main`.
3. Runtime/Language: **Docker**. Dockerfile path: `./Dockerfile`. Docker context: `.`. Deja el comando de arranque sin sobrescribir; lo define Docker.
4. Plan de instancia: **Free**. No añadas disco persistente, base de datos Render de pago ni servicios adicionales.
5. En **Environment** añade estas variables antes del despliegue:

| Variable | Valor |
| --- | --- |
| APP_ENV | production |
| APP_DEBUG | false |
| APP_KEY | Clave completa generada en el paso 4 |
| DB_CONNECTION | mysql |
| DB_HOST | Host de Aiven |
| DB_PORT | Puerto de Aiven |
| DB_DATABASE | Nombre de base de Aiven, normalmente defaultdb |
| DB_USERNAME | Usuario de Aiven, normalmente avnadmin |
| DB_PASSWORD | Contraseña de Aiven |
| AIVEN_CA_CERT_BASE64 | Certificado codificado del paso 2 |
| CLOUDINARY_URL | URL privada del paso 3 |
| SESSION_DRIVER | database |
| SESSION_SECURE_COOKIE | true |
| CACHE_STORE | file |
| LOG_CHANNEL | stderr |

APP_URL se obtiene automáticamente del RENDER_EXTERNAL_URL de Render. Si usas un dominio propio en el futuro, define APP_URL con ese dominio HTTPS. Google es opcional en el primer despliegue: el registro con correo y contraseña funciona sin credenciales Google. No necesitas copiar APP_URL=http://localhost:8000 ni APP_DEBUG=true de tu entorno local.

6. Configura **Health Check Path: `/up`** (puede aparecer en Advanced o Settings después de crear el servicio).
7. Pulsa **Create Web Service / Deploy**. Render compila Tailwind y PHP, ejecuta migraciones y arranca Apache. Revisa el log hasta que diga **Live**.
8. Abre el enlace HTTPS que Render asignó. El nombre puede variar si `alquilahn` ya está ocupado.

Alternativa: **New → Blueprint** con el mismo repositorio usa render.yaml. El archivo ya declara plan Free y health check; solicitará los secretos pendientes. Revisa siempre el plan antes de crear el recurso.

## 6. Comprobar el primer despliegue

- Abre la portada, registra una cuenta y publica un anuncio con varias fotos.
- Verifica filtros, búsqueda libre, WhatsApp y el detalle del inmueble.
- Reinicia el servicio desde Render y confirma que el anuncio y las fotos siguen presentes.
- Abre `/robots.txt` y `/sitemap.xml`. En el código fuente, el canonical debe tener tu dominio Render y no localhost.
- Las imágenes que tenías en anuncios de la base SQLite local no se migran automáticamente. La producción comienza con la base MySQL de Aiven; publica tus anuncios allí o prepara una migración de datos por separado.

## 7. Activar Google y recuperación de contraseña

Para Google, crea un cliente OAuth web en Google Cloud y autoriza exactamente `https://TU-SERVICIO.onrender.com/auth/google/callback`. Añade GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET en Render. El script deriva GOOGLE_REDIRECT_URI de APP_URL cuando no se establece. Completa la pantalla de consentimiento y sus condiciones de publicación en Google.

La recuperación de contraseña requiere configurar un proveedor de correo SMTP: MAIL_MAILER=smtp, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_ENCRYPTION y MAIL_FROM_ADDRESS. Render Free bloquea puertos SMTP habituales 25/465/587 según su documentación; elige un proveedor que soporte un puerto permitido, como 2525, o integra un envío por API HTTPS. Con la configuración actual de log no se envían correos reales. No hace falta activar Google ni SMTP para probar el primer registro manual.

## Problemas frecuentes

- **Error instalando OPcache en PHP 8.5:** corregido en Dockerfile: PHP 8.5 ya lo incorpora, no se instala como módulo aparte.
- **Connection refused / timeout de MySQL:** revisa DB_HOST, DB_PORT y permisos de red en Aiven.
- **Error de certificado:** vuelve a descargar la CA de ese servicio Aiven y comprueba la conversión base64. No desactives SSL.
- **No application encryption key:** falta APP_KEY o no se copió completa.
- **Cloudinary no configurado:** revisa CLOUDINARY_URL; el formato no es una URL https de foto.
- **419 al entrar:** comprueba APP_URL HTTPS, SESSION_SECURE_COOKIE=true y que la tabla sessions se creó.
- **El primer acceso tarda:** el servicio Free puede estar despertando tras su suspensión por inactividad.

## Estado de esta preparación

El proyecto tiene archivos lock y configuración de despliegue. Docker no está instalado en el entorno de preparación: no se ejecutó una construcción local del contenedor. No se ha creado un repositorio remoto ni servicios en tus cuentas. El despliegue se verifica al finalizar el build y los pasos de comprobación en Render; un archivo Docker preparado no equivale a una web publicada.
