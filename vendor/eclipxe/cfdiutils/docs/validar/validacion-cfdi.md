# Validaciones de CFDI version 3.3

Esta librería provee recursos para realizar validaciones en el espacio de nombres `CfdiUtils\Validate`.

Se busca que al validar no solo se reporten las validaciones con error. También se reportan aquellas
exitosas, las que tienen una advertencia y las correctas, incluso algunas podrían contener una explicación.

A diferencia de los mensajes de error de toda la librería, todos los mensajes de las validaciones están en español.

El espacio de nombres contiene un validador `MultiValidator`
que comúnmente se genera con una fábrica `MultiValidatorFactory`.
Gracias a este proceso validar documentos creados o recibidos se simplifica.


## Validación de documentos creados

Si se está creando un documento usando la clase `CfdiUtils\CfdiCreator33`
entonces se puede validar usando el método `validate(): Asserts`, por ejemplo:

```php
<?php
/** @var \CfdiUtils\CfdiCreator33 $creator */
$asserts = $creator->validate();
$asserts->hasErrors(); // devuelve verdadero en caso de error
```


## Validación de documentos recibidos

Para esta validar un documento recibido se puede utilizar la clase `CfdiUtils\CfdiValidator33`, por ejemplo:

```php
<?php
use CfdiUtils\CfdiValidator33;

$cfdiFile = '... ubicación del archivo XML del cfdi ...';

$validator = new CfdiValidator33();
$asserts = $validator->validateXml(file_get_contents($cfdiFile));
$asserts->hasErrors(); // devuelve verdadero en caso de error
```

Un objeto de tipo `CfdiValidator33` contiene un `XmlResolver`.
Si se elimina entonces algunos validadores no realizarán el proceso o bien saldrán a internet a encontrar
los recursos que necesitan. Por omisión se crea un nuevo `XmlResolver` pero puede ser establecido
desde su contructor o bien con el método `setXmlResolver`.

Recuerda que la validación trabajará con la información tal comno es presentada, por lo que tal vez
desees usar el método rápido de limpieza `CfdiUtils\Cleaner\Cleaner::staticClean()`.


## ValidatorInterface

Para que un validador funcione necesita ser de tipo `ValidatorInterface` e implementar:

- `validate(NodeInterface $comprobante, Asserts $asserts): void`: Método que se llama para validar.
- `canValidateCfdiVersion(string $version): bool`: Devuelve si el validador es compatible con una versión dada.


## Assert

Cada validador debe inyectar uno o más objetos de tipo `Assert` en la colección `Asserts`.
Se puede considerar que un `Assert` es una prueba o un aseguramiento, y cada `Assert` tiene un estado dado por `Status`.

Gracias al registro de todos los `Assert` en una validación se puede saber no solo lo que falló o generó
una advertencia; también se puede saber lo que estuvo bien o no se pudo comprobar.

El `Assert` es un "aseguramiento", se trata de un enunciado afirmativo, no un enunciado de error, por ello,
un ejemplo de título del aseguramiento podría ser: *El CFDI tiene una moneda definida y que pertenece al catálogo de monedas*.


## Status

Esta es una clase de tipo "value object" por lo que solamente se puede instanciar con un valor y no modificar.

Un objeto `Status` puede contener uno de cuatro valores:

- error: Existe un fallo y se debe considerar que el CFDI es inválido y debería ser rechazado.
- warning: Existe un fallo pero se desconoce si esto es correcto o incorrecto.
- ok: Se realizó la prueba y no se encontró fallo
- none: Ninguno de los estados anteriores, úsese para describir que la prueba no se realizó.


## Asserts

`Asserts` es una colección de objetos de tipo `Assert`.
Esta colección no permite que existan dos `Assert` con el mismo código, cuando se encuentra que se quiere
escribir un `Assert` con el mismo código entonces el previo es sobre escrito.

```php
<?php
/** @var \CfdiUtils\CfdiCreator33 $creator */
$asserts = $creator->validate();
foreach ($asserts as $assert) {
    echo $assert, PHP_EOL;
}
```
