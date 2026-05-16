## Pasos
Justo al instalar Windows 10 y borrar todos los discos hacemos lo siguiente:
1. CTRL + F10
2. diskpart
3. select disk <n_disk>
4. clean
5. create partition primary
6. select partition 1
7. active
8. format fs=NTFS
9. Esperar a que se formatee el disco por completo
10. assign
11. exit
## Bibliografía
[Información adquirida de aquí](https://computerhoy.com/paso-a-paso/hardware/como-formatear-tu-disco-duro-completo-windows-cmd-62604)