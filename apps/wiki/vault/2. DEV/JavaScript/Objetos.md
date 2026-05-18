## General
| **COMANDO**               |     | **DESCRPCIÓN**                                |
| ------------------------- | :-: | --------------------------------------------- |
| `console.table(persona);` | ->  | Para ver el objeto en modo tabla podemos usar |
Creamos el objeto persona con los atributos nombre, edad y profesión:
``` JavaScript
const persona = {
  nombre: 'Adri',
  edad: 20,
  profesión: 'informático'
};
```

Para mutar el objeto creado deberemos cambiar un atributo, ya que no podremos cambiar el objeto como tal:
``` JavaScript
persona.edad = 21;
```

También podemos mostrar los atributos de un objeto de esta manera:
``` JavaScript
console.log(persona.nombre, 'tiene', persona.edad, 'años.'); // Adri tiene 21 años.
```
## Objetos CLAVE-VALOR

Creamos el objeto clave-valor curso con el atributo nombre:
``` JavaScript
let curso = {
  nombre: "Sumérgete en JS"
};
```

Y añadimos <span style="color:rgb(0, 176, 80)">precio</span> y <span style="color:rgb(0, 176, 80)">autor</span>:
``` JavaScript
curso.precio = 6;
curso.autor = 'programee';
console.table(curso);
```

Nos sale lo siguiente:
![[Contenido tabla curso js.png]]