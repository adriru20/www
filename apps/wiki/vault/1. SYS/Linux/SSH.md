En el fichero `/etc/ssh/sshd_config` buscamos `#PermitRootLogin prohibit-password`, lo descomentamos y cambiamos <span style="color:rgb(255, 0, 0)">prohibit-password</span> por <span style="color:rgb(0, 176, 80)">yes</span> y ponemos el comando `sudo systemctl restart ssh`.

Eliminar keygen SSH:
``` Bash
ssh-keygen -f "/home/xe82187/.ssh/known_hosts" -R "10.0.2.68"
```