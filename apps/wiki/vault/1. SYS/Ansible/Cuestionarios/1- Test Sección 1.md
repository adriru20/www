# Preguntas y respuestas correctas:
1. ¿Cómo se llama el agente de Ansible?
	- ansible-pull
	- ansible-cli
	- ==Ansible no tiene un agente==
2. ¿Cuál es el fichero, por defecto, que contiene la lista de servidores?
	- /etc/ansible/servers
	- ==/etc/ansible/hosts==
	- /etc/hosts
	- /etc/ansible/inventory/hosts
3. Qué módulo permite comprobaqr la conexión al usar el modo ad-hoc
	- ==ping==
	- check
	- connect
	- check_connect
4. La única manera de conectar a un servidor es utilizando clave publica SSH
	- Sí
	- ==No, es posible usar usuario y contraseña también.==
5. ¿Cuál es la variable para definir el tipo de conexión (SSH, local, windows)
	- ansible_conn
	- ==ansible_connection==
	- host_conn
	- host_connection
6. En qué directorios se asignan las variables para groups y servidores.
	- ==/etc/ansible/group_vars y /etc/ansible/host_vars==
	- /etc/ansible/vars_group y /etc/ansible/var_host
	- /etc/ansible/groups_vars y /etc/ansible/hosts_vars
	- /etc/ansible/vars_groups y /etc/ansible/var_hosts
7. Cómo se especifica un inventario dinámico
	- -a fichero.py ó -a directorio/
	- -e fichero.py ó -e directorio/
	- ==-i fichero.py ó -i directorio/==
8. Dónde se aloja el fichero de configuración global de Ansible
	- /etc/ansible/ansible.ini
	- ==/etc/ansible/ansible.cfg==
	- /etc/ansible/ansible.conf
9. Qué servicio utiliza Ansible para administrar equipos Windows
	- SSH
	- RDP
	- ==WinRM==
