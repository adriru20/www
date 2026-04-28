## Comprobación:
``` Bash
uname -r

docker --version
systemctl status docker --no-pager
docker ps -a

minikube version
minikube status

kubectl version --client --output=yaml
kubectl get nodes
kubectl get pods -A
kubectl get namespaces
kubectl get awx -A
kubectl get pods -A | grep awx
kubectl get crd | grep awx

ansible --version
```
## Borrar:
``` Bash
docker stop $(docker ps -aq)
docker rm $(docker ps -aq)
docker ps -a
docker system prune -a --volumes -f
systemctl stop docker
dnf remove -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
rm -rf /var/lib/docker /var/lib/containerd

rm -rf ~/.kube
rm -rf ~/.minikube
dnf remove -y kubectl

docker ps -a
docker images
kubectl get pods -A

ansible --version
sudo dnf remove podman-docker docker-ce-cli
dnf list installed | grep -E 'docker|podman'
sudo dnf remove docker-ce docker-ce-rootless-extras podman
sudo dnf autoremove
sudo dnf clean all
which docker
```