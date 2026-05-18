Para ver el array en modo tabla podemos usar -> `console.table(frutas);`
Creamos el array <span style="color:rgb(0, 176, 240)">frutas</span> con el contenido <span style="color:rgb(0, 176, 80)">manzana</span>, <span style="color:rgb(0, 176, 80)">plátano</span> y <span style="color:rgb(0, 176, 80)">naranja</span>:
``` JavaScript
const frutas = ["manzana", "plátano", "naranja"];
```

Para añadir contenido al array se hace de la siguiente manera:
``` JavaScript
frutas[3] = "pera";
console.log(frutas); // 'manzana', 'plátano', 'naranja', 'pera'
```

En cambio si queremos modificar el array debemos hacer esto:
``` JavaScript
frutas[0] = "melón";
```