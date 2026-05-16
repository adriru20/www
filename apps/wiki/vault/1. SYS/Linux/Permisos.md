Comandos para dar permisos en Debian o RedHat en el fichero `/etc/sudoers`:
``` Bash
sudo usermod -aG sudo user
sudo usermod -aG wheel user
```

Establecer todos los permisos a un usuario:
``` Bash
user    ALL=(ALL:ALL) ALL
```

Eliminar tiempo de retardo para la contraseña:
``` Bash
Defaults        timestamp_timeout=-1
```

Eliminar contraseña de un usuario (Convertir en Password Less):
``` Bash
user ALL=(ALL) NOPASSWD: ALL
```