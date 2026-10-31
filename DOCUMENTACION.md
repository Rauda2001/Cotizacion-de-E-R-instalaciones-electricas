# Documentacion del sistema

## 1. Descripcion

E&R Instalaciones Electricas es una aplicacion web para administrar clientes, catalogos y cotizaciones de instalaciones electricas. Permite generar propuestas con materiales y mano de obra/servicios, consultar y actualizar cotizaciones guardadas, duplicarlas y descargar o compartirlas en PDF.

La aplicacion esta desarrollada en PHP 8 y utiliza MySQL/MariaDB mediante PDO. Dompdf genera los documentos PDF. La interfaz se sirve desde `public/`; Apache redirige a esa carpeta desde la raiz del proyecto.

## 2. Requisitos

- Windows con XAMPP, o un servidor equivalente con Apache y PHP 8.
- MySQL o MariaDB.
- Extensiones de PHP `pdo_mysql` y las requeridas por Dompdf.
- Composer para instalar las dependencias definidas en `composer.json`.
- Apache con `mod_rewrite` y permisos para procesar archivos `.htaccess`.
- Navegador web actualizado.

## 3. Instalacion en XAMPP

### Instalacion nueva

1. Copia la carpeta `er_instalaciones` en `C:\xampp\htdocs\`.
2. Inicia Apache y MySQL en el panel de XAMPP.
3. En phpMyAdmin, crea y selecciona una base de datos vacia. Usa una codificacion compatible con `utf8mb4`.
4. Importa `database/schema.sql` en esa base. El archivo define el esquema y contiene sentencias `INSERT`; revisa su contenido antes de importarlo si no quieres cargar esos registros.
5. Edita `config/config.php` para que `name`, `host`, `port`, `username` y `password` correspondan a tu servidor MySQL.
6. Desde PowerShell o una consola, abre la carpeta del proyecto y ejecuta:

   ```powershell
   composer install
   ```

7. Confirma que la extension `pdo_mysql` esta habilitada en el `php.ini` utilizado por Apache y reinicia Apache si la habilitaste.
8. Abre `http://localhost/er_instalaciones/`.
9. Inicia sesion con una cuenta que exista en la tabla `usuarios`.

La aplicacion no incluye un formulario de registro, recuperacion de contrasena ni asistente de instalacion para crear usuarios. El esquema puede incluir un registro inicial en `usuarios`; confirma con la persona responsable que las credenciales sean las correctas y cambia o reemplaza la cuenta antes de usar el sistema en produccion. Nunca guardes contrasenas en texto plano. Si Apache no aplica la redireccion, verifica que `mod_rewrite` este activo y que `AllowOverride` permita las reglas `.htaccess`.

### Actualizacion de una base existente

- Haz una copia de seguridad antes de cualquier cambio.
- No importes el `schema.sql` completo sobre una base que ya tiene informacion: el archivo crea tablas e incluye datos, y no es un script de migracion.
- Si la base solo necesita la secuencia anual de folios, ejecuta una sola vez la definicion de `cotizacion_folio_secuencias` que aparece en `database/schema.sql`, despues de comprobar que la tabla no exista.
- Mantén la configuracion de la aplicacion apuntando a la misma base que seleccionaste en phpMyAdmin. La base configurada se determina en `config/config.php`, no por el comentario dentro del volcado SQL.

## 4. Acceso y navegacion

Las paginas de gestion requieren una sesion iniciada. Despues de ingresar, el panel permite abrir:

- **Clientes:** directorio, busqueda, alta y edicion de datos de contacto.
- **Materiales:** insumos, unidades, descripciones y precios unitarios.
- **Servicios:** conceptos de mano de obra y servicios, con sus unidades y precios.
- **Cotizaciones:** creacion, busqueda, detalle, edicion, duplicacion, exportacion y eliminacion.

El catalogo incluye elementos activos e inactivos. Desactivar un elemento evita que se use en nuevas cotizaciones, pero no borra el registro ni los datos historicos de propuestas anteriores.

## 5. Administracion de clientes y catalogos

### Clientes

1. Abre **Clientes** y selecciona **Agregar cliente**.
2. Ingresa el nombre y, si se conocen, telefono, correo, direccion y notas.
3. Guarda los datos. Puedes buscar clientes por nombre, correo o telefono y editar su ficha posteriormente.
4. Un cliente asociado a cotizaciones no se puede eliminar; edita sus datos para conservar las referencias historicas.

### Materiales y servicios

1. Abre **Catalogos** y elige **Materiales** o **Servicios**.
2. Registra el nombre, unidad y precio unitario. La descripcion es opcional.
3. Edita el elemento cuando cambie la informacion vigente.
4. Desactiva elementos que ya no se deban ofrecer. Las cotizaciones guardan una copia de su descripcion, unidad y precio para que los cambios en el catalogo no modifiquen propuestas anteriores.

Los precios aceptan hasta dos decimales y pueden ser cero. Las cantidades de las cotizaciones admiten hasta tres decimales.

## 6. Crear y administrar cotizaciones

### Crear

1. Abre **Cotizaciones** y selecciona **Nueva cotizacion**.
2. Selecciona el cliente, la fecha y agrega notas si se requieren.
3. Busca un material o servicio por nombre y selecciona una coincidencia. Puedes desplazarte por las coincidencias con las flechas del teclado y confirmar la seleccion.
4. Introduce la cantidad en el cuadro que aparece. Si el mismo concepto ya esta agregado, puedes actualizar su cantidad sin crear otro renglon.
5. Repite el proceso con los conceptos necesarios. El formulario muestra un resumen preliminar.
6. Guarda la cotizacion. El servidor valida los datos y calcula los importes usando los precios almacenados en la base.

La vigencia se calcula a 30 dias a partir de la fecha de la cotizacion. La aplicacion no agrega impuestos automaticamente.

### Completar o editar

1. Busca la cotizacion en el historial y abre su detalle.
2. Selecciona **Editar / agregar articulos**.
3. Agrega los renglones que faltaban, cambia cantidades o elimina los que ya no correspondan.
4. Selecciona **Guardar cambios**. La cotizacion conserva su folio.

Al mantener el mismo concepto al editar, se conserva su precio historico. Si se reemplaza por otro concepto, se utiliza el precio vigente de ese elemento.

### Duplicar

Desde el detalle, selecciona **Duplicar cotizacion**. La copia conserva cliente, notas, conceptos, cantidades y precios historicos; recibe la fecha actual y un folio nuevo. Puedes modificar la copia sin alterar la cotizacion de origen.

### Buscar y eliminar

El historial permite buscar por folio o cliente y filtrar por estado. Desde cada resultado puedes abrir el detalle o eliminar la cotizacion. La eliminacion requiere confirmacion y tambien elimina sus partidas relacionadas; esta accion no se puede deshacer, por lo que conviene descargar o respaldar la informacion necesaria primero.

## 7. Folios y calculos

Las cotizaciones nuevas y duplicadas reciben folios con el formato `E&R-AAAA-NNNN`, por ejemplo `E&R-2026-0001`. El correlativo empieza en `0001` cada año y se incrementa para las nuevas cotizaciones. La tabla `cotizacion_folio_secuencias` conserva la secuencia; el bloqueo de base de datos evita asignar el mismo numero a dos operaciones simultaneas. Los folios de cotizaciones anteriores no se cambian.

Cada renglon se calcula como cantidad por precio unitario. Se redondea el importe de cada concepto a dos decimales y se suman los importes de materiales y servicios/mano de obra. No se calcula IVA ni otro impuesto.

## 8. PDF y precios de materiales

Desde el detalle de una cotizacion puedes:

- **Ver PDF** en el navegador.
- **Descargar PDF**.
- **Compartir PDF** si el navegador y el dispositivo permiten compartir archivos.
- Activar o desactivar **Mostrar precios de materiales en el PDF** antes de usar las acciones del documento.

Al ocultar precios, el PDF indica que el cliente aporta los materiales y el total a cobrar corresponde a mano de obra y servicios. Esta opcion afecta la presentacion y el calculo mostrado en ese PDF; no modifica los precios ni las partidas guardadas.

## 9. Modelo de datos

El esquema principal se encuentra en `database/schema.sql`. Todas las tablas pertenecen a la misma base:

| Tabla | Proposito |
|---|---|
| `usuarios` | Cuentas que pueden iniciar sesion. |
| `clientes` | Datos de contacto de los clientes. |
| `materiales` | Catalogo de materiales y sus precios vigentes. |
| `servicios` | Catalogo de mano de obra y servicios. |
| `cotizaciones` | Encabezado, cliente, folio, fechas y notas. |
| `cotizacion_folio_secuencias` | Ultimo correlativo utilizado por cada año. |
| `cotizacion_materiales` | Materiales y valores historicos de cada cotizacion. |
| `cotizacion_servicios` | Servicios y valores historicos de cada cotizacion. |

Las tablas de detalle guardan copias de nombre, descripcion, unidad y precio unitario. Asi, editar un catalogo no reescribe los documentos ya emitidos.

## 10. Estructura del proyecto

```text
app/
  auth.php                 Control de acceso.
  bootstrap.php            Configuracion, sesion, PDO y protecciones comunes.
  catalog.php              Administracion compartida de materiales y servicios.
  helpers.php              Validaciones, folios, formatos y utilidades.
  views/                   Encabezado y pie comunes.
config/
  config.php               Configuracion de aplicacion y base de datos.
database/
  schema.sql               Definicion del esquema SQL.
public/
  assets/css/styles.css    Estilos.
  assets/js/quote-form.js  Interaccion del formulario de cotizacion.
  *.php                   Paginas publicas de la aplicacion.
vendor/                    Dependencias PHP instaladas por Composer.
```

## 11. Seguridad, respaldo y mantenimiento

- La aplicacion utiliza consultas PDO preparadas, validacion en el servidor, escape de salida HTML y tokens CSRF en las operaciones que modifican datos.
- Las contrasenas se verifican mediante hashes; no se deben guardar ni compartir en texto plano.
- Configura una contrasena MySQL distinta de la predeterminada de XAMPP y limita el acceso a la aplicacion en produccion.
- Habilita HTTPS en cualquier servidor accesible desde internet.
- Respalda regularmente la base de datos y verifica que puedas restaurar una copia.
- No publiques `config/config.php`, respaldos SQL con datos de clientes ni archivos con credenciales.
- Conserva `vendor/` o ejecuta `composer install` durante el despliegue para instalar Dompdf.
- Cuando actualices la aplicacion, revisa los cambios de esquema y aplica migraciones especificas; no trates `schema.sql` como actualizador de bases existentes.

## 12. Solucion de problemas

| Problema | Comprobaciones |
|---|---|
| Error al conectar con MySQL | Revisa que MySQL este iniciado y que la base, usuario, contrasena, host y puerto coincidan con `config/config.php`. |
| No se encuentra el controlador PDO | Habilita `pdo_mysql` en el `php.ini` que usa Apache y reinicia el servicio. |
| La raiz del sitio no redirige a la aplicacion | Verifica `mod_rewrite`, `AllowOverride` y las reglas de `.htaccess`. |
| Error al generar PDF | Confirma que `vendor/autoload.php` existe y que Composer instalo Dompdf. |
| No se puede iniciar sesion | Confirma que la cuenta existe y esta activa en `usuarios`; el sistema no tiene registro web ni recuperacion de contrasena. |
| No se puede eliminar un cliente | El cliente tiene cotizaciones relacionadas. Conserva el historial y edita los datos en vez de eliminarlo. |
