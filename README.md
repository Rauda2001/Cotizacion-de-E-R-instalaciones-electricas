# E&R Instalaciones Eléctricas

Sistema web en PHP puro para administrar clientes, catálogos y cotizaciones de instalaciones eléctricas residenciales. Incluye autenticación, gestión de clientes y conceptos, cotizaciones con materiales y mano de obra, historial, cálculo sin impuestos automáticos y exportación PDF.

Consulta [DOCUMENTACION.md](DOCUMENTACION.md) para ver la guía de instalación, el manual de uso, el modelo de datos y las recomendaciones de mantenimiento.

## Arquitectura

Aplicación PHP sin framework de backend:

- `public/`: páginas y recursos estáticos.
- `app/`: inicialización, autenticación, validaciones compartidas, lógica de catálogos y vistas.
- `config/`: configuración central de la conexión y la aplicación.
- `database/`: esquema completo de MySQL.
- `vendor/`: dependencias instaladas por Composer, incluido Dompdf.

Las páginas usan PDO y consultas preparadas. La conexión, sesiones, escape HTML, tokens CSRF y funciones compartidas se centralizan en `app/`. Las carpetas internas tienen reglas Apache para impedir su acceso HTTP directo.

```text
er_instalaciones/
|-- app/
|   |-- auth.php
|   |-- bootstrap.php
|   |-- catalog.php
|   |-- helpers.php
|   `-- views/
|-- config/config.php
|-- database/schema.sql
|-- composer.json
|-- public/
|   |-- assets/css/styles.css
|   |-- assets/js/quote-form.js
|   |-- clients.php
|   |-- dashboard.php
|   |-- index.php
|   |-- login.php
|   |-- logout.php
|   |-- materials.php
|   |-- quote_form.php
|   |-- quote_pdf.php
|   |-- quote_view.php
|   |-- quotes.php
|   |-- services.php
|-- vendor/ (dependencias de Composer)
|-- DOCUMENTACION.md
`-- README.md
```

## Base de datos y cálculo

`database/schema.sql` crea `usuarios`, `clientes`, `materiales`, `servicios`, `cotizaciones`, `cotizacion_folio_secuencias`, `cotizacion_materiales` y `cotizacion_servicios` en una sola base. Una cotización referencia al cliente y al usuario que la creó. Sus detalles guardan copias del nombre, descripción, unidad y precio usados, por lo que los cambios posteriores a los catálogos no alteran cotizaciones históricas.

Cada renglón se calcula como `cantidad × precio unitario`. Se redondea a dos decimales por concepto; el total suma materiales y servicios/mano de obra. Se usan columnas `DECIMAL`, sin agregar impuestos automáticamente. Al editar una cotización, las partidas conservan sus precios históricos si se mantiene el mismo elemento; al cambiar el concepto se toma su precio vigente.

El PDF con Dompdf organiza los datos del cliente y materiales, la mano de obra/servicios y el resumen de totales en secciones separadas. Desde el detalle de la cotización se puede elegir si mostrar los precios de materiales; al ocultarlos, el PDF indica que los aporta el cliente y calcula el total únicamente con mano de obra y servicios. El historial permite buscar por folio, cliente y estado.

Los folios de las nuevas cotizaciones siguen el formato `E&R-AAAA-NNNN`, con un correlativo independiente por año que comienza en `0001`. La tabla `cotizacion_folio_secuencias`, incluida en el esquema principal, evita repetir números al duplicar cotizaciones o si se elimina una. Las cotizaciones anteriores conservan sus folios originales. Al actualizar una base existente, crea únicamente esta tabla usando su definición en `database/schema.sql`; no vuelvas a importar el esquema completo sobre una base con datos.

## Seguridad básica

- PDO con consultas preparadas, excepciones y preparaciones nativas.
- Contraseñas con `password_hash()` y `password_verify()`.
- Regeneración del identificador de sesión y cookies `HttpOnly`, `SameSite=Lax` y `Secure` cuando se usa HTTPS.
- Tokens CSRF en formularios que modifican datos y escape HTML al mostrar contenido.
- Validación en servidor de fechas, cantidades y precios del catálogo.
- Los catálogos se desactivan en vez de eliminarse, para no perder referencias históricas.
- El proyecto no incluye una pantalla web para registrar usuarios ni recuperar contraseñas; las cuentas se administran directamente en la base de datos.
- En producción, habilita HTTPS, configura una contraseña MySQL y evita mostrar errores PHP al usuario.

## Instalación en XAMPP

1. Copia `er_instalaciones` dentro de `C:\xampp\htdocs\`.
2. Inicia Apache y MySQL desde el panel de XAMPP.
3. En `http://localhost/phpmyadmin/`, crea una base vacía, selecciónala e importa `database/schema.sql`. Asegúrate de que el nombre coincida con `database.name` en `config/config.php`. El archivo contiene sentencias `INSERT`; revísalo antes de importar si no quieres cargar esos registros.
4. Revisa `config/config.php`; la configuración inicial usa `root` sin contraseña, habitual en XAMPP local.
5. Abre una consola en `C:\xampp\htdocs\er_instalaciones\` y ejecuta `composer install` para instalar Dompdf. Si no tienes Composer, instálalo desde `https://getcomposer.org/download/`.
6. Asegúrate de que exista una cuenta activa en la tabla `usuarios`; el proyecto no incluye una pantalla web para crear usuarios.
7. Entra al sistema en `http://localhost/er_instalaciones/`; Apache redirige automáticamente a `public/`.

La extensión `pdo_mysql` debe estar habilitada en PHP de XAMPP. Si aparece que falta el controlador, habilita `extension=pdo_mysql` en el `php.ini` que usa Apache y reinícialo.
La redirección requiere que Apache tenga habilitado `mod_rewrite` y permita las reglas de `.htaccess` en `htdocs`.

## Uso

1. Agrega clientes desde **Clientes**.
2. Registra materiales y servicios/mano de obra desde **Catálogos**. Puedes desactivar elementos que ya no uses.
3. Crea cotizaciones combinando conceptos de ambos catálogos; al elegir cada concepto, indica su cantidad en el cuadro que aparece. Si vuelves a elegir un artículo ya agregado, puedes actualizar su cantidad sin duplicar el renglón.
4. Busca las cotizaciones anteriores, consulta el detalle, edita o elimina y descarga el PDF. Para completar una cotización guardada, ábrela y elige **Editar / agregar artículos**; agrega los conceptos faltantes y selecciona **Guardar cambios** para actualizarla conservando el mismo folio. Para conservar la original y preparar otra propuesta similar, elige **Duplicar cotización**; la copia conserva las partidas y sus precios históricos, recibe el siguiente folio correlativo único y queda como borrador para editarla.

El precio de una cotización se valida y copia desde la base de datos al guardar; el navegador solo muestra un resumen preliminar. El total definitivo se calcula a partir de los detalles guardados y no agrega impuestos automáticamente.
