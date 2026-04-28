## Parar
**Detiene AWX ordenadamente** (es opcional pero recomendado), **verifica** que los pods de AWX (web/task) estén parados y **detiene minikube**:
``` Bash
kubectl -n awx scale deployment awx --replicas=0
kubectl get pods -n awx
minikube stop
```

---

## Levantar
Inicia **minikube**, verifica el **nodo** y **lista los pods**:
``` Bash
minikube start --driver=docker --memory=6144 --cpus=3 --force
kubectl get nodes
kubectl get pods -n awx
```

> [!error] Fallo
> Si nos falla al iniciar, podemos eliminar y volver a iniciar minikube:
> ``` Bash
> minikube delete --all --purge
> minikube start --driver=docker --memory=6144 --cpus=3 --force
> ```
> 
> Y si aun tenemos problemas, podemos bajar las protecciones del Kernel:
> ``` Bash
> sysctl fs.protected_regular=0
> sysctl fs.protected_symlinks=0
> ```

---

Si AWX no arranca automáticamente (raro, pero puede pasar tras reinicio), simplemente vuelve a escalar el despliegue:
``` Bash
kubectl -n awx scale deployment awx --replicas=1
```
Y verifica otra vez:
``` Bash
kubectl get pods -n awx -w
```
Accedemos:
``` Bash
http://ansible:32360
```

---
## OPCIONAL
Puedes ver los volúmenes donde AWX guarda datos con:
``` Bash
kubectl get pvc -n awx
kubectl get pv
```
Tiene que salir:
``` Bash
NAME                         STATUS   VOLUME                                     CAPACITY   ACCESS MODES   STORAGECLASS   AGE
postgres-15-awx-postgres-0   Bound    pvc-6e3f8f9b-d82b-4a02-b89a-18e33ff8f7c9   5Gi        RWO            standard       5d
```

## 🪄 En resumen
| Acción                       | Comando                                            | Resultado                             |
| ---------------------------- | -------------------------------------------------- | ------------------------------------- |
| Apagar correctamente         | `minikube stop`                                    | Guarda todo y detiene el clúster      |
| Arrancar después de reinicio | `minikube start`                                   | Restaura todo (pods, servicios, PVCs) |
| Detener solo AWX (opcional)  | `kubectl scale -n awx deployment awx --replicas=0` | Deja Postgres y Operator vivos        |
| Reanudar AWX                 | `kubectl scale -n awx deployment awx --replicas=1` | Reactiva AWX Web y Task               |
| Acceder a la interfaz        | `http://<IP_HOST>:32360`                           | Misma configuración que antes         |