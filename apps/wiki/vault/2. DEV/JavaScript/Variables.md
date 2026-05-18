## Generales
| **VARIABLE** |     | **DESCRIPCIÓN**                                                                      |
| ------------ | :-: | ------------------------------------------------------------------------------------ |
| `var`        | ->  | Variables locales, accesibles en todo el código.                                     |
| `let`        | ->  | Variables de bloque, accesibles solo en inicio y fin de un bloque (if, function).    |
| `const`      | ->  | Variables de bloque que nunca cambian, pueden mutar (se usan para objetos o arrays). |
>[!Info]
>Las variables pueden ser de declaración global (existen en todo el código) o declaración de bloque (solo existen dentro del bloque y sus sub-bloques en cuestión).
## Dato
| VARIABLE                       |     | DESCRIPCIÓN                                                      |
| ------------------------------ | :-: | ---------------------------------------------------------------- |
| `int`                          | ->  | Números enteros, decimales, positivos o negativos.               |
| `string`                       | ->  | Entre ' o ".                                                     |
| `boolean`                      | ->  | True o False.                                                    |
| `objets`                       | ->  | Conjunto de clave-valor {}.                                      |
| `arrays`                       | ->  | Listas ordenadas de valores [].                                  |
| `function`                     | ->  | Bloques de código que se pueden ejecutar más tarde function(){}. |
| `Especiales`                   | ->  | null (nulo) y undefined (indefinido).                            |
| `console.log(typeof <valor>);` | ->  | Para ver el tipo de dato.                                        |
>[!Info]
>Estas variables son de tipo dinámico, es decir, que el interprete modifica el tipo de la variable dependiendo del contexto.