# Requisitos
1. Parar y eliminar contenedores Docker AWX existentes.
2. Instalar o verificar: Docker, kubectl, minikube y kustomize.
3. Iniciar Minikube con recursos suficientes (recomiendo **8 GiB RAM y 3 CPUs**).
4. Instalar AWX Operator (v2.19.1) y crear la CR AWX (NodePort 32360).
5. Abrir puerto en firewall y verificar pods.
6. Obtener contraseña admin.

---
## 0) Limpieza previa (opcional — eliminar los posibles contenedores AWX existentes)

Si aún tenemos los contenedores Docker `awx-web`, `awx-task`, etc. Tenemos que borrarlos primero para evitar conflictos:
``` Bash
# listar los contenedores AWX (para confirmar)
docker ps -a | egrep 'awx|awx-web|awx-task|awx-postgres|awx-redis'

# detener y eliminar solo esos contenedores (más seguro que borrar todo)
docker stop awx-web awx-task awx-postgres awx-redis 2>/dev/null || true
docker rm -f awx-web awx-task awx-postgres awx-redis 2>/dev/null || true

# (opcional) eliminar imágenes AWX/Redis/Postgres relacionadas
docker images | egrep 'ansible/awx|awx|redis|postgres' -i
docker rmi -f <IMAGE_ID> # sustituye IMAGE_ID por los que quieras borrar
```

También podemos borrar **todo** lo relacionado con Docker (imágenes, volúmenes), con el siguiente comando:

> [!warning] Cuidado
> Borra todo lo no usado / todo si forzado

``` Bash
docker system prune -a --volumes -f
```

---
## 1) Requisitos mínimos recomendados para AWX en Minikube
- Memoria: **≥ 6 GiB** para Minikube
- CPUs: **≥ 3 vCPU**
- Usuario exclusivo para tratar minikube (**ansible**)
- Espacio disco: **≥ 20** GiB libre
- Conexión a Internet para descargar imágenes desde `quay.io`. (Si tu entorno está desconectado, indica y te doy pasos para mirror.) [ansible.readthedocs.io](https://ansible.readthedocs.io/projects/awx-operator/en/latest/installation/basic-install.html?utm_source=chatgpt.com)

---
## 2) Instalar / verificar herramientas (kubectl, minikube, kustomize)
### kubectl
1. Descargar `kubectl`:
``` Bash
sudo curl -Lo /tmp/kubectl "https://dl.k8s.io/release/$(curl -Ls https://dl.k8s.io/release/stable.txt)/bin/linux/amd64/kubectl"
```

> [!note] Nota
> (La URL `https://dl.k8s.io` es la oficial y más estable que la vieja `storage.googleapis.com`)

2. Añadimos permisos de ejecución:
``` Bash
sudo chmod +x /tmp/kubectl
```

3. Lo movemos a la ruta `/usr/bin` que use root:
``` Bash
sudo mv /tmp/kubectl /usr/bin/kubectl
```

4. Verificamos que existe y tiene permisos:
``` Bash
ls -l /usr/bin/kubectl
```
- Debería mostrar algo como:
``` Bash
-rwxr-xr-x 1 root root 53852904 nov  7 15:32 /usr/bin/kubectl
```

5. Comprobamos la versión:
``` Bash
kubectl version --client
```
- Deberías salir algo similar a:
``` Bash
Client Version: v1.34.2
Kustomize Version: v5.7.1
```

---
### minikube
1. Lo descargamos:
``` Bash
sudo curl -Lo /tmp/minikube https://storage.googleapis.com/minikube/releases/latest/minikube-linux-amd64
```

2. Lo instalamos:
``` Bash
sudo install /tmp/minikube /usr/local/bin/minikube
```

3. Y comprobamos:
``` Bash
minikube version
```

---
### kustomize (si no lo usamos con kubectl builtin)
1. Lo descargamos:
``` Bash
sudo curl -s "https://raw.githubusercontent.com/kubernetes-sigs/kustomize/master/hack/install_kustomize.sh" | bash
```

2. Lo instalamos:
``` Bash
sudo mv kustomize /usr/local/bin/kustomize
```

3. Y comprobamos:
``` Bash
kustomize version
```

---
### docker
1. Instalar Docker CE:
``` Bash
sudo dnf remove podman buildah -y #(opcional, para evitar conflictos)
sudo dnf install -y dnf-plugins-core
sudo dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
sudo dnf install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

2. Creamos el usuario con permisos de docker:
``` Bash
sudo adduser usuarioNuevo
sudo usermod -aG docker usuarioNuevo
newgrp docker
```

3. Habilitar y arrancar el servicio Docker:
``` Bash
sudo systemctl enable --now docker
sudo systemctl status docker
```

3. Verificar que Docker funciona:
``` Bash
docker run hello-world
```

4. Borrar contenedor e imagen **hello-world**:
``` Bash
docker images && docker ps -a
docker rm -f $(docker ps -a -q --filter ancestor=hello-world)
docker rmi hello-world
docker images && docker ps -a
```

---
## 3) Arrancar Minikube (driver docker) con recursos adecuados

Ajusta memoria y CPUs según tu máquina:
> [!failure] Borrar todo lo que tenía minikube
> Si ya tenías minikube corriendo y quieres reiniciar con nuevos recursos:
> ``` Bash
> minikube delete --all || true
> ```

> [!success] Arrancar minikube
> Arrancar con driver docker y recursos suficientes:
>``` Bash
>minikube start --driver=docker --memory=6144 --cpus=3 --force
>```

> [!info] Estado minikube
> Comprobar el estado:
> ``` Bash
> #Info minikube
> minikube status
> #Info kubectl
> kubectl cluster-info
> kubectl get nodes
> kubectl get pods -A
> ```

Si `minikube start` falla por permisos del socket docker, asegúrate de que tu usuario está en el grupo `docker` o ejecuta con sudo donde proceda, pero en mi caso lo estoy haciendo con el usuario `root`.

---
## 4) (Opcional pero recomendado) pre-pull de imágenes AWX y postgres

Si quieres evitar retrasos/pull errors, puedes descargar manualmente las imágenes en Docker antes de que Kubernetes las requiera:
``` Bash
docker pull quay.io/ansible/awx-operator:2.19.1
docker pull quay.io/ansible/awx:24.6.1   # versión que el operator probablemente deployará
docker pull postgres:15
docker pull quay.io/ansible/awx-ee:latest  # si se usa
```

> [!warning] Info
> Esto puede tardar al rededor de 10 o 15 minutos.

Comprobamos el acceso a quay.io desde la máquina:
``` Bash
curl -I https://quay.io
```

Si falla la conexión, necesitas resolver salida a Internet o usar mirror/registro privado. [GitHub](https://github.com/ansible/awx-operator?utm_source=chatgpt.com)

---
## 5) Crear namespace y desplegar AWX Operator (v2.19.1)

Usaremos la versión estable 2.19.1 del operador:
``` Bash
# crear namespace
kubectl create namespace awx || true

# instalar el operator (kustomize manifiests desde repo, versión 2.19.1)
kubectl apply -k https://github.com/ansible/awx-operator/config/default?ref=2.19.1
```

> [!warning] Info
> Esto puede tardar al rededor de 10 o 15 minutos.

Verifica que el operador esté corriendo:
``` Bash
kubectl get pods -n awx
```

Para ver los logs:
``` Bash
kubectl logs -n awx deployment/awx-operator-controller-manager -c awx-manager --tail=100
```

Referencias oficiales y notas sobre versiones: AWX Operator repo y docs. [GitHub+1](https://github.com/ansible/awx-operator?utm_source=chatgpt.com)

---
## 6) Crear el recurso AWX (CR) correcto

Crea un archivo `awx-cr.yaml` con este contenido (ajustado a operator v2.19.1 — **sin** `postgres_data_volume_size` porque me daba error):
``` Bash
apiVersion: awx.ansible.com/v1beta1
kind: AWX
metadata:
  name: awx
  namespace: awx
spec:
  service_type: NodePort
  nodeport_port: 32360
  ingress_type: none
  admin_user: admin
  admin_password_secret: awx-admin-password
  postgres_storage_class: "standard"
  # projects_persistence: false   # opcional
```

Aplica:
``` Bash
kubectl apply -f awx-cr.yaml
```

> [!warning] Info
> Esto puede tardar al rededor de 10 o 15 minutos.

El operador leerá este CR y creará Postgres, web, task, secrets, etc.

---
## 7) Esperar y verificar el despliegue (comprobaciones)

Observa los pods:
``` Bash
kubectl get pods -n awx -w
```

Deberías ver en orden: `awx-postgres-...` → `awx-web-...` → `awx-task-...` y el operador `awx-operator-controller-manager` en Running.

Si algún pod queda en `ImagePullBackOff` o `CrashLoopBackOff`:
- `kubectl describe pod <pod> -n awx` (ver Events)    
- `kubectl logs -n awx <pod> -c <container>` (ver errores)  

Problemas comunes:
- imágenes no disponibles (tag incorrecto) — revisa que `quay.io/ansible/awx-operator:2.19.1` exista. [GitHub](https://github.com/ansible/awx-operator?utm_source=chatgpt.com)
- recursos insuficientes — aumenta `minikube start --memory` y `--cpus`.
- secrets incorrectos — revisa `kubectl get secret -n awx`.

---
## 8) Abrir firewall y acceso desde otra máquina

Abre puerto NodePort en CentOS (puerto 32360):
``` Bash
sudo firewall-cmd --add-port=32360/tcp --permanent
sudo firewall-cmd --reload
```

Obtener IP de Minikube:
``` Bash
minikube ip # por ejemplo 192.168.49.2 (pero en acceso externo es mejor la IP del host)
```

Acceso recomendado desde otra máquina de la LAN: usa la **IP del host CentOS** (ej. `192.168.1.3`) y el NodePort **32360**. Si Minikube expone el NodePort en todas las interfaces Docker host, podrás acceder con:
```
http://192.168.1.3:32360
```

Si no funciona, comprueba `docker ps` para ver que hay un `docker-proxy` mapeando el puerto 32360 (ya lo viste antes). También asegúrate de que CentOS tiene esa IP en la LAN.

---
## 9) Obtener credenciales admin de AWX

Cuando `awx-web` y `awx-task` estén Running:
``` Bash
kubectl get secret awx-admin-password -n awx -o jsonpath="{.data.password}" | base64 --decode; echo
# usuario: admin
```

---
## 10) Troubleshooting rápido (problemas más comunes)

- **Operador en CrashLoopBackOff**
    - `kubectl logs -n awx deployment/awx-operator-controller-manager -c manager`
    - Si error `manifest not found` => instalaste operator con tag que no existe; reinstala con `ref=2.19.1`. [GitHub](https://github.com/ansible/awx-operator?utm_source=chatgpt.com)
- **awx-task stuck en Init waiting for migrations**
    - Confirma `awx-postgres` Running y que puedes conectar:
		``` Bash
		# obtener contraseña postgres del secret
		kubectl get secret -n awx awx-postgres-configuration -o jsonpath='{.data.password}' | base64 --decode
		# probar conexión desde un pod temporal
		kubectl run -n awx --rm -it pg-client --image=postgres:15 --env="PGPASSWORD=<PASSWORD>" -- psql -h awx-postgres-15 -U awx -d awx -c '\l'
		```
	- Si falla por autenticación, inspecciona secrets; si falla por red, mira eventos y CNI.
- **ImagePullBackOff**
    - `kubectl describe pod -n awx <pod>` muestra cuál imagen falta. Puedes `docker pull <imagen>` localmente para pre-poblar.
- **Recursos insuficientes**
    - Aumenta `minikube start` memory/CPUs y re-aplica el CR.

---
## 11) Comandos útiles resumen (copiar y pegar)
``` Bash
# limpiar contenedores AWX antiguos (si quedan)
docker stop awx-web awx-task awx-postgres awx-redis 2>/dev/null || true
docker rm -f awx-web awx-task awx-postgres awx-redis 2>/dev/null || true

# instalar herramientas (si faltan)
# kubectl
curl -Lo /tmp/kubectl "https://storage.googleapis.com/kubernetes-release/release/$(curl -s https://storage.googleapis.com/kubernetes-release/release/stable.txt)/bin/linux/amd64/kubectl"
chmod +x /tmp/kubectl && sudo mv /tmp/kubectl /usr/local/bin/kubectl

# minikube
curl -Lo /tmp/minikube https://storage.googleapis.com/minikube/releases/latest/minikube-linux-amd64 && sudo install /tmp/minikube /usr/local/bin/minikube

# arrancar minikube con recursos
minikube delete --all || true
minikube start --driver=docker --memory=8192 --cpus=3

# instalar operator
kubectl create namespace awx || true
kubectl apply -k https://github.com/ansible/awx-operator/config/default?ref=2.19.1

# deploy AWX CR (ajusta si quieres)
cat <<EOF | kubectl apply -f -
apiVersion: awx.ansible.com/v1beta1
kind: AWX
metadata:
  name: awx
  namespace: awx
spec:
  service_type: NodePort
  nodeport_port: 32360
  ingress_type: none
  admin_user: admin
  admin_password_secret: awx-admin-password
  postgres_storage_class: "standard"
EOF

# abrir firewall
sudo firewall-cmd --add-port=32360/tcp --permanent
sudo firewall-cmd --reload

# ver pods
kubectl get pods -n awx -w

# obtener password admin
kubectl get secret awx-admin-password -n awx -o jsonpath="{.data.password}" | base64 --decode ; echo
```

---
## Recursos / docs (para referencia)
- AWX Operator repo & releases (usar `ref=2.19.1` si quieres estabilidad). [GitHub+1](https://github.com/ansible/awx-operator?utm_source=chatgpt.com)
- Documentación Basic Install AWX Operator (ReadTheDocs). [ansible.readthedocs.io](https://ansible.readthedocs.io/projects/awx-operator/en/latest/installation/basic-install.html?utm_source=chatgpt.com)

---

Si quieres, hago **uno de estos** ahora mismo (elige uno):
1. Te genero **un script final** y probado que hace todo lo anterior (instala herramientas, inicia minikube, instala operator, crea AWX CR) y te avisa cuándo entrar.
2. Te ayudo **en vivo** paso a paso: me pegas la salida de cada comando y yo te guío (recomendado si quieres evitar errores).
3. Te genero el YAML `awx-cr.yaml` exacto y un `kubectl apply -f` final — tú lo aplicas y yo te explico cómo verificar.
¿Cuál prefieres?