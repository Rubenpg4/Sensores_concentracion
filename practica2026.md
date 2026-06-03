# Sistemas empotrados y ubicuos

**Máster Universitario en Ingeniería Informática – Universidad de Jaén**  
**Curso 2025-26**  
**Módulo 1. Fusión de conocimiento**

---

## Práctica 1

### 1. Introducción

Vivimos en un mundo digital, rodeados de información sobre nosotros, los objetos en nuestro entorno, y sobre todo lo que está ocurriendo a nuestro alrededor. Dicha información es tremendamente valiosa y potencialmente útil para su aplicación en nuestro beneficio. *"Solo"* tenemos que saber cómo recogerla, procesarla, almacenarla, analizarla y, finalmente, aplicarla.

Un ejemplo de sistema que permite sacar provecho de la información reinante en nuestro entorno son las **Redes de Sensores** (en inglés, *Wireless Sensor Networks, WSN*).

Una **red de sensores** [Wikipedia] es un sistema de nodos (pequeños elementos de computación) interconectados entre sí, equipados con sensores para monitorizar condiciones físicas o ambientales (temperatura, sonido, etc.), y que colaboran para llevar a cabo una tarea común.

Se caracterizan por su facilidad de despliegue y por ser autoconfigurables; cada nodo puede ejercer diferentes funciones como emisor, receptor, ofrecer servicios de enrutamiento entre otros nodos no conectados directamente, así como registrar datos procedentes de los sensores locales. Otra característica habitual es su gestión eficiente de energía, lo que les permite obtener una alta tasa de autonomía.

En esta primera práctica implementaremos un prototipo de red de sensores, usando los medios disponibles, prestando especial atención al problema de la **fusión de conocimiento**, es decir, al modo en que combinaremos datos de diferente naturaleza y formato, obtenidos de distintas fuentes, para obtener un conocimiento que nos permita, por ejemplo, reconocer una actividad.

---

### 2. Objetivos

Se propone la implementación de un **prototipo de red de sensores** para recoger datos de nuestro entorno, y entender cómo procesarlos y almacenarlos convenientemente y, posteriormente, someterlos a un proceso de análisis e interpretación.

Con el objeto de optimizar los recursos a nuestro alcance, y de facilitar el acceso a los datos recogidos por los sensores, se propone usar los sensores disponibles en, por ejemplo, un Smartphone. No es preciso que el dispositivo sea de última generación ni tenemos por qué ajustarnos a una marca o modelo en concreto, ya que, al tratarse de un prototipo, para nuestros objetivos nos basta con contar con un número suficiente de sensores a los que podamos acceder.

En una primera etapa del proyecto práctico desarrollaremos un medio por el cual poder acceder a los **sensores disponibles en nuestro dispositivo** (GPS, acelerómetro, brújula, sensores de iluminación, proximidad, etc.), y **recoger** así los datos que éstos nos puedan facilitar. Debemos analizar la **naturaleza** de dichos datos para definir el **formato** en el que los representaremos, **rangos** de valores, etc.

Teniendo en cuenta que nuestro principal interés reside no solo en la recogida de dichos datos, sino en su almacenamiento y posterior análisis a lo largo del tiempo, otro aspecto que tendremos que considerar será el diseño e implementación del **sistema de bases de datos** necesario para recopilar dicha información. En esta etapa debemos considerar aspectos tales como, por ejemplo, cómo llevar a cabo la **comunicación** entre nuestra red de sensores y el servidor con la base de datos. Habrá que determinar si la comunicación será síncrona o asíncrona, el formato, estructura y tamaño de los paquetes de datos que se transmitirán, etc.

Finalmente, es interesante poder recuperar esa información almacenada en un formato amigable, que permita su **análisis e interpretación**. En esta tercera etapa debemos desarrollar una aplicación que nos permita acceder a la base de datos para mostrar los datos en el formato más adecuado. Podemos plantear diferentes alternativas, como, por ejemplo, implementar la herramienta como una aplicación web, app móvil, etc.

---

### 3. Recomendaciones

El objetivo de la práctica es el de desarrollar un prototipo lo más funcional posible, de forma que se puedan cubrir suficientemente las diferentes etapas descritas en el apartado anterior. Para ello, dadas las limitaciones en tiempo y recursos, es interesante y conveniente que los alumnos se puedan organizar en equipos de trabajo, con un número ideal de **2 integrantes** por equipo. En aquellos casos donde la complejidad del proyecto lo justificara, se podrán admitir equipos de mayor tamaño.

Los alumnos tendrán total libertad a la hora de elegir las herramientas que les resulten más cómodas y convenientes. Una interesante alternativa es **AWARE framework** (<https://awareframework.com/>), un sistema de código abierto que proporciona herramientas y apps para la automatización de recogida de datos procedentes de los sensores de un teléfono móvil. Es multiplataforma (iOS, Android) y admite la recolección y preparación de datos para ser exportados en múltiples formatos (CSV, MySQL).

---

### 4. Entrega de prácticas

Se habilitará una actividad de **entrega** de ejercicios en la plataforma, desde donde los grupos de prácticas podrán subir la memoria del trabajo, que debe estar en formato PDF. Es suficiente con que **uno de los miembros** del equipo suba el trabajo. Sólo se subirá a PLATEA dicha memoria, indicando (bien en la memoria, bien en un archivo de texto aparte) un enlace desde donde poder descargar el resto del trabajo (materiales multimedia, códigos fuentes, ejecutables, registros recogidos... cualquier material que consideréis que forma parte del trabajo práctico y que por tanto haya de ser tenido en cuenta para la evaluación).

La actividad estará abierta hasta el día **5 de junio de 2026, a las 23:59h**. Previamente, dicho día, en horario de clase, se habrá llevado a cabo la defensa y exposición del trabajo realizado.

Sobre la estructura de la memoria, ésta puede seguir una estructura similar a la siguiente:

- Portada
- Índice
- Introducción. Descripción del problema.
- Descripción de las posibles alternativas de solución. Justificación de la solución elegida, y de las herramientas empleadas.
- Descripción de las etapas de diseño y desarrollo del prototipo.
  - Enumeración y descripción del conjunto de sensores utilizado.
  - Diseño de la base de datos: Tablas, atributos y tipos.
  - Descripción y justificación del proceso de recogida de datos.
- Experimentación y resultados obtenidos.
  - Descripción de los datasets recogidos (nº de atributos, nº de casos, metadatos).
  - Visualización y descripción de casos de uso (si es aplicable).
- Conclusiones y autoevaluación.
- Bibliografía
- Apéndices (opcional): Manuales de instalación, gráficas, material multimedia, etc.

---

### 5. Defensa de prácticas

La defensa del trabajo práctico se llevará a cabo en la última sesión presencial del módulo, el día **5 de junio de 2026, de 15:30h a 17:30h**. Debe consistir en una breve exposición (10-15 minutos) del trabajo llevado a cabo por parte de cada uno de los grupos, en la que podrán apoyarse en el material multimedia que consideren oportuno (presentaciones, vídeos, demostraciones ad hoc...), y donde han de participar todos los integrantes del equipo.

---

### 6. Evaluación

El trabajo práctico tiene un peso del **40%** sobre la nota final del módulo, el cual, a su vez, tiene un peso del **33%** sobre el total de la asignatura. En la evaluación de este trabajo se tendrán en cuenta los siguientes aspectos:

- **Contenido del trabajo** (hasta un 50%): Medido como el grado de cumplimiento de los objetivos marcados, si se ha seguido una estructura similar o equivalente a la propuesta, descripción de la metodología seguida, análisis de los resultados, etc.
- **Presentación del trabajo** (hasta un 20%): Claridad en la exposición de la memoria, organización de los contenidos, originalidad de los contenidos, uso adecuado de la bibliografía referenciada, etc.
- **Defensa del trabajo** (hasta un 30%): Calidad de los materiales usados en la exposición oral final del trabajo realizado, claridad en la exposición, grado de asimilación de los contenidos teóricos y prácticos por parte del equipo de trabajo, grado de interacción entre los miembros del equipo, ajuste al límite de tiempo establecido, etc.
