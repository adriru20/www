## INFO
---
### INTENCIÓN
- Intenta fusionar al Developer con el Operador de Sistemas.
- Uno de sus principios es hacer los procesos más eficientes, eliminando pasos siempre y cuando que sea posible.
---
### REQUISITOS
- S.R.E. (Site Reliability Engineer = Ingeniero de confiabilidad del sitio).
- No hace falta saber programación intensiva ni diseño de software.
---
### CICLO DE VIDA
#### Intención del ciclo de vida
- La intención es que nunca se detenga porque fallo algo del código o servidor, falte alguna parte de código o servidor, etc.
#### Concepto visual del ciclo de vida
![[DEVOPS-Ciclo DevOps.png|600]]
#### Pasos del ciclo de vida
##### 1. PLAN
- <mark style="background: #FF5582A6;color:rgb(255,255,255);">Analizar el requerimiento o funcionalidad del proyecto y planificarlo</mark> para ver como se desarrolla dependiendo de la metodología que se quiera usar.
##### 2. CODE
- <mark style="background: #FF5582A6;color:rgb(255,255,255);">Se asigna el trabajo</mark> o las tareas a los programadores y se empieza a escribir el código según asignación.
##### 3. BUILD
- También llamada compilación ya que los programadores <mark style="background: #FF5582A6;color:rgb(255,255,255);">juntan todo el código ya creado</mark> en el anterior paso <mark style="background: #FF5582A6;color:rgb(255,255,255);">y se integra</mark> en el master que <mark style="background: #FF5582A6;color:rgb(255,255,255);">genera un paquete o un ejecutable tras pasar los test de integración</mark>.
##### 4. TEST
- Aquí <mark style="background: #FF5582A6;color:rgb(255,255,255);">se prueba el Software</mark> para probar que funciona <mark style="background: #FF5582A6;color:rgb(255,255,255);">según los requerimientos</mark> que se solicitaron en el paso [[$ Info General#1. PLAN|1. PLAN]].
##### 5. RELEASE
- <mark style="background: #FF5582A6;color:rgb(255,255,255);">Tras pasar los test</mark> se crea un snapshot, artefacto o ejecutable listo para <mark style="background: #FF5582A6;color:rgb(255,255,255);">mandarlo al paso -></mark> [[$ Info General#6. DEPLOY|6. DEPLOY]].
##### 6. DEPLOY
- <mark style="background: #FF5582A6;color:rgb(255,255,255);">Enviar el paquete o ejecutable a los servidores de PROD.</mark>
##### 7. OPERATE
- Es constante y ocurre todo el tiempo, son las tareas de <mark style="background: #FF5582A6;color:rgb(255,255,255);">configuración de sistemas, optimización, implementación de infraestructuras, servidores, bases de datos, réplicas, etc</mark>.
- Aquí intervienen los <mark style="background: #FF5582A6;color:rgb(255,255,255);">arquitectos Cloud</mark>.
##### 8. MONITOR
- <mark style="background: #FF5582A6;color:rgb(255,255,255);">Monitorizar todo el entorno para saber donde ha fallado o donde ha dejado de prestar servicio</mark>.
---
### CI/CD
#### CI = Continuous Integration (Integración Continua)
Mezclar el código continuamente, ya que se realizan las pruebas automáticamente comprobando que cumplan con estándares, políticas establecidas, que no entre en conflicto con otro código. Si cumple es <mark style="background-color:rgb(0, 176, 80);color:rgb(255,255,255);">luz verde</mark> y si no cumple es <mark style="background-color:rgb(255, 0, 0);color:rgb(255,255,255);">luz roja</mark> y tienes que arreglar el código.
#### CD = Continuous Delivery, Continuous Deploy y Continuous Distribution
> [!example] Resumen de las 3 CD
> <mark style="background: #ADCCFFA6;">C. Distribution</mark> = <mark style="background: #FFB8EBA6;">C. Delivery</mark> + <mark style="background: #D2B3FFA6;">C. Deploy</mark>
1. <mark style="background: #FFB8EBA6;">C. Delivery</mark>: es pasar el código que ya paso la parte de [[$ Info General#CI = Continuous Integration (Integración Continua)|CI]] y crea un ejecutable listo para producción.
2. <mark style="background: #D2B3FFA6;">C. Deploy</mark>: Mandarlo a producción directamente.
3. <mark style="background: #ADCCFFA6;">C. Distribution</mark>: Es la manera de agrupar <mark style="background: #FFB8EBA6;">C. Delivery</mark> y <mark style="background: #D2B3FFA6;">C. Deploy</mark>.
---
### HERRAMIENTAS
1. Planeación
	- Jira para desarrollar en <mark style="background: #FF5582A6;color:rgb(255,255,255);">Scrum</mark>.
	- Asana
	- Trello
	- Notion (Tiene para: BBDD, Documentos, Visualizador)
2. Código
	- GitHub
	- GitLab (Más orientado a DevOps)
3. Compilación
	- Apache Maven (Compila, empaqueta y gestiona de dependencias del software)
	- Gradle (Compila, empaqueta y gestiona de dependencias del software, pruebas y políticas para el código)
4. Testing
	- Selenium
	- Gremlin
5. Release y Deploy = CI/CD
	- Se hacen desde consola:
		- Jenkins
		- GitHub Actions
		- DC/CI con GitLab
	- Se hace desde código (Tener nuestro recetario de bloques de código y poder usarlo al gusto del consumidor)
		- Chef
		- Ansible
		- Puppet
		- Terraform
6. Operaciones (Microservicios / Contenedores)
	- Docker
	- Kubernetes (Implementaciones en AWS=> AWS EKS y en Google=> GKE - Google Kubernetes Engine)
7. Monitorización
	- New Relic
	- Amazon Cloud Watch
	- Grafana
	- Prometheus
---
## BIBLIOGRAFÍA
---
- [Explicación de EDteam](https://youtu.be/MtDFK-evWw4?si=HAtH7g9jhJ22azkd)