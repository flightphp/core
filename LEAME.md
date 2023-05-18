# ¿Qué es Flight?

Flight es un framework rápido, simple y extensible para PHP. Flight te permite
construir rápida y fácilmente aplicaciones web RESTful.

```php
require 'flight/Flight.php';

Flight::route('/', function(){
    echo 'hola mundo!';
});

Flight::start();
```

[Aprende más](http://flightphp.com/learn)

# Requisitos

Flight requiere `PHP 7.4` o superior.

# Licencia

Flight está liberado bajo la licencia [MIT](http://flightphp.com/license).

# Instalación

1\. Descarga los archivos.

Si estás usando [Composer](https://getcomposer.org/), puedes ejecutar el
siguiente comando:

```bash
composer require mikecao/flight
```

O puedes [descargarlo](https://github.com/mikecao/flight/archive/master.zip)
directamente y extraerlo en tu directorio web.

2\. Configura tu servidor web.

Para *Apache*, edita tu archivo `.htaccess` con lo siguiente:

```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

**Nota**: Si necesitas usar flight en un subdirectorio agrega la línea `RewriteBase /subdir/` justo después de `RewriteEngine On`.

Para *Nginx*, agrega lo siguiente a tu declaración del servidor:

```
server {
    location / {
        try_files $uri $uri/ /index.php;
    }
}
```
3\. Crea tu archivo `index.php`.

Primero incluye el framework.

```php
require 'flight/Flight.php';
```

Si estás usando Composer, ejecuta el autoloader en su lugar.

```php
require 'vendor/autoload.php';
```

Luego define una ruta y asigna una función para manejar la petición.

```php
Flight::route('/', function(){
    echo 'hola mundo!';
});
```

Finalmente, inicia el framework.

```php
Flight::start();
```

# Enrutado

El enrutado en Flight es hecho registrando un patrón URL con una función llamada de retorno.

```php
Flight::route('/', function(){
    echo 'hola mundo!';
});
```

La llamada de retorno puede ser cualquier objeto que sea un
[callable](https://www.php.net/manual/es/language.types.callable.php).
Así que puedes usar una función regular:

```php
function hola(){
    echo 'hola mundo!';
}

Flight::route('/', 'hola');
```

O un método de clase:

```php
class Saludo {
    public static function hola() {
        echo 'hola mundo!';
    }
}

Flight::route('/', ['Saludo', 'hello']);
```

O un método de objeto:

```php
class Saludo {
	public $nombre;
	
    public function __construct() {
        $this->nombre = 'John Doe';
    }

    public function hola() {
        echo "Hola, {$this->nombre}!";
    }
}

$saludo = new Saludo();

Flight::route('/', [$saludo, 'hola']); 
```

Las rutas son coincididas en el orden en que están definidas. La primera ruta
que coincida con una petición será invocada.

## Métodos del Enrutado

Por defecto, los patrones de rutas son coincididos contra todos los métodos de
petición. Puedes responder a métodos específicos ubicando un identificador antes
de la URL.

```php
Flight::route('GET /', function(){
    echo 'Recibí una petición GET.';
});

Flight::route('POST /', function(){
    echo 'Recibí una petición POST.';
});
```

Puedes también mapear múltiples métodos a un simple
[callback](https://www.php.net/manual/es/language.types.callable.php)
usando un delimitador `|`:

```php
Flight::route('GET|POST /', function(){
    echo 'Recibí una petición GET o POST.';
});
```

## Expresiones Regulares

Puedes usar expresiones regulares en tus rutas:

```php
Flight::route('/usuario/[0-9]+', function(){
    // Esto coincidirá con /usuario/1234
});
```

## Parámetros nombrados

Puedes especificar parámetros nombrados en tus rutas las cuales serán
pasados a tu función callback.

```php
Flight::route('/@nombre/@id', function($nombre, $id){
    echo "hola, $nombre ($id)!";
});
```

Puedes también incluir expresiones regulares con tus parámetros nombrados
usando el delimitador `:`, por ejemplo:

```php
Flight::route('/@nombre/@id:[0-9]{3}', function($nombre, $id){
    /* Esto coincidirá con /bob/123
    Pero no coincidirá con /bob/12345 */
});
```

Coincidir grupos de expresiones regulares `()` con parámetros nombrados no está
soportado.

## Parámetros Opcionales

Puedes especificar parámetros nombrados que son opcionales para coincidencias
agrupando segmentos en paréntesis.

*NOTA*: Caracteres latinos Unicode no están soportados: á, é, Ó, ñ, Ñ, etc...

```php
Flight::route('/blog(/@year(/@mes(/@dia)))', function($year, $mes, $dia){
    /* Esto coincidirá con las siguientes URLs:
    /blog/2012/12/10
    /blog/2012/12
    /blog/2012
    /blog */
});
```

Cualquier parámetro opcional que no coincida será pasado como `NULL`.

## Comodines

Las coincidencias sólo son hechas en segmentos individuales de URL. Si quieres
coincidir con múltiples segmentos puedes usar el comodín `*`.

```php
Flight::route('/blog/*', function(){
    // Esto coincidirá con /blog/2000/02/01
});
```

Para enrutar todas las peticiones a un simple callback, puedes hacer lo siguiente:

```php
Flight::route('*', function(){
    // Hacer algo
});
```

## Paso

Puedes pasar la ejecución a una siguiente coincidencia de ruta retornando `true`
de tu función callback.

```php
Flight::route('/usuario/@nombre', function($nombre){
    // Verifica algunas condiciones
    if ($nombre !== 'Bob') {
        // Continua con la siguiente ruta
        return true;
    }
});

Flight::route('/usuario/*', function(){
    // Esto será llamado
});
```

## Información de la Ruta

Si quieres inspeccionar la información de la ruta coincidente, puedes solicitar que el objeto
`route` sea pasado a tu callback pasando `true` como tercer parámetro in tu método route.
El objeto ruta siempre debe ser el último parámetro pasado a tu función callback.

```php

use flight\net\Route;

Flight::route('/', function(Route $ruta){
    // Arreglo de métodos HTTP con los cuales hay coincidencias
    $ruta->methods;

    // Arreglo de parámetros nombrados
    $ruta->params;

    // Expresiones regulares coincidentes
    $ruta->regex;

    // Contiene todo el contenido de cualquier '*' usado en el patrón de URL
    $ruta->splat;
}, true);
```

# Extendiendo

Flight está diseñado para ser un framework extensible. El framework viene con
un conjunto de métodos y componentes por defecto, pero te permite mapear tus
propios métodos, registrar tus propias clases o incluso sobrescribir clases y
métodos existentes.

## Mapeando Métodos

Para mapear tus propios métodos personalizados, puedes usar el método `map`:

```php
// Mapea tu método
Flight::map('hola', function($nombre){
    echo "hola $nombre!";
});

// Llama a tu método personalizado
Flight::hola('Bob');
```

## Registrando Clases

Para registrar tus propias clases, puedes usar el método `register`:

```php
// Registra tu clase
Flight::register('usuario', 'Usuario');

// Obtiene una instancia de tu clase
$usuario = Flight::usuario();
```

El método register también te permite pasar parámetros a tu constructor de clase.
Así cuando cargas tu clase personalizada, vendrá pre-inicializada.
Puedes definir los parámetros del constructor pasando un arreglo adicional.
Aquí hay un ejemplo cargando una conexión a base de datos:

```php
// Registra una clase con parámetros del constructor
Flight::register('bd', 'PDO', ['mysql:host=localhost;dbname=prueba', 'usuario', 'contraseña']);

/* Obtiene una instancia de tu clase
Esto creará un objeto con los parámetros definidos

	new PDO('mysql:host=localhost;dbname=prueba', 'usuario', 'contraseña') */
$bd = Flight::bd();
```

Si pasas en un parámetro callback adicional, será ejecutado inmediatamente
después de la construcción del objeto. Esto te permite realizar cualquier
procedimiento de asignación para tu nuevo objeto. La función callback toma un
parámetro que es la instancia del nuevo objeto.

```php
// El callback será pasado al objeto que fue construido
Flight::register('bd', 'PDO', ['mysql:host=localhost;dbname=prueba', 'usuario', 'contraseña'], function(PDO $bd){
    $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
});
```

Por defecto, cada vez que cargas tu clase obtendrás una instancia compartida.
Para obtener una nueva instancia de la clase, simplemente pasa `false` como un parámetro:

```php
// Instancia compartida de la clase
$compartida = Flight::bd();

// Nueva instancia de la clase
$nueva = Flight::bd(false);
```

Ten en cuenta que los métodos mapeados tienen precedencia sobre las clases
registradas. Si declaras ambos usando el mismo nombre, sólo el método mapeado
será invocado.

# Sobrescribiendo

Flight te permite sobrescribir sus funcionalidades por defecto para ajustarlas
a tus necesidades.

Por ejemplo, cuando Flight no pueden coincidir una URL con una ruta, invoca el
método `notFound`, el cual envía una respuesta genérica `HTTP 404`. Puedes
sobrescribir este comportamiento usando el método `map`:

```php
Flight::map('notFound', function(){
    // Mostrar una página 404 personalizada
    include 'errores/404.html';
});
```

Flight también te permite reemplazar componentes del núcleo del framework.
Por ejemplo puedes reemplazar la clase Router por defecto con tu propia
clase personalizada:

```php
// Registra tu clase personalizada
Flight::register('router', 'MiRouter');

// Cuando Flight carga la instancia Router, cargará tu clase
$miRouter = Flight::router();
```

Sin embargo, los métodos del Framework como `map` y `register` no pueden ser
sobrescritos. Obtendrás un error si intentas hacer eso.

# Filtrando

Flight te permite filtrar métodos antes y después de que sean llamados. No hay
hooks que necesitas memorizar. Puedes filtrar cualquiera de los métodos por defecto
del framework así como también cualquiera de los métodos personalizados
que hayas mapeado.

Una función filtro luce así:

```php
function(&$parametros, &$salida) {
    // Filtrar código
}
```

Usando las variables pasadas puedes manipular los parámetros de entrada y/o
la salida.

Puedes tener un filtro que se ejecute antes que un método lo haga:

```php
Flight::before('start', function(&$parametros, &$salida){
    // Hacer algo
});
```

Puedes tener un filtro que se ejecute después que un método lo haga:

```php
Flight::after('start', function(&$parametros, &$salida){
    // Hacer algo
});
```

Puedes agregar tantos filtros como quieras a cualquier método. Ellos serán llamados
en el orden en que son declarados.

Aquí hay un ejemplo del proceso de filtrado:

```php
// Mapea un método personalizado
Flight::map('hola', function($nombre){
    return "Hola, $nombre!";
});

// Agrega un filtro antes
Flight::before('hola', function(&$parametros, &$salida){
    // Manipula el parámetro
    $parametros[0] = 'Fred';
});

// Agrega un filtro después
Flight::after('hola', function(&$parametros, &$salida){
    // Manipula la salida
    $salida .= ' Ten un buen día!';
});

// Invoca el método personalizado
echo Flight::hola('Bob');
```

Esto debería mostrar:

	Hola Fred! Ten un buen día!

Si has definido múltiples filtros, puedes romper la cadena retornando `false`
in cualquiera de tus funciones filtro:

```php
Flight::before('start', function(&$parametros, &$salida){
    echo 'uno';
});

Flight::before('start', function(&$parametros, &$salida){
    echo 'dos';

    // Esto cortará la cadena
    return false;
});

// Esto no será llamado
Flight::before('start', function(&$parametros, &$salida){
    echo 'tres';
});
```

Nota, los métodos del núcleo como `map` y `register` no pueden ser filtrados
ya que ellos son llamados directamente y no invocados dinámicamente.

# Variables

Flight te permite guardar variables para que puedan ser usadas en cualquier parte
de tu aplicación.

```php
// Guarda tu variable
Flight::set('id', 123);

// El cualquier parte de tu aplicación
$id = Flight::get('id');
```
Para ver si una variable ha sido asignada puedes hacer lo siguiente:

```php
if (Flight::has('id')) {
	// Hacer algo
}
```

Puedes limpiar una variable haciendo lo siguiente:

```php
// Limpia la variable id
Flight::clear('id');

// Limpia todas las variables
Flight::clear();
```

Flight también usa variables para propósitos de la configuración

```php
Flight::set('flight.log_errors', true);
```

# Vistas

Flight provides some basic templating functionality by default. To display a view
template call the `render` method with the name of the template file and optional
template data:
Flight te provee algunas funcionalidades básicas de plantilla por defecto.
Para mostrar una plantilla de vista llama al método `render` con el nombre del archivo
de la plantilla pasándole una lista opcional de datos a la plantilla.

```php
Flight::render('hola', ['nombre' => 'Bob']);
```

Los datos de la plantilla que pasas son automáticamente inyectados a tu plantilla
y pueden ser referenciados como variables locales. Los archivos de plantilla
son simples archivos PHP. Si el contenido del archivo de plantilla `hola.php` es:

```php
Hola, '<?= $nombre ?>'!
```

La salida debería ser:

	Hola, Bob!

Puedes también manualmente asignar las variables de la vista usando el método
set:

```php
Flight::view()->set('nombre', 'Bob');
```

La variable `nombre` ahora está disponible a través de todas tus vistas. Así
que puedes simplemente hacer:

```php
Flight::render('hola');
```

Nota que cuando especificamos el nombre de la plantilla en el método render,
puedes dejarla sin la extensión `.php`.

Por defecto Flight buscará un directorio `views` para archivos de plantilla.
Puedes asignar una ruta alternativa a tus plantillas asignando la siguiente
configuración:

```php
Flight::set('flight.views.path', __DIR__ . '/ruta/a/vistas');
```

## Diseños

Es común que sitios web tengan un simple archivo de plantilla de diseño con
contenido intercambiable. Para renderizar contenido a ser usado en un diseño,
puedes pasar un parámetro opcional al método render `render`:

```php
Flight::render('encabezado', ['encabezado' => 'Hola'], 'contenidoEncabezado');
Flight::render('cuerpo', ['cuerpo' => 'Mundo'], 'contenidoCuerpo');
```

Tus vistas tendrán guardadas variables llamadas `contenidoEncabezado` y
`contenidoCuerpo`.
Puedes luego renderizar tu diseño haciendo lo siguiente:

```php
Flight::render('diseño', ['titulo' => 'Página Principal']);
```

If the template files looks like this:
Si los archivos de plantilla lucen así:

`encabezado.php`:

```php
<h1><?= $encabezado ?></h1>
```

`cuerpo.php`:

```php
<div><?= $cuerpo ?></div>
```

`diseño.php`:

```php
<html>
  <head>
    <title><?= $titulo ?></title>
  </head>
  <body>
    <?= $contenidoEncabezado ?>
    <?= $contenidoCuerpo ?>
  </body>
</html>
```

La salida sería:
```html
<html>
  <head>
    <title>Página Principal</title>
  </head>
  <body>
    <h1>Hola</h1>
    <div>Mundo</div>
  </body>
</html>
```

## Vistas Personalizadas

Flight te permite cambiar el motor de vistas por defecto simplemente
registrando tu propia clase de vistas. Aquí hay un ejemplo de como usarías el
motor de plantillas [Smarty](http://www.smarty.net/) para tus vistas:

```php
// Carga la librería de Smarty
require './Smarty/libs/Smarty.class.php';

/* Registra Smarty como clase de vistas
también pasa una función callback para configurar Smarty al cargar */
Flight::register('view', 'Smarty', [], function(Smarty $smarty) {
    $smarty->setTemplateDir('templates');
    $smarty->setCompileDir('templates_c');
    $smarty->setConfigDir('config');
    $smarty->setCacheDir('cache');
});

// Asignas datos a la plantilla
Flight::view()->assign('nombre', 'Bob');

// Muestra la plantilla
Flight::view()->display('hola.tpl');
```

Para completar, puedes también sobrescribir el método render por defecto de Flight:

```php
Flight::map('render', function($plantilla, $datos) {
	// Para hacer la extensión .tpl opcional
	if (strpos($plantilla, '.tpl') === false) {
		$plantilla .= '.tpl';
	}
	
	foreach ($datos as $variable => $valor) {
    	Flight::view()->assign($variable, $valor);
	}
	
    Flight::view()->display($plantilla);
});
```
# Manejo de Errores

## Errores y Excepciones

Todos los errores y excepciones son capturadas por Flight y pasadas al método
`error`. El comportamiento por defecto es enviar una respuesta genérica
`HTTP 500 Internal Server Error` con algo de información del error.

Puedes sobrescribir este comportamiento para tus propias necesidades:

```php
Flight::map('error', function(Exception|Error $ex){
    // Manejar error
    echo $ex->getTraceAsString();
});
```

Por defecto los errores no son logueados en el servidor web. Puedes habilitar
esto cambiando la configuración:

```php
Flight::set('flight.log_errors', true);
```

## No Encontrado

Cuando una URL no puede ser encontrada, Flight llama al método `notFound`.
El comportamiento por defecto es enviar una respuesta `HTTP 404 Not Found` con
un simple mensaje.

Puedes sobrescribir este comportamiento para tus propias necesidades:

```php
Flight::map('notFound', function(){
    // Manejar no encontrado
});
```

# Redireccionar

Puedes redireccionar la petición actual usando el método `redirect` y pasar una
nueva URL:

```php
Flight::redirect('/nueva/ubicacion');
```

Por defecto Flight envía un código de estado HTTP 303. Puedes opcionalmente
asignar un código personalizado:

```php
Flight::redirect('/nueva/ubicacion', 401);
```

# Peticiones

Flight encapsula la petición HTTP en un sólo objeto, el cual puede ser accedido
haciendo lo siguiente:

```php
$peticion = Flight::request();
```

El objeto petición provee las siguientes propiedades:

- `url - La URL solicitada`
- `base - El directorio padre de la URL`
- `method - El método de la petición (GET, POST, PUT, DELETE)`
- `referrer - La URL proveniente`
- `ip - La dirección IP del cliente`
- `ajax - Si la petición es una petición AJAX`
- `scheme - El protocolo del servidor (http, https)`
- `user_agent - Información del Navegador`
- `type - El tipo de contenido`
- `length - La cantidad de caracteres del contenido`
- `query - Cadena de parámetros de la consulta (?buscar=flight)`
- `data - Datos POST o datos JSON`
- `cookies - Datos de Cookies`
- `files - Archivos subidos`
- `secure - Si la conexión es segura`
- `accept - Parámetros HTTP aceptados`
- `proxy_ip - Dirección proxy IP del cliente`
- `host - El nombre del host de la petición`

Puedes acceder a las propiedades `query`, `data`, `cookies` y `files`
como arreglos u objetos.

Así, que para obtener un parámetro de la cadena de consulta, puedes hacer:

```php
$id = Flight::request()->query['id'];
```

O puedes hacer:

```php
$id = Flight::request()->query->id;
```

## Contenido CRUDO de la Petición

Para obtener el cuerpo crudo de la petición HTTP, por ejemplo cuando trabajas
con peticiones PUT, puedes hacer:

```php
$cuerpo = Flight::request()->getBody();
```

## Entrada JSON

Si envías una petición con el tipo `application/json` y los datos `{"id": 123}`
de la propiedad `data`:

```php
$id = Flight::request()->data->id;
```

# Cacheo HTTP

Flight te provee soporte incorporado para nivel de cacheo HTTP. Si las condiciones
de cacheo se cumplen, Flight retornará una respuesta `304 Not Modified`. La
próxima vez que el cliente solicita el mismo recurso, se decidirá si usar la
versión local o la versión cacheada.

## Last-Modified

You can use the `lastModified` method and pass in a UNIX timestamp to set the date
and time a page was last modified. The client will continue to use their cache until
the last modified value is changed.
Puedes usar el método `lastModified` y pasar un timestamp UNIX para asignar
la fecha y hora en que la página fue modificada por última vez. El cliente
continuará usando su versión cacheada hasta que el valor de `last modified` cambie.

```php
Flight::route('/noticias', function() {
    Flight::lastModified(1234567890);
    echo 'Este contenido será cacheado.';
});
```

## ETag

El cacheado `ETag` es similar al `Last-Modified`, excepto que puedes especificar
cualquier id que quieras para el recurso:

```php
Flight::route('/noticias', function(){
    Flight::etag('mi-id-unico');
    echo 'Este contenido será cacheado.';
});
```

Ten en cuenta que llamando a `lastModified` o `etag` ambos asignarán y revisarán el
valor del caché. Si el valor del caché es el mismo entre peticiones, Flight
inmediatamente enviará una respuesta `HTTP 304` y detendrá el proceso.

# Deteniendo

Puedes detener el framework en cualquier punto llamando al método `half`:

```php
Flight::halt();
```

Puedes también especificar un código de estado HTTP opcional y un mensaje:

```php
Flight::halt(200, 'Estaré de vuelta...');
```

Llamando a `half` descartará cualquier contenido de respuesta a ese punto.
Si quieres detener el framework y dar salida a la respuesta actual, usa el método
`stop`:

```php
Flight::stop();
```

# JSON

Flight te provee soporte para enviar respuestas JSON y JSONP. Para enviar
una respuesta JSON puedes pasar algunos datos a ser codificados a JSON:

```php
Flight::json(['id' => 123]);
```

Para peticiones JSONP, puedes opcionalmente pasar un nombre de parámetro de consulta
que estés usando para definir tu función callback en el cliente:

```php
Flight::jsonp(['id' => 123], 'q');
```

Así, cuando hagas una petición GET usando `?q=mi_func` deberías recibir la salida:

```
mi_func({"id":123});
```

Si no pasas un parámetro de consulta, por defecto será `jsonp`.


# Configuración

You can customize certain behaviors of Flight by setting configuration values
through the `set` method.
Puedes personalizar ciertos comportamientos de Flight asignando valores de
configuración a través del método `set`.

```php
Flight::set('flight.log_errors', true);
```

Lo siguiente es una lista de todos las opciones de configuración disponibles:

- flight.base_url - Sobrescribe la url base de la petición. (por defecto: null)
- flight.case_sensitive - Coincidencias sensibles a mayúsculas y minúsculas para URLs. (por defecto: false)
- flight.handle_errors - Le permite a Flight manejar todos los errores internamente. (por defecto: true)
- flight.log_errors - Registra los errores en el archivo de registro de errores del servidor web. (por defecto: false)
- flight.views.path - Directorio que contiene tus archivos de plantillas de vista. (por defecto: ./views)
- flight.views.extension - Extensión de los archivos de plantillas de vista. (por defecto: .php)

# Métodos del Framework

Flight está diseñado para ser fácil de usar y entender. Lo siguiente es el conjunto
completo de métodos para el framework. Consiste en métodos del núcleo, los cuales
son métodos estáticos regulares y métodos extensibles, los cuales son métodos
mapeados que pueden ser filtrados y sobrescritos.

## Métodos del Núcleo

```php
Flight::map($nombre, $callback) // Crea un método del framework personalizado.
Flight::register($nombre, $clase, $parametros, $callback) // Registra una clase a un método del framework.
Flight::before($nombre, $callback) // Agrega un filtro antes de un método del framework.
Flight::after($nombre, $callback) // Agrega un filtro después de un método del framework.
Flight::path($ruta) // Agrega una ruta para autocarga de clases.
Flight::get($clave) // Obtiene una variable.
Flight::set($clave, $valor) // Asigna una variable.
Flight::has($clave) // Verifica si una variable está asignada.
Flight::clear($clave) // Limpia una variable.
Flight::init() // Inicia el framework en sus ajustes por defecto.
Flight::app() // Obtiene un objeto instancia de la aplicación
```

## Métodos Extensibles

```php
Flight::start() // Inicia el framework.
Flight::stop() // Detiene el framework y envía una respuesta.
Flight::halt($codigo, $mensaje) // Detiene el framework con un código de estado opcional y un mensaje.
Flight::route($patron, $callback) // Mapea un patrón de URL a un callback.
Flight::redirect($url, $codigo) // Redirecciona a otra URL.
Flight::render($archivo, $datos, $clave) // Renderiza un archivo de plantilla.
Flight::error($exception) // Envia una respuesta HTTP 500.
Flight::notFound() // Envia una respuesta HTTP 404.
Flight::etag($id, $tipo) // Realiza un cacheo HTTP ETag.
Flight::lastModified($time) // Realiza un cacheo HTTP Last Modified.
Flight::json($datos, $codigo, $codificado, $conjuntoCaracteres, $opciones) // Envía una respuesta JSON.
Flight::jsonp($datos, $parametro, $codigo, $codificado, $conjuntoCaracteres, $opciones) // Envía una respuesta JSONP.
```

Cualquier método personalizado agregado con `map` y `register` también puede ser
filtrado.


# Instancia del Framework

En lugar de ejecutar Flight como una clase estática global, puedes opcionalmente
ejecutarlo como un objeto instancia.

```php
require 'flight/autoload.php';

use flight\Engine;

$app = new Engine;

$app->route('/', function(){
    echo 'hola mundo!';
});

$app->start();
```

Así que en lugar de llamar a métodos estáticos, llamarías a métodos de
la instancia con el mismo nombre en el objeto Engine.