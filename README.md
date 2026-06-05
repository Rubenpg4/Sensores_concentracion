<p align="center">
  <img src="https://via.placeholder.com/200x200.png?text=Exoesqueleto+Logo" alt="Exoesqueleto Logo" width="200"/>
</p>

<h1 align="center">🦾 Proyecto Exoesqueleto</h1>

<p align="center">
  <strong>Sistema robótico ponible open-source para asistencia y rehabilitación motriz</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/C%2B%2B-00599C?style=for-the-badge&logo=c%2B%2B&logoColor=white" alt="C++"/>
  <img src="https://img.shields.io/badge/Arduino-00979D?style=for-the-badge&logo=Arduino&logoColor=white" alt="Arduino"/>
  <img src="https://img.shields.io/badge/ROS-22314E?style=for-the-badge&logo=ros&logoColor=white" alt="ROS"/>
  <img src="https://img.shields.io/badge/Hardware-ESP32%20%7C%20BLDC%20%7C%20EMG-blue?style=for-the-badge" alt="Hardware"/>
  <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge" alt="License"/>
  <img src="https://img.shields.io/badge/Version-1.0.0-orange?style=for-the-badge" alt="Version"/>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Estado-Prototipo%20Activo-brightgreen?style=flat-square" alt="Status"/>
  <img src="https://img.shields.io/badge/Aplicación-Asistencia%20Médica-purple?style=flat-square" alt="Application"/>
</p>

---

## 📋 Tabla de Contenidos

- [🎯 El Problema que Resuelve](#-el-problema-que-resuelve)
- [✨ Características Principales](#-características-principales)
- [🔧 Tech Stack & Hardware](#-tech-stack--hardware)
- [🚀 Instalación y Quick Start](#-instalación-y-quick-start)
- [🗂️ Estructura del Proyecto](#️-estructura-del-proyecto)

---

## 🎯 El Problema que Resuelve

El **Proyecto Exoesqueleto** está diseñado para ofrecer soporte activo a pacientes con movilidad reducida o en procesos de rehabilitación, democratizando el acceso a tecnología robótica de asistencia.

| Limitación Actual | Solución Implementada |
|---------------------|----------------------|
| Terapias dependientes de fuerza física del terapeuta | **Asistencia motriz automatizada** con control de torque adaptativo |
| Falta de métricas de evolución precisas | **Registro telemétrico** de fuerza, ángulos articulares y tiempos |
| Equipos hospitalarios estáticos y muy costosos | **Diseño portable y de bajo coste** basado en componentes open-source e impresión 3D |
| Fatiga muscular en uso prolongado o resistencia | **Control predictivo** basado en señales EMG (Electromiografía) para leer la intención de movimiento |

---

## ✨ Características Principales

### 🦾 Hardware & Mecánica
- ✅ Estructura ligera paramétrica con piezas impresas en 3D (PETG/Fibra de carbono).
- ✅ Actuadores BLDC (Brushless) de alto torque con encoders magnéticos absolutos.
- ✅ Sensores de fuerza (FSR) en plantillas y sensores musculares de superficie (EMG).
- ✅ Pack de baterías LiPo para 4+ horas de autonomía operativa.

### 🧠 Software & Control
- ✅ Control PID de admitancia para garantizar una asistencia fluida y natural.
- ✅ Fusión de datos (IMU + EMG) para predecir la fase de la marcha.
- ✅ Interfaz gráfica (GUI) de monitoreo en tiempo real vía Wi-Fi/Bluetooth.
- ✅ Sistema de seguridad redundante (parada de emergencia por software y hardware).

---

## 🔧 Tech Stack & Hardware

### Firmware y Control
| Tecnología | Propósito |
|------------|-----------|
| **C / C++** | Lógica de control de bajo nivel y tiempo real |
| **FreeRTOS** | Sistema operativo en tiempo real para paralelismo en el microcontrolador |
| **ROS (Noetic/Humble)** | Middleware para telemetría, logging y control de alto nivel |

### Hardware Principal
| Componente | Especificación | Propósito |
|---------|---------|-----------|
| **Teensy 4.1 / ESP32** | Microcontrolador | Cerebro central de procesamiento y conectividad |
| **Motores BLDC** | 24V con reductora planetaria | Actuadores principales de cadera y rodilla |
| **MPU6050 / BNO085** | IMU 6-DOF / 9-DOF | Sensor de orientación y aceleración espacial |
| **MyoWare 2.0** | Sensores EMG | Lectura analógica de contracción muscular |

---

## 🚀 Instalación y Quick Start

### Prerrequisitos

```bash
# 1. Instalar entorno de desarrollo (PlatformIO o Arduino IDE)
# 2. Instalar dependencias de ROS (si se compila el nodo de telemetría)
sudo apt install ros-noetic-desktop-full
```

### Compilación y Flasheo

```bash
# 1. Clonar el repositorio
git clone https://github.com/tu-usuario/Exoesqueleto.git
cd Exoesqueleto/firmware

# 2. Instalar dependencias de librerías
pio pkg install

# 3. Compilar y subir el firmware a la placa
pio run --target upload
```

---

## 🗂️ Estructura del Proyecto

```text
📦 Exoesqueleto
 ┣ 📂 firmware/         # Código C/C++ para el microcontrolador (PlatformIO)
 ┃ ┣ 📂 src/            # Lógica principal de control PID y lectura de sensores
 ┃ ┗ 📂 lib/            # Librerías custom para drivers de motores
 ┣ 📂 hardware/         # Diseños mecánicos y PCBs
 ┃ ┣ 📂 3d_models/      # Archivos STL/STEP de la estructura
 ┃ ┗ 📂 pcb/            # Esquemáticos y ruteo de la placa de control (KiCad)
 ┣ 📂 ros_telemetry/    # Nodos de ROS para interfaz de usuario
 ┗ 📜 README.md         # Documentación principal
```
