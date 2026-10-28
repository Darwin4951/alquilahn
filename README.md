# AlquilaHN

Portal de alquileres en Honduras con PHP 8.5 (mínimo 8.5.12), Laravel 12, Livewire 3, Tailwind 3, MySQL/SQLite, Socialite y Cloudinary. El catálogo y el detalle son públicos; publicar y gestionar anuncios requiere una cuenta.

## Entregables en el orden solicitado

El código está en los archivos del proyecto. [ENTREGABLES.md](ENTREGABLES.md) reúne el código completo de los cinco bloques en el orden solicitado, con los archivos auxiliares necesarios.

1. `database/migrations/`, `app/Models/User.php`, `app/Models/Property.php`.
2. `app/Http/Controllers/Auth/`, `app/Http/Requests/Auth/LoginRequest.php`, `routes/web.php`, `routes/auth.php`, `resources/views/auth/`.
3. `app/Livewire/PropertyCatalog.php`, `resources/views/livewire/property-catalog.blade.php`, `resources/views/components/property-card.blade.php` y `resources/views/properties/show.blade.php`.
4. `app/Livewire/ManageProperties.php`, su vista, `app/Policies/PropertyPolicy.php` y `app/Services/PropertyImages.php`.
5. `app/Providers/AppServiceProvider.php`, `Dockerfile`, `00-laravel-deploy.sh`, `docker/php.ini` y `render.yaml`.

## Estado y límites

Estado actualizado: PHP 8.5.12 y Laravel 12.70.2 instalados. Composer completa el descubrimiento de paquetes. Se crearon .env y SQLite locales, se aplicaron las migraciones y pasaron 28 pruebas con 257 aserciones. El build frontend y la compilación de vistas Blade también se verificaron. Docker y las conexiones reales Google/Cloudinary/Aiven siguen pendientes de verificar. Composer crea los directorios temporales antes de arrancar Laravel mediante scripts/prepare-directories.php.

El proyecto se actualizó a **Laravel 12**, que declara compatibilidad con PHP 8.5 y es compatible con Cloudinary Laravel 3.x ([calendario oficial](https://laravel.com/docs/12.x/releases), [dependencias de Cloudinary](https://github.com/cloudinary-community/cloudinary-laravel/blob/master/composer.json)). Composer exige PHP **8.5.12 o un parche posterior de la rama 8.5**. Docker y CI usan la rama 8.5. Las opciones TLS de MySQL usan las constantes modernas `Pdo\Mysql::ATTR_SSL_*`. No se ha desactivado el bloqueo de dependencias vulnerables de Composer.

El coste cero depende de las cuotas y condiciones vigentes de cada proveedor. [Render Free](https://render.com/docs/free) suspende el servicio tras 15 minutos sin actividad y usa disco efímero; el primer acceso puede tardar. No se usan discos de pago, Redis, workers ni cron. [Aiven MySQL Free](https://aiven.io/docs/products/mysql/concepts/mysql-free-tier) y [Cloudinary Free](https://cloudinary.com/pricing) tienen límites de almacenamiento, cómputo y transferencia. Revisa su panel y evita habilitar planes de pago por accidente. No existe una garantía de gratuidad ilimitada.

## Instalación local — PowerShell

Requisitos: PHP **8.5.12 o posterior de la rama 8.5** con pdo_sqlite, pdo_mysql, mbstring, intl, zip, openssl, fileinfo y gd (para pruebas de imágenes), Composer 2 y Node 22. La terminal de esta revisión todavía resuelve PHP 8.2.12; activa tu PHP 8.5 en PATH antes de instalar dependencias. No uses `--ignore-platform-reqs`.

```powershell
Copy-Item .env.example .env
$folders = @('bootstrap/cache','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/app/private','storage/logs')
foreach ($folder in $folders) { New-Item -ItemType Directory -Force $folder | Out-Null }
New-Item -ItemType File -Force database/database.sqlite | Out-Null
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan test
composer run dev
```

Abre `http://localhost:8000`. Para desarrollar estilos, ejecuta `npm run dev` en otra terminal. No ejecutes `breeze:install` encima de estas vistas: las sobrescribiría. La autenticación manual incluye controladores y LoginRequest con el patrón Breeze Blade, registro, sesiones, throttling, CSRF, cierre de sesión y recuperación de contraseña; Breeze está declarado como dependencia de desarrollo. Su código ya está incorporado y adaptado en el proyecto.

El catálogo vacío es intencional: registra una cuenta y crea un anuncio con fotos reales. Las subidas necesitan credenciales Cloudinary incluso en desarrollo. Los tests sustituyen Cloudinary y usan SQLite en memoria.

## Google

En Google Cloud Console configura la pantalla de consentimiento y crea un cliente OAuth de tipo aplicación web. Registra exactamente los callbacks:

```text
http://localhost:8000/auth/google/callback
https://TU-SERVICIO.onrender.com/auth/google/callback
```

Define `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` y `GOOGLE_REDIRECT_URI` en cada entorno. El botón se muestra cuando hay client ID. Mientras la aplicación Google esté en modo de pruebas, añade los correos de prueba correspondientes.

Socialite valida el estado OAuth y exige correo verificado. Una cuenta Google nueva se crea sin contraseña; puede establecer una mediante recuperación por correo. Un correo ya registrado manualmente no se vincula automáticamente a Google; debe entrar con contraseña o recuperarla. No se almacenan access tokens ni refresh tokens. Configura SMTP para que los enlaces de recuperación se entreguen: `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`. Con `MAIL_MAILER=log` se registran para desarrollo, no se envían.

## Cloudinary

Configura en `.env` o Render:

```dotenv
CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME
```

Las credenciales son exclusivamente del servidor. Se usa el SDK de Cloudinary mediante el binding que registra `cloudinary-labs/cloudinary-laravel` 3.x y la configuración `filesystems.disks.cloudinary`. No necesitas un preset unsigned ni un disco persistente. Se suben imágenes a `alquilahn/properties` y se guardan solo sus URLs HTTPS en `properties.images`.

Hay de 1 a 8 fotos por anuncio, hasta 5 MB por archivo, JPG/PNG/WebP. Livewire usa archivos temporales locales durante la petición; Cloudinary es el destino permanente. Las fotos quitadas en edición se borran solo después de guardar. Los fallos de subida intentan limpiar las fotos nuevas; los fallos al borrar en Cloudinary se registran. Un corte abrupto del proceso puede dejar archivos huérfanos: revisa periódicamente la carpeta de Cloudinary. JSON almacena URLs, así que el servicio deriva el public ID únicamente de las URLs de la carpeta propia para limpiar.

## Render + Aiven

1. Crea una base MySQL Free en Aiven. Copia host, **puerto indicado por Aiven**, usuario, contraseña y nombre de base. Descarga su certificado CA. Autoriza las conexiones salientes de la región Render en los controles de red de Aiven, si aplican.
2. Codifica el certificado en PowerShell: `[Convert]::ToBase64String([IO.File]::ReadAllBytes('C:\ruta\ca.pem'))`. Copia el resultado a `AIVEN_CA_CERT_BASE64` en Render; no lo incluyas en Git. La conexión exige CA y verifica el certificado del servidor.
3. Genera una clave una sola vez con `php artisan key:generate --show`; guárdala como `APP_KEY` en Render. Debe conservarse entre reinicios y despliegues.
4. Publica el código con sus archivos lock en tu repositorio Git. Crea un servicio Render mediante Blueprint (`render.yaml`) o Web Service → Docker → Free. No se ha creado ningún servicio ni recurso externo desde este entorno.
5. Define todas las variables solicitadas por `render.yaml`. `APP_URL` debe ser el URL HTTPS real; `GOOGLE_REDIRECT_URI` debe coincidir con el callback autorizado en Google. Usa `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_DRIVER=database`, `DB_CONNECTION=mysql` y `CACHE_STORE=file`.
6. El contenedor compila Tailwind/Vite, instala PHP 8.5 con Apache, crea los directorios y arranca en `$PORT`. El script valida configuración, materializa la CA, cachea configuración/vistas y ejecuta migraciones antes de aceptar tráfico. El health check es `/up`.
7. Verifica registro manual, login Google, subida/edición/eliminación de fotos, filtros, paginación, WhatsApp y acceso desde otra cuenta. Reinicia el servicio y confirma que los anuncios y sesiones permanecen en MySQL y las fotos en Cloudinary.

No generes APP_KEY en cada arranque. No uses SQLite en producción Render: se perderían los datos. El script falla si falta la configuración necesaria; no arranca silenciosamente sobre otra base. Las migraciones se ejecutan al inicio porque el plan gratuito no ofrece la misma etapa pre-deploy de los planes de pago. Para este esquema inicial son aditivas; revisa futuras migraciones destructivas y haz respaldo antes de aplicarlas.

Prueba Docker en un equipo con Docker instalado y usa un archivo privado `.env.render.local` con variables de producción:

```powershell
docker build -t alquilahn .
docker run --rm --env-file .env.render.local -p 10000:10000 alquilahn
```

## Comportamiento y consultas

Los filtros se combinan por AND, y la búsqueda por colonia/dirección/título usa un grupo OR con bindings. La disponibilidad es activa por defecto. La paginación tiene 12 anuncios, orden estable por ID y reinicio al pulsar Buscar inmuebles; los valores quedan en la URL. Se valida el rango de precios y se escapan `%` y `_` para que sean texto literal. Se seleccionan solo columnas necesarias y no se consulta el propietario por tarjeta.

Los índices ayudan a ciudad y filtros de igualdad/rango. Una búsqueda de subcadena `%texto%` no aprovecha un B-tree para evitar escanear texto; si el catálogo crece, mide con EXPLAIN y considera búsqueda FULLTEXT con una implementación específica para MySQL. El catálogo incluye 18 departamentos y 298 municipios, con selectores relacionados y validación de pertenencia en el servidor. Tegucigalpa y Comayagüela se ofrecen como ciudades adicionales del Distrito Central, sin sumarlas al total municipal.

Las mutaciones se resuelven desde la relación del usuario autenticado y la policy; no se acepta `user_id` desde el formulario. Los IDs de edición y URLs existentes están bloqueados con `#[Locked]`. El cambio de disponibilidad es un UPDATE atómico. El detalle permite ver dirección, descripción, todas las fotos y WhatsApp sin login, incluso cuando el anuncio está alquilado.

## Validación

`php artisan test` cubre acceso público, filtros combinados, disponibilidad, edición, acceso cruzado, validación de WhatsApp/fotos, rollback ante fallo de subida, registro/login/logout y prevención de vinculación Google por correo. Resultado local: 28 pruebas aprobadas y 257 aserciones con PHP 8.5.12. El workflow usa PHP 8.5.

## Departamentos, ciudades y municipios

Los datos están en `config/honduras-regions.php`, basados en los [perfiles municipales UNAH / INE / AMHON](https://oee.unah.edu.hn/lineas-de-investigaci/desarrollo-economico-con-enfoque-territorial/psmh/), con nombres usuales actuales como Cantarranas y Villanueva. `App\Support\Honduras` relaciona las opciones y agrega Tegucigalpa y Comayagüela. El campo `city` guarda la ciudad o municipio seleccionado y `department` evita confundir nombres repetidos.

La migración `2026_10_28_000001_add_department_to_properties.php` se aplica con `php artisan migrate`. Conserva los anuncios existentes y asigna departamento solo si la ubicación es inequívoca. Los anuncios con ubicaciones ambiguas siguen visibles en el catálogo general; el propietario debe elegir departamento al editarlos para que aparezcan bajo ese filtro. Los nuevos anuncios requieren departamento y una ciudad o municipio de ese departamento.

## Buscador unificado

Un solo formulario combina palabras clave, departamento, ciudad, colonia, tipo, presupuesto y disponibilidad mediante `searchAll`. Buscar o Enter aplica todos los valores juntos, sin recargar; un error conserva la búsqueda anterior. La URL guarda los filtros aplicados. Las acciones anteriores siguen disponibles para compatibilidad.

## Palabras clave

La barra permite frases como **apartamento UNAH** junto con los filtros. Cada palabra debe coincidir en tipo, título, descripción, colonia, dirección, ciudad o departamento; pueden aparecer en campos distintos. UNAH debe figurar en los datos del anuncio: no se realiza geolocalización. La URL conserva la frase en `q`.

## Layout editorial del catálogo

Hero fotográfico con Unsplash y overlay, buscador horizontal flotante de un solo formulario, categorías rápidas y listados a ancho completo en 1/2/3/4 columnas. En móviles el formulario se apila. Sin sidebar ni ilustración arquitectónica. Inter, fondo #FAF9F6 y acentos #0F382F / #D97706. El estado vacío muestra un aviso compacto y cuatro tarjetas de zonas con fotografías de referencia, sin inventar anuncios. Las categorías Locales comerciales y Amueblados buscan palabras en los anuncios actuales; no agregan nuevos tipos al esquema. Fuentes Google Fonts y fotos Unsplash requieren acceso a Internet. Validación: 28 pruebas (257 aserciones), build Vite y compilación Blade correctos. Inspección visual en navegador pendiente por falta de conexión a la herramienta.

## Varias fotos por anuncio

Selecciona varias fotos juntas (Ctrl/Shift en Windows) o agrega otras en selecciones sucesivas: las nuevas se acumulan sin reemplazar las anteriores. El formulario muestra vistas previas, contador y botones para quitar fotos antes de guardar. Admite hasta 8 fotos en total, contando las existentes al editar, de hasta 5 MB cada una.

Para desarrollo usa `composer run dev`, que ejecuta `scripts/serve.php` con upload_max_filesize=6M, post_max_size=48M y max_file_uploads=8. Deten el servidor anterior con Ctrl+C antes de arrancarlo. En local, si CLOUDINARY_URL esta vacia, las fotos se guardan en `storage/app/public/properties` y se sirven desde `public/storage`; no necesitas Cloudinary para probar. En produccion se requiere Cloudinary para conservar fotos fuera del disco efimero de Render.

## SEO técnico y local

`App\Support\Seo` genera title, descripción, canonical, Open Graph, Twitter Cards y JSON-LD. El catálogo adapta título y H1 a ciudad, departamento y tipo aplicados; cada detalle tiene metadatos y foto propios. La descripción de portada tiene 160 caracteres (se omite "toda" de la propuesta original de 165). Las canónicas conservan filtros significativos y página, excluyendo vacíos y parámetros de seguimiento. La paginación mantiene los filtros en enlaces HTML rastreables. Un evento Livewire `seo-updated` actualiza los metadatos del head mediante resources/js/app.js. El HTML inicial de cada URL contiene los metadatos completos para rastreadores y vistas previas sin JavaScript.

El JSON-LD incluye WebSite/SearchAction, Organization con cobertura Honduras, ItemList de los anuncios visibles y RealEstateListing con Offer, precio HNL mensual, disponibilidad y alojamiento con dirección. El portal no se declara como agencia ni inventa oficina, reseñas o coordenadas. Se codifica JSON con escape de etiquetas para evitar cerrar el script desde contenido del anuncio. Google [retiró el Sitelinks Search Box en noviembre de 2024](https://developers.google.com/search/blog/2024/10/sitelinks-search-box); SearchAction sigue como descripción semántica solicitada, sin prometer ese resultado enriquecido. El marcado inmobiliario tampoco garantiza un rich result.

El footer agrega enlaces por ciudad, colonia y tipo usando las rutas y parámetros reales del catálogo. Las páginas públicas válidas llevan index, follow; los formularios privados y de autenticación llevan noindex, follow. La imagen hero tiene eager/high priority; las tarjetas usan lazy/async y dimensiones explícitas. No se ha medido Lighthouse ni rendimiento de campo.

### Rastreo y puesta en producción

- `/robots.txt` es una ruta dinámica, permite catálogo y anuncios y excluye administración, autenticación, panel y endpoints internos. No requiere crear un archivo estático en public.
- `/sitemap.xml` es el índice. Incluye `/sitemaps/locations.xml` (portada, tipos, 18 departamentos y sus ciudades/municipios) y `/sitemaps/properties-N.xml` con lotes de 1000 anuncios. Incluye anuncios alquilados porque sus detalles siguen siendo públicos, con disponibilidad OutOfStock. Los lastmod de anuncios provienen de updated_at.
- Define **APP_URL** con el dominio HTTPS definitivo antes de publicar y ejecuta `php artisan config:cache`. Canonical, Schema y sitemap usan ese origen configurado, evitando depender del Host de una petición.
- Puedes definir **SEO_OG_IMAGE** con una imagen HTTPS propia de 1200x630; si está vacío se usa la foto predeterminada. Los anuncios usan su primera foto real.
- Verifica el dominio en Google Search Console y envía `/sitemap.xml`. [Google describe el sitemap como una señal, no una garantía de indexación](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap). Valida el marcado en validator.schema.org y prueba las vistas previas sociales al desplegar.

Validación local: 28 pruebas y 257 aserciones, incluyendo metadata filtrada, URLs limpias, paginación, seguridad del JSON-LD, precios/disponibilidad, XML y robots. Compilación Vite y Blade correcta. Inspección en navegador y verificación externa de Google/WhatsApp pendientes.

## Publicar gratis

Consulta [PUBLICAR.md](PUBLICAR.md) para el recorrido guiado GitHub, Aiven, Cloudinary y Render Free. El script de arranque toma APP_URL de RENDER_EXTERNAL_URL si no se define y deriva el callback Google; las credenciales Google son opcionales en el primer despliegue. Dockerfile omite instalar OPcache por separado porque PHP 8.5 ya lo incluye. Apache limita su concurrencia para la memoria de la instancia gratuita. Build Docker no ejecutado localmente: este entorno no tiene Docker.

## Corrección del estado de carga de fotos

El selector de archivos mantiene una key estable durante las selecciones. Antes se reemplazaba al procesar la subida, lo que podía dejar el evento de finalización en un elemento desconectado y bloquear Publicar. El estado Alpine vive en la raíz del componente y escucha tanto los eventos nativos de carga como photos-processed del servidor, incluyendo rechazos de validación y exceso de fotos. El input se limpia al vaciar pendingPhotos sin sustituir el nodo. Las pruebas verifican las confirmaciones de éxito/error y el selector estable. Total: 28 pruebas y 257 aserciones.

En desarrollo local, deja CLOUDINARY_URL vacia para guardar fotos localmente. En produccion configura CLOUDINARY_URL con credenciales reales en el entorno del servicio. Nunca publiques credenciales en Git ni en el chat.
