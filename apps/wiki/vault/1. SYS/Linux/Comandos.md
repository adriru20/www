Borrar programas por completo:
``` Bash
sudo apt-get --purge remove <program>
```

Ver lista de paquetes que se pueden upgradear:
``` Bash
sudo apt list --upgradable
```

Cambiar el idioma del teclado a Español:
``` Bash
sudo loadkeys es
```

Habilitar el acceso vía web por el puerto <span style="color:rgb(0, 176, 80)">9090</span>:
``` Bash
sudo systemctl enable --now cockpit.socket
```

Mostrar fichero sin comentarios ni líneas vacías:
``` Bash
sudo cat /etc/ssh/sshd_config | grep -v -e '^#' -e '^$'
```