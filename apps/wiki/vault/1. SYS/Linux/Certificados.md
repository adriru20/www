## Instalar los certificados:
``` Bash
sudo cp ca.crt /usr/local/share/ca-certificates/
sudo update-ca-certificates
```

Generar certificado de autoridad:
``` Bash
openssl genrsa -out ca.key 4096

openssl req -x509 -new -nodes -sha512 -days 3650 –subj "/C=CN/ST=Beijing/L=Beijing/O=example/OU=Personal/CN=yourdomain.com" -key ca.key -out ca.crt
```
## Generar certificado de servidor:
``` Bash
openssl genrsa -out yourdomain.com.key 4096

openssl req -sha512 -new -subj "/C=CN/ST=Beijing/L=Beijing/O=example/OU=Personal/CN=yourdomain.com" -key yourdomain.com.key -out yourdomain.com.csr
```
## Generar el archivo "v3.ext" para Harbor:
``` Bash
cat > v3.ext <<-EOF
authorityKeyIdentifier=keyid,issuer
basicConstraints=CA:FALSE
keyUsage = digitalSignature, nonRepudiation, keyEncipherment, dataEncipherment
extendedKeyUsage = serverAuth
subjectAltName = @alt_names

[alt_names]
DNS.1=yourdomain.com
DNS.2=yourdomain
DNS.3=hostname
EOF
```