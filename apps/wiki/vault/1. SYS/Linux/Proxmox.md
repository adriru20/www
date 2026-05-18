Eliminar mensaje de subscripción:
``` Bash
Ext.Msg.show({
title: gettext('No valid subscription'),
```

Añadir al principio de la primera linea esto:
``` Bash
void({ //
```

Y ejecutar:
``` Bash
systemctl restart pveproxy.service
```

Actualizar distribución:
``` Bash
apt update
apt dist-upgrade
```
```
reboot
```
## Bibliografía:
- [https://www.nosolohacking.info/desactivar-notificacion-de-licencia-en-proxmox/](https://www.nosolohacking.info/desactivar-notificacion-de-licencia-en-proxmox/)