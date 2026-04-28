``` Bash
network:
  ethernets:
    enp0s3:
      dhcp4: false
      addresses: [192.168.1.6/24]
      gateway4: 192.168.1.1
      nameservers:
        addresses: [1.1.1.1,8.8.8.8]

  version: 2
```
