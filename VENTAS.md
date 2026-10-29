# Ventas en AlquilaHN — implementación y actualización

Se amplió el proyecto existente, sin cambiar rutas ni infraestructura. No se ejecutó push ni se accedió al servidor de producción.

## Qué cambió

- Menú: Alquileres, Casas en venta, Terrenos en venta y Vehículos en venta; se conservan Explorar e Ingresar. Publicar anuncio accede al flujo existente.
- Publicación: operación alquiler/venta y categorías casa, apartamento, cuarto, local comercial, terreno y vehículo. Terrenos y vehículos requieren venta. También se permite vender apartamentos, cuartos y locales.
- Terrenos: superficie positiva y unidad m²/v². Los filtros comparan superficies en la unidad elegida; no convierten unidades automáticamente.
- Vehículos: tipo, marca, modelo, año, kilometraje, transmisión, combustible y condición nuevo/usado. Conservan departamento, municipio, descripción, fotos, precio y WhatsApp; no exigen colonia ni dirección de inmueble.
- Los campos específicos se validan en servidor y se limpian cuando cambia la categoría, para no conservar datos de vehículos en inmuebles o superficies de terreno en otras categorías.
- Buscador original: operación y categoría, ubicación y presupuesto; filtros dinámicos para marca/modelo/año y superficie. Consultan properties y se conservan en URL y paginación. Marca y modelo se comparan como valores completos, con la sensibilidad a mayúsculas de la base configurada.
- Tarjetas, detalles y panel: etiqueta Alquiler/Venta, precio mensual solo en alquiler, detalles de terreno/vehículo y estado Vendido.
- Panel: editar, eliminar, desactivar con disponibilidad y marcar vendido. Volver a activar disponibilidad quita el estado vendido; también se puede editarlo. Se conservan consultas limitadas al propietario y policies.
- SEO: ventas con precio total y operación de venta; vehículos con información de producto y sin dirección inmobiliaria. URLs de detalles existentes /inmuebles/{id} se conservan para todas las categorías.

## Archivos

Modificados:

- app/Models/Property.php
- app/Livewire/ManageProperties.php
- app/Livewire/PropertyCatalog.php
- app/Support/Seo.php
- resources/views/layouts/app.blade.php
- resources/views/livewire/manage-properties.blade.php
- resources/views/livewire/property-catalog.blade.php
- resources/views/components/property-card.blade.php
- resources/views/properties/show.blade.php

Nuevos:

- database/migrations/2026_10_29_000001_add_sales_to_properties.php
- tests/Feature/SalesTest.php
- VENTAS.md

No se alteraron autenticación, rutas, políticas, servicio de imágenes, CSS/JavaScript fuente, dependencias, Dockerfile ni script de arranque.

## Base de datos y reversión

Se inspeccionaron las migraciones, modelos y relaciones. La tabla properties conserva sus filas e imágenes. type pasa de enum a string para admitir categorías nuevas; los valores se validan en servidor. Se añaden operation con default alquiler, is_sold con default false y campos opcionales específicos, más índices de catálogo de ventas y vehículo.

La migración down vuelve al esquema anterior cuando todas las publicaciones son alquileres de los tres tipos originales. Si existen ventas o categorías nuevas, se niega a revertir para evitar pérdida de información. No revertir la migración para volver temporalmente a la imagen anterior: esa versión puede no comprender tipos nuevos. Es preferible corregir hacia adelante o restaurar el respaldo completo de forma controlada.

La migración y su reversión se probaron en SQLite. El ALTER de MySQL y el despliegue Docker requieren comprobación en un entorno de ensayo con una copia de producción; no se han ejecutado aquí. En MySQL, los cambios de estructura pueden bloquear escrituras y no tienen la misma transaccionalidad que SQLite: programar una ventana de mantenimiento y respaldo verificado.

## Probar localmente (PowerShell)

```powershell
Set-Location E:\Proyectos\Alquiler
& C:\php85\php.exe artisan migrate
npm run build
& C:\php85\php.exe artisan test
& C:\php85\php.exe scripts/serve.php
```

El servidor de esta sesión ya está en http://127.0.0.1:8000; no iniciar otro en el mismo puerto. La migración local ya está aplicada. Se respaldó SQLite en storage/app/private/backups/before-sales-20261029-144933.sqlite, fuera del control de versiones. La copia local tenía cero anuncios.

1. Registrarse o ingresar y abrir Mis anuncios.
2. Crear un alquiler con fotos: comprobar sus campos anteriores y precio / mes.
3. Crear una casa en venta y comprobar que no muestra / mes.
4. Crear terreno con superficie y unidad; buscarlo por ubicación, precio y superficie en esa unidad.
5. Crear vehículo: comprobar campos dinámicos y buscar por marca/modelo completos, rango de año y precio.
6. Editar cada publicación sin volver a subir las fotos, desactivar/reactivar, marcar venta como vendida y consultar su detalle.
7. Ingresar con otra cuenta y comprobar que solo aparecen sus propios anuncios en el panel.
8. Revisar navegación y formularios en móvil, incluyendo subida de varias fotos y WhatsApp.

Verificación automatizada: 37 pruebas, 337 aserciones. Incluyen las 28 pruebas anteriores de autenticación, búsquedas, fotos, permisos y SEO; más creación de ventas en las tres categorías, edición, vendido, filtros, limpieza de campos, autorización y preservación/reversión de migración. Build Vite y Blade correctos. Inspección visual manual y prueba real de MySQL/Docker pendientes.

## Preparar GitHub (no ejecutado)

Revisar la rama actual y el remoto antes de enviar. Estos comandos crean una rama de trabajo y suben solo los archivos del cambio:

```powershell
git status --short
git switch -c feature/anuncios-venta
git add app/Models/Property.php app/Livewire/ManageProperties.php app/Livewire/PropertyCatalog.php app/Support/Seo.php resources/views/layouts/app.blade.php resources/views/livewire/manage-properties.blade.php resources/views/livewire/property-catalog.blade.php resources/views/components/property-card.blade.php resources/views/properties/show.blade.php database/migrations/2026_10_29_000001_add_sales_to_properties.php tests/Feature/SalesTest.php VENTAS.md
git diff --cached --check
git diff --cached --stat
git commit -m "Agregar anuncios de venta de casas terrenos y vehiculos"
git push -u origin feature/anuncios-venta
```

Integrar la rama mediante revisión antes de actualizar la rama que usa producción. No incluir .env, SQLite, respaldos ni fotos en Git.

## Actualización posterior de Docker sin perder datos (no ejecutada)

El repositorio local no incluye un compose de producción; los nombres de servicios, montajes y rama deben obtenerse de la configuración real existente. No sustituirla con un compose inventado. Los comandos siguientes son una plantilla para el operador, desde el directorio de despliegue ya configurado. Si se usa docker run en lugar de Compose, conservar exactamente los mounts, redes, variables, puertos y política de reinicio del contenedor actual.

1. Probar primero en un entorno aislado con una copia restaurada de MySQL y fotos. Confirmar que el nuevo contenedor conserva APP_KEY, APP_URL, credenciales y los mismos volúmenes. El Dockerfile y 00-laravel-deploy.sh actuales se mantienen, incluido migrate --force durante el arranque y sus requisitos de Cloudinary/MySQL/TLS.
2. Identificar el servicio de aplicación y de base de datos. Si MySQL es externo, hacer el respaldo con las herramientas y conexión existentes del proveedor en lugar del ejemplo de servicio db.

```bash
docker compose config --services
# Establecer nombres REALES; app y db son ejemplos.
APP_SERVICE=app
DB_SERVICE=db
APP_CONTAINER=$(docker compose ps -q "$APP_SERVICE")
docker inspect "$APP_CONTAINER" --format '{{json .Mounts}}'
```

Verificar persistencia de MySQL y, si hay fotos locales, storage/app/public y el enlace public/storage. Si las fotos están en la capa efímera del contenedor, copiarlas y configurar su persistencia antes de recrearlo. Con Cloudinary, conservar la misma cuenta y configuración; no borrar recursos remotos. No continuar hasta tener respaldo verificable y montajes persistentes.

3. Mantener la imagen anterior, poner el portal en mantenimiento y respaldar datos/fotos. Ejecutar en una terminal del servidor, no en el contenedor:

```bash
STAMP=$(date +%Y%m%d-%H%M%S)
mkdir -p "backups/$STAMP"
chmod 700 "backups/$STAMP"
docker image tag "$(docker inspect "$APP_CONTAINER" --format '{{.Image}}')" "alquilahn:before-sales-$STAMP"
docker compose exec -T "$APP_SERVICE" php artisan down --retry=60
# Ejemplo para contenedor MySQL con MYSQL_USER/MYSQL_PASSWORD/MYSQL_DATABASE definidos.
# Usar el usuario de respaldo configurado si esas variables no corresponden.
docker compose exec -T "$DB_SERVICE" sh -c 'exec mysqldump --single-transaction --no-tablespaces -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' > "backups/$STAMP/database.sql"
docker compose exec -T "$APP_SERVICE" tar -czf - -C /var/www/html storage/app/public > "backups/$STAMP/photos.tar.gz"
# Si esa carpeta no existe porque todas las fotos están en Cloudinary, respaldar y verificar allí.
test -s "backups/$STAMP/database.sql"
tar -tzf "backups/$STAMP/photos.tar.gz" > /dev/null
```

Comprobar códigos de salida y restaurar el dump en la base de ensayo para verificarlo. Respaldar también la configuración de despliegue/.env de forma privada y conservar APP_KEY; no mostrar ni subir secretos. Si hay otros procesos que escriben en la base, pausarlos durante el respaldo coherente.

4. Actualizar a la revisión aprobada usando la rama real de despliegue y recrear solo la aplicación. Los valores entre <...> deben sustituirse antes de ejecutar:

```bash
git status --short
# Continuar solo con un checkout limpio.
git fetch origin
git pull --ff-only origin <rama-de-produccion>
docker compose build "$APP_SERVICE"
docker compose up -d --no-deps "$APP_SERVICE"
docker compose logs --tail=100 "$APP_SERVICE"
docker compose exec -T "$APP_SERVICE" php artisan migrate:status
docker compose exec -T "$APP_SERVICE" php artisan view:cache
docker compose exec -T "$APP_SERVICE" php artisan up
```

El arranque existente aplica la migración antes de servir; comprobar que termina correctamente. No usar down -v, volume rm, migrate:fresh, db:wipe ni eliminar contenedores de base de datos/volúmenes. Los volúmenes y fotos deben conservar sus mismos destinos y propietarios.

5. Verificar portada, anuncios antiguos con sus fotos, registro/login, panel, WhatsApp, búsquedas y una publicación de cada categoría. Revisar logs. No modificar Ubuntu, SSH, UFW, Fail2ban, Tailscale ni Cloudflare Tunnel.
6. Si falla, mantener mantenimiento mientras se diagnostica. Antes de aceptar publicaciones nuevas puede restituirse la imagen anterior y revertir únicamente esta migración tras comprobar su precondición. Si ya existen ventas/categorías nuevas, no ejecutar rollback ni arrancar el código anterior sobre esos datos: corregir hacia adelante o coordinar restauración íntegra de base/fotos/imagen, preservando aparte todas las escrituras posteriores al respaldo.
