### WSL Network Mirroring
# Problema con la VPN de Global Protect[](https://dev.azure.com/aidteur001/TECH-DevOps-DeliveryCenterMadrid-ES/_wiki/wikis/Delivery%20Center%20Madrid/119/Instalar-y-configurar-WSL2?anchor=problema-con-la-vpn-de-global-protect)

## Configurar los adaptadores de red del WSL en "modo espejo" con los de Windows[](https://dev.azure.com/aidteur001/TECH-DevOps-DeliveryCenterMadrid-ES/_wiki/wikis/Delivery%20Center%20Madrid/119/Instalar-y-configurar-WSL2?anchor=configurar-los-adaptadores-de-red-del-wsl-en-%22modo-espejo%22-con-los-de-windows)

Si tenéis un Windows 11 actualizado (Windows 11 22H2 o superior), ahora es posible configurar WSL para que replique las conexiones de red de Windows. El WSL en lugar de actuar como una máquina virtual dentro de Windows actuará de forma similar a un programa ejecutado en Windows, replicando en tiempo real cada adaptador de red que se conecte a windows como un adaptador de red conectado a Linux. Lo que incluye las conexiones virtuales que crean las VPN, las conexiones WiFi, etc. Aunque en el Linux las creará como conexiones de cable "virtuales". No distinguiendo entre conexiones inalámbricas o de otro tipo. Estas conexiones virtuales serán compartidas entre todas las máquinas WSL instaladas.

Para configurar esta opción debemos crear un fichero en nuestra carpeta de usuario. A la que podemos acceder usando la variable de entorno de Windows **%USERPROFILE%** en el explorador de ficheros y pulsando intro:
![[devops_userprofile.png]]

Allí, en esa misma carpeta, crearemos un fichero llamado **.wslconfig** con el que se configuran todas las máquinas WSL instaladas. En ese fichero, a parte de otras configuraciones posibles como limitar el uso de memoria del WSL, establecer los núcleos de CPU disponibles u otras configuraciones similares, tenemos tres referentes a la configuración de red:

``` Bash
[wsl2]
networkingMode=mirrored
dnsTunneling=true
autoProxy=true
```

La primera de las opciones, **networkingMode** es la que nos permite la configuración en espejo. La segunda, **dnsTunneling**, nos pasa los dns de windows a Linux y la tercera, **autoProxy**, nos replica la configuración del proxy de Windows en el WSL.

Cuidado con la extensión del fichero **.wslconfig** porque Windows tiende a ocultar las extensiones por defecto y puede que tenga una extensión ".txt" o cualquier otra y así no funcionará. El nombre del fichero **no debe llevar extensión** de ningún tipo y hay que reiniciar tras crear el fichero. Para ver las extensiones ocultas en el explorador de fichero de Windows, desplegad View -> Show -> File name extensions:
![[devops_extensiones.png]]

Tenéis el resto de configuraciones disponibles en la documentación oficial de Microsoft en:
[Advanced settings configuration in WSL | Microsoft Learn](https://learn.microsoft.com/en-us/windows/wsl/wsl-config#configuration-settings-for-wslconfig) 

Y otros "hacks" de red en:
[Accessing network applications with WSL | Microsoft Learn](https://learn.microsoft.com/en-us/windows/wsl/networking) 

Es importante remarcar que numeración típica de estos dispositivos de red replicados en espejo (eth0, eth1, eth2...) será dinámica y podrá cambiar de una sesión a otra. A no ser que configuremos Linux para que asigne la misma conexión a cada dispositivo mediante la MAC. Lo cual es algo **opcional** pero que puede ser útil en derterminados escenarios. Para ello debemos crear ficheros en la carpeta **/etc/systemd/network/** (mediante **sudo**, puesto que el usuario administrador **root** es el propietarios de las carpetas del sistema):
``` Bash
sudo nano /etc/systemd/network/10-eth1.link
```

El nombre del fichero es indiferente pero suelen empezar por 10, 20 ,30, etc. por convención, para establecer el orden en que el sistema los carga, puesto que lo hace alfabéticamente. Dentro del fichero es donde indicaremos que MAC corresponde a qué dispositivo. Por ejemplo:
``` Bash
[Match]
PermanentMACAddress=02:50:41:00:00:11

[Link]
Name=eth1
```

Con esto, podremos establecer de forma estática a que dispositivos se les asigna que nombres de forma permanente. Para ver los dispositivos que tenemos usaremos:
``` Bash
ip link show
```

Y para ver las rutas de red de dichos dispositivos usaremos (reemplazando devX por el número correspondiente):
``` Bash
ip route show dev ethX
```

## Modificar la métrica de la conexión de red (**obsoleto si podéis montar la red en "modo espejo"**)[](https://dev.azure.com/aidteur001/TECH-DevOps-DeliveryCenterMadrid-ES/_wiki/wikis/Delivery%20Center%20Madrid/119/Instalar-y-configurar-WSL2?anchor=modificar-la-m%C3%A9trica-de-la-conexi%C3%B3n-de-red-\(**obsoleto-si-pod%C3%A9is-montar-la-red-en-%22modo-espejo%22**\))

Es posible que al conectar la VPN de Global Protect el WSL pierda el acceso a la red. Para solucionarlo hay que ir a visor de conexiones de red:
![[Pasted image 20260323164116.png]]

Entra a las propiedades de la conexión que nos crea el Global Protect cada vez que conecta (PANGP Virtual Ethernet Adapter):
![[Pasted image 20260323163939.png]]

Ir a las propiedades de TCP/IPv4:
![[Pasted image 20260323163949.png]]

Luego a las propiedades avanzadas:
![[Pasted image 20260323164001.png]]

Y por, último, cambiar la **Interface metric** de 1 a 6000:
![[Pasted image 20260323164020.png]]

Para hacer esto más ágil, puesto que hay que hacerlo cada vez que conectamos la VPN, desarrollé una instrucción que lo hace por nosotros, pero que debe ejecutarse en **modo administrador** puesto que modifica la configuración de la red. Por lo que se puede copiar en un script **.bat** o **.ps1** y ejecutarlo **como administrador** cada vez que se conecte la VPN:

``` Bash
Powershell.exe -ExecutionPolicy Bypass -Command "Get-NetAdapter | Where-Object {$_.InterfaceDescription -Match 'Virtual'} | Set-NetIPInterface -InterfaceMetric 6000"
```

Para hacer esto más ágil, puesto que hay que hacerlo cada vez que conectamos la VPN, desarrollé una instrucción que lo hace por nosotros, pero que debe ejecutarse en **modo administrador** puesto que modifica la configuración de la red. Por lo que se puede copiar en un script **.bat** o **.ps1** y ejecutarlo **como administrador** cada vez que se conecte la VPN:

``` Bash
Powershell.exe -ExecutionPolicy Bypass -Command "Get-NetAdapter | Where-Object {$_.InterfaceDescription -Match 'Virtual'} | Set-NetIPInterface -InterfaceMetric 6000"
```

# Instalar Docker[](https://dev.azure.com/aidteur001/TECH-DevOps-DeliveryCenterMadrid-ES/_wiki/wikis/Delivery%20Center%20Madrid/119/Instalar-y-configurar-WSL2?anchor=instalar-docker)

Desde la consola de Ubuntu podéis copiar entero este bloque de comandos y ejecutarlo. **Os pedirá la contraseña** de vuestro usuario para otorgar **permisos de super-usuario** (administrador) a los comandos que empiezen por sudo (**su**per-user **do**):

``` Bash
sudo apt-get update
sudo apt-get install ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

# Add the repository to Apt sources:
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update
sudo apt-get install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```
Tras ello, **añadimos nuestro usuario al grupo docker** (para que tenga permisos de ejecución de docker sin necesidad del comando sudo):

``` Bash
sudo usermod $USER -aG docker
```

**Cerramos la consola y la volvemos a abrir** (para forzar una nueva sesión del usuario con los nuevos permisos aplicados) y probamos el comando:

``` Bash
docker ps
```

Que mostrará algo así:
![[devops_docker.png]]# Instalar Git[](https://dev.azure.com/aidteur001/TECH-DevOps-DeliveryCenterMadrid-ES/_wiki/wikis/Delivery%20Center%20Madrid/119/Instalar-y-configurar-WSL2?anchor=instalar-git)

Para instalar git en Ubuntu:

``` Bash
sudo apt update
sudo apt install git
```

# Configurar Git para conectar con el servidor de Azure Repos[](https://dev.azure.com/aidteur001/TECH-DevOps-DeliveryCenterMadrid-ES/_wiki/wikis/Delivery%20Center%20Madrid/119/Instalar-y-configurar-WSL2?anchor=configurar-git-para-conectar-con-el-servidor-de-azure-repos)

## Crear un Token (contraseña para Azure)[](https://dev.azure.com/aidteur001/TECH-DevOps-DeliveryCenterMadrid-ES/_wiki/wikis/Delivery%20Center%20Madrid/119/Instalar-y-configurar-WSL2?anchor=crear-un-token-\(contrase%C3%B1a-para-azure\))

Nos dirigimos a la página web de nuestro proyecto de Azure Devops:

[Summary - Overview](https://dev.azure.com/aidteur001/TECH-DevOps-DeliveryCenterMadrid-ES/)

Pinchamos en la opción **Personal Access Token** en la esquina superior derecha:
![[devops_token.png]]

Pulsamos en New Token:

![[devops_newToken.png]]

Y, por último, le damos un **nombre** al token, permisos de **Full access** y una **fecha de expiración** (recomiendo el máximo que permite que es un año):

![[devops_tiempoToken.png|620]]

Como resultado nos dará un Token que podremos usar como contraseña para Azure DevOps. Es **muy importante guardar el token** puesto que no lo volverá a mostrar.

## Configurar git[](https://dev.azure.com/aidteur001/TECH-DevOps-DeliveryCenterMadrid-ES/_wiki/wikis/Delivery%20Center%20Madrid/119/Instalar-y-configurar-WSL2?anchor=configurar-git)

Desde la consola de Ubuntu ejecutamos esto para que git recuerde nuestra contraseña (el token) al comunicar con el servidor (y no ponerla cada vez, sino solo la primera vez, hasta que el token expire):

``` bash
git config --global credential.helper store
```

Para terminar, configuraremos nuestro email y nombre en git para "firmar" nuestros cambios en el código cuando usemos git:

``` Bash
git config --global user.name "nombre y apellidos"
git config --global user.email "tu.usuario@accenture.com"
```

Como curiosidad, el parámetro **--global** lo usaremos para que esos cambios afecten a todos los repositorios de nuestro ordenador. Sin el podríamos definir distintos usuarios para cada repositorio de la misma máquina, pero al tratarse de nuestro portátil tiene más sentido la opción global.

# Entendiendo la estructura de los directorios[](https://dev.azure.com/aidteur001/TECH-DevOps-DeliveryCenterMadrid-ES/_wiki/wikis/Delivery%20Center%20Madrid/119/Instalar-y-configurar-WSL2?anchor=entendiendo-la-estructura-de-los-directorios)

Linux hereda su estructura de carpeta del sistema Unix (antecesor común de Linux e MacOS que comparten estos mismos directorios):

![[devops_herenciaLinux.png]]

La estructura de carpetas de Linux en el WSL está separada de la del Windows que lo hospeda y hay que tener en cuenta que el Linux las carpetas cuelgan de la carpeta raíz **/** y las carpetas hijas se separan con la barra **/** y no con la contra-barra de Windows **\**. No obstante, podemos acceder a los ficheros que tengamos en nuestra carpetas de Windows desde la carpeta de Linux **/mnt/** donde se montan las unidades de windows (C:, D:, etc):

``` Bash
sudo ls /mnt/c
```

También podemos invocar al explorador de ficheros desde una carpeta de Linux para verla desde el explorador de ficheros:

```
explorer.exe
```

O acceder a los ficheros desde el explorador como carpetas compartidas:

```
\\wsl.localhost\Ubuntu
```

# Comandos[](https://dev.azure.com/aidteur001/TECH-DevOps-DeliveryCenterMadrid-ES/_wiki/wikis/Delivery%20Center%20Madrid/119/Instalar-y-configurar-WSL2?anchor=comandos)

Por último, aquí tenemos un pdf con un resumen de los principales comandos más usados:

[crakernano_linux-shell.pdf](obsidian://open?vault=Accenture&file=6.%20Docs%2Fpdf%2Fcrakernano_linux-shell-e45c458c-1d74-415a-87f4-8044043096ff.pdf)

También podemos obtener ayuda sobre el uso de los comandos mediante sus páginas de manual:

``` Bash
man grep
```

O, habitualmente, con el argumento **--help** o **-h** (es también un estándar no escrito que algunos argumentos se puedan invocar con dos guiones y una palabra o con su forma abreviada de un guion y una letra). El argumento **--help** nos suele indicar la forma de llamar al comando, los argumentos posibles y para que sirven:

``` Bash
grep --help
grep -h
```

Hay que tener en cuenta que la filosofía tras los comandos de Linux es que hagan una sola cosa pero que la hagan bien (por lo que suelen cumplir una sola función pero dan un montón de formas de llevarla a cabo mediante multiples argumentos opcionales). Y así, se logra también el objetivo de la modularidad, haciendo tareas complejas a base de sumar muchas simples, puesto que puedes redirigir la salida de cada comando a un comando siguiente mediante el uso de tuberías ( **|** ):

``` Bash
 # Lista el contenido de la carpeta raíz **/** con **ls**
 # Después filtra con **grep** los resultados que contengan HOME ignorando las mayúsculas y mostrando las 3 líneas superiores e inferiores de cada resultado (el contexto)
 # Y, tras ello, ordena lo obtenido con **sort** de forma invertida
 
 ls / | grep --ignore-case --context=3 HOME | sort --reverse

 # El mismo comando de forma abreviada

 ls / | grep -iC3 HOME | sort -r  
```