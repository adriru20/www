# Requirements
## Service User and Packages to install
Make a service user that will be used to deploy AWX
```bash
useradd -c "AWX User for kubernetes" -d /home/awx -m -s /bin/bash awx
```
Install k3s using this command
```bash
curl -sfL https://get.k3s.io | sh -
```
Give the ownership of k3s.yaml to awx service user
```bash
chown awx:awx /etc/rancher/k3s/k3s.yaml
```
Install kustomize
```bash
curl -s "https://raw.githubusercontent.com/kubernetes-sigs/kustomize/master/hack/install_kustomize.sh" | bash
```
Move the binary to the following route
```bash
mv kustomize /usr/local/bin/
```
# Installation
Switch to the service user
```bash
sudo su - awx
```
Make certificate directory
```bash
mkdir certs
```
Create the files inside the home directory of `awx` user
```bash
touch awx.yaml ingress.yaml kustomization.yaml 
```
Contents of those files are here:

`awx.yaml`
```yaml
---
apiVersion: awx.ansible.com/v1beta1
kind: AWX
metadata:
  name: awx
spec:
  service_type: nodeport
  nodeport_port: 30080
  extra_env:
    - name: TZ
      value: Europe/Madrid
```

`ingress.yaml`
```yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: awx-ingress
  namespace: awx
  annotations:
    nginx.ingress.kubernetes.io/rewrite-target: /
spec:
  tls:
  - hosts:
    - awx.pplcanfly.com
    secretName: my-tls-secret
  rules:
  - host: awx.pplcanfly.com
    http:
      paths:
      - path: /
        pathType: Prefix
        backend:
          service:
            name: awx-service
            port:
              number: 80
```

`kustomization.yaml`
```yaml
apiVersion: kustomize.config.k8s.io/v1beta1
kind: Kustomization
resources:
  # Find the latest tag here: https://github.com/ansible/awx-operator/releases
  - github.com/ansible/awx-operator/config/default?ref=2.19.1
  - awx.yaml
  - ingress.yaml

# Set the image tags to match the git version from above
images:
  - name: quay.io/ansible/awx-operator
    newTag: 2.19.1

# Specify a custom namespace in which to install AWX
namespace: awx
```

>[!WARNING]
>ingress.yaml contains my own configuration and domain name, change it to fit your needs. Or if you dont plan on using HTTPS just comment out the kustomization.yaml contents which contain ingress.yaml

```yaml
apiVersion: kustomize.config.k8s.io/v1beta1
kind: Kustomization
resources:
  # Find the latest tag here: https://github.com/ansible/awx-operator/releases
  - github.com/ansible/awx-operator/config/default?ref=2.19.1
  - awx.yaml
  #- ingress.yaml

# Set the image tags to match the git version from above
images:
  - name: quay.io/ansible/awx-operator
    newTag: 2.19.1

# Specify a custom namespace in which to install AWX
namespace: awx
```
# Running the kubernetes pod
```bash
kustomize build .| kubectl apply -f-
```
This will show how many pods are running
```bash
kubectl get pods --namespace awx
```
Example of all the pods when they go online and you shoud try to get the password
```text
awx@awx:~$ kubectl get pods --namespace awx
NAME                                               READY   STATUS    RESTARTS       AGE
awx-operator-controller-manager-76d45c5fff-kx9qj   2/2     Running   0              12d
awx-postgres-15-0                                  1/1     Running   9 (20d ago)    65d
awx-task-56b75cd5df-vxgx5                          4/4     Running   0              21h
awx-web-64877df5-8sxp4                             3/3     Running   27 (20d ago)   65d
```
Get the password for the admin user
```bash
kubectl get secret --namespace awx awx-admin-password -o jsonpath="{.data.password}" | base64 --decode ; echo
```
That command should give you the `admin` password for the web interface, once you get the password login to:

http://YOUR_SERVER_IP:30080

# Enforcing HTTPS
Create a self-signed cert for 10 years, it should put the 2 files in ./certs
```bash
mkdir -p certs && openssl req -x509 -nodes -newkey rsa:4096 -days 3650 \
-keyout certs/key.pem -out certs/cert.pem -subj "/CN=localhost"
```
Uncomment ingress.yaml line
```yaml
apiVersion: kustomize.config.k8s.io/v1beta1
kind: Kustomization
resources:
  # Find the latest tag here: https://github.com/ansible/awx-operator/releases
  - github.com/ansible/awx-operator/config/default?ref=2.19.1
  - awx.yaml
  - ingress.yaml

# Set the image tags to match the git version from above
images:
  - name: quay.io/ansible/awx-operator
    newTag: 2.19.1

# Specify a custom namespace in which to install AWX
namespace: awx
```
Create a secret for the pods
```bash
kubectl create secret tls my-tls-secret --cert=certs/certificate.crt --key=certs/private.key --namespace=awx
```
Applying changes
```bash
kubectl apply -k .
```

For future changes, you should delete the actual secret, and repeat the steps for Enforcing HTTPS, this ensures the new certificates are loaded properly. This only applies if you use legit issued certificates and they do expire after 1 year. 
```bash
kubectl delete secret my-tls-secret --namespace awx
```

Login using the HTTPS method

https://YOUR_SERVER_IP:30080

# Useful commands
Deleting pods that failed or unknown and shit like that
```bash
kubectl delete pods -n awx --field-selector=status.phase!=Running,status.phase!=Succeeded
```