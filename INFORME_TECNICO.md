# INFORME TÉCNICO COMPLETO — PROYECTO SENSORES (WSN)

> **Asignatura:** Redes de Sensores Inalámbricos  
> **Máster en Ingeniería Informática — Universidad de Jaén**  
> **Curso:** 2025-26  
> **Fecha de entrega:** 5 de junio de 2026, 23:59  
> **Defensa:** 5 de junio de 2026, 15:30–17:30

---

## 1. VISIÓN GENERAL DEL PROYECTO

**Sensores** es un prototipo de Red de Sensores Inalámbricos (WSN) cuyo objetivo es detectar en tiempo real si el usuario está **concentrado o distraído**, fusionando datos de **5 sensores de smartphone** mediante un índice ponderado. Los datos los recoge la app **Sensor Logger** instalada en el teléfono, se envían a un servidor local con **PHP + MySQL**, y se visualizan en un **Progressive Web App (PWA)** que se instala como app nativa en el móvil.

El sistema demuestra el concepto de **fusión de conocimiento** (*knowledge fusion*): combinar señales heterogéneas de sensores independientes para reconocer un estado de actividad del usuario (nivel de concentración).

---

## 2. ARQUITECTURA DEL SISTEMA

```
┌──────────────────────────────────────────────┐
│  Sensor Logger (Android / iOS)               │
│  Recoge 5 sensores en tiempo real            │
│  Envía POST cada ~500ms a /api/ingest.php    │
└──────────────────────┬───────────────────────┘
                       │ HTTPS (Cloudflare Tunnel)
                       ▼
┌──────────────────────────────────────────────┐
│  Servidor Local (Laragon: Apache + PHP)      │
│  http://localhost/Sensores/                  │
│                                              │
│  /api/ingest.php        ← POST (ingesta)     │
│  /api/api_realtime.php  ← GET (tiempo real)  │
│  /api/analytics.php     ← GET (histórico)    │
└──────────────────────┬───────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────┐
│  MySQL: wsn_sensors                          │
│  5 tablas (una por sensor)                   │
└──────────────────────┬───────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────┐
│  PWA Dashboard (HTML + CSS + JS)             │
│  Instalable en móvil como app nativa         │
│  Polling cada 1s (tiempo real)               │
│  Polling cada 60s (histórico)                │
└──────────────────────────────────────────────┘
```

**Acceso remoto:** `cloudflared tunnel --url http://localhost:80` genera una URL HTTPS pública (`https://[palabras-aleatorias].trycloudflare.com`) que se configura como destino de envío en Sensor Logger. La URL cambia con cada reinicio del túnel.

---

## 3. DATOS QUE SE REGISTRAN

### 3.1 Tablas de la base de datos (`wsn_sensors`)

| Tabla | Columnas | Unidad | Sensor físico |
|---|---|---|---|
| `accelerometer` | `id`, `timestamp_ms`, `axis_x`, `axis_y`, `axis_z` | m/s² | Acelerómetro lineal |
| `brightness` | `id`, `timestamp_ms`, `brightness_level` | Lux (0–5000) | Sensor de luz ambiente/pantalla |
| `microphone` | `id`, `timestamp_ms`, `dbfs_level` | dBFS (−50 a 0) | Micrófono |
| `gravity` | `id`, `timestamp_ms`, `axis_x`, `axis_y`, `axis_z` | m/s² | Sensor de gravedad |
| `gyroscope` | `id`, `timestamp_ms`, `axis_x`, `axis_y`, `axis_z` | rad/s | Giroscopio |

- **Timestamp:** `BIGINT` en milisegundos desde Unix Epoch. Sensor Logger envía nanosegundos → se dividen por 1.000.000 en `ingest.php`.
- **Índices:** `timestamp_ms` indexado en las 5 tablas para acelerar las consultas por rango de tiempo.

---

## 4. CÓMO SE RECOGEN LOS DATOS (SENSOR LOGGER)

### 4.1 App Sensor Logger

Sensor Logger es una aplicación móvil (Android/iOS) que accede a los sensores del hardware del teléfono y los transmite vía HTTP POST a una URL configurable.

**Formato JSON enviado por Sensor Logger:**

```json
{
  "payload": [
    {
      "name": "accelerometer",
      "time": 1690000000000000000,
      "values": {"x": 0.5, "y": -0.1, "z": 9.8}
    },
    {
      "name": "microphone",
      "time": 1690000000000000000,
      "values": {"dBFS": -35.2}
    }
  ]
}
```

### 4.2 Procesamiento en `ingest.php`

1. Validación del JSON y cabeceras CORS (`Access-Control-Allow-Origin: *`)
2. Conversión de nanosegundos → milisegundos (`÷ 1.000.000`)
3. Preparación de 5 `PreparedStatements` PDO (uno por tabla)
4. Apertura de una **transacción MySQL** para inserción atómica del lote completo
5. Enrutamiento de cada lectura a su tabla según el campo `name`
6. `COMMIT` si todo va bien, `ROLLBACK` en caso de error
7. En caso de error de validación, guarda el payload en `api/debug_payload.log`

**Errores HTTP devueltos:**
- `HTTP 500` — fallo de conexión a la base de datos
- `HTTP 400` — JSON malformado o campo `payload` ausente

### 4.3 Tasas de muestreo típicas

| Sensor | Hz típico | Lecturas/hora | Almacenamiento estimado/hora |
|---|---|---|---|
| Acelerómetro | 100 Hz | 360.000 | ~1,2 MB |
| Giroscopio | 100 Hz | 360.000 | ~1,2 MB |
| Gravedad | 100 Hz | 360.000 | ~1,2 MB |
| Micrófono | 50 Hz | 180.000 | ~0,6 MB |
| Brillo | 10 Hz | 36.000 | ~0,12 MB |
| **TOTAL** | — | ~1.296.000 | **~4,3 MB/hora** |

---

## 5. ALGORITMO DE FUSIÓN: ÍNDICE DE CONCENTRACIÓN

### 5.1 Concepto

El sistema combina 4 señales de sensor en un índice 0–100 mediante suma ponderada de **puntuaciones normalizadas**. El umbral de decisión es **75**: índice ≥ 75 → CONCENTRADO, índice < 75 → DISTRAÍDO.

### 5.2 Función de normalización

```php
getScore(valor, min_val, max_val, invertido = false):
  pct = clamp((valor - min_val) / (max_val - min_val), 0.0, 1.0)
  if (invertido) pct = 1.0 - pct
  return pct * 100
```

Mapea cualquier valor al rango `[0, 100]` según los extremos definidos. El flag `invertido` invierte la escala (útil cuando menor valor = mayor concentración).

### 5.3 Los 4 componentes del índice

#### Componente 1 — Micrófono (Peso: 10%)

- **Medida:** Media del nivel de audio en las últimas 50 lecturas (~1 segundo)
- **Rango:** −45 dBFS (silencio) a −20 dBFS (conversación/TV activa)
- **Invertido:** Sí — menos ruido = mayor concentración
- **Fórmula:** `scoreMic = getScore(avg_mic, -45, -20, inverted=true)`

#### Componente 2 — Gravedad eje Z (Peso: 25%)

- **Medida:** Media del eje Z gravitacional en las últimas 50 lecturas
- **Rango:** 8,0 – 9,0 m/s²
- **Lógica:** Indica si el teléfono está boca abajo sobre la mesa (posición de estudio)
- **Fórmula:** `scoreGrav = getScore(abs(avg_grav_z), 8.0, 9.0, inverted=false)`

#### Componente 3 — Giroscopio / Varianza de rotación (Peso: 40% — el mayor)

- **Medida:** `(MAX−MIN) de eje X + (MAX−MIN) de eje Y + (MAX−MIN) de eje Z` en las últimas 50 lecturas
- **Lógica:** Alta varianza = usuario girando/moviendo el teléfono = distracción
- **Invertido:** Sí — menos rotación = mayor concentración
- **Sensibilidad adaptativa según estado de pantalla:**
  - Pantalla **OFF** (brillo = 0): rango `[0.1, 0.8]` — más tolerante
  - Pantalla **ON**: rango `[0.01, 0.1]` — muy sensible (10× más estricto)
- **Fórmula:** `scoreGyro = getScore(gyro_var, [rango_adaptativo], inverted=true)`

#### Componente 4 — Acelerómetro / Varianza de movimiento (Peso: 25%)

- **Medida:** `(MAX−MIN) de eje X + (MAX−MIN) de eje Y + (MAX−MIN) de eje Z` en las últimas 50 lecturas
- **Lógica:** Alta varianza = usuario moviendo/agitando el teléfono = distracción
- **Invertido:** Sí — menos movimiento = mayor concentración
- **Sensibilidad adaptativa según estado de pantalla:**
  - Pantalla **OFF**: rango `[0.3, 2.0]` — más tolerante
  - Pantalla **ON**: rango `[0.05, 0.3]` — muy sensible
- **Fórmula:** `scoreAccel = getScore(accel_var, [rango_adaptativo], inverted=true)`

### 5.4 Fórmula base del índice

```
concentration_index = (scoreMic   × 0.10)
                    + (scoreGrav  × 0.25)
                    + (scoreGyro  × 0.40)
                    + (scoreAccel × 0.25)
```

### 5.5 Overrides y ajustes especiales

#### A) Detección de "Reels" (penalización: −40 puntos)

Cuenta los segundos en los últimos 45 segundos donde se cumplan **simultáneamente**:
- Amplitud de acelerómetro > 0.05 (gesto de scroll vertical)
- Nivel de micrófono > −35 dBFS (entorno con audio activo)

Solo se aplica si la pantalla está encendida.  
**Interpretación:** El usuario está haciendo scroll en redes sociales (TikTok, Instagram Reels) con audio de fondo → penalización severa de distracción.

#### B) Override boca abajo (fuerza índice = 100)

Si `gravity_z > 7.5` → `concentration_index = 100` incondicionalmente.  
**Interpretación:** Teléfono boca abajo sobre la mesa = usuario estudiando sin usar el móvil → concentración máxima.

#### C) Clamp final

```
concentration_index = max(0, min(100, concentration_index))
```

### 5.6 Resumen de pesos

| Sensor | Peso | Justificación |
|---|---|---|
| Giroscopio (rotación) | **40%** | Indicador más fiable de uso activo del dispositivo |
| Gravedad Z (orientación) | 25% | Detecta posición de "estudio" (boca abajo) |
| Acelerómetro (movimiento) | 25% | Detecta agitación física y gestos |
| Micrófono (ruido) | 10% | Complemento ambiental; menos determinante |

---

## 6. LOS 5 GRÁFICOS

### Gráfico 1 — Micrófono en tiempo real

| Propiedad | Detalle |
|---|---|
| Tipo | Línea temporal (Flot Charts) |
| Color | Morado (`#a855f7`) |
| Ventana de datos | Últimos 10 segundos (10 puntos, uno por segundo) |
| Dato mostrado | Media de `dbfs_level` por segundo |
| Eje Y | Nivel en dBFS |
| Actualización | Cada 1 segundo |
| Badge | "LIVE" con punto parpadeante |

### Gráfico 2 — Giroscopio en tiempo real

| Propiedad | Detalle |
|---|---|
| Tipo | Línea temporal (Flot Charts) |
| Color | Ámbar (`#f59e0b`) |
| Ventana de datos | Últimos 10 segundos |
| Dato mostrado | Varianza de magnitud total (MAX−MIN suma de 3 ejes) por segundo |
| Eje Y | Magnitud de varianza (rad/s) |
| Actualización | Cada 1 segundo |
| Badge | "LIVE" con punto parpadeante |

### Gráfico 3 — Gravedad Z en tiempo real

| Propiedad | Detalle |
|---|---|
| Tipo | Línea temporal (Flot Charts) |
| Color | Azul cielo (`#38bdf8`) |
| Ventana de datos | Últimos 10 segundos |
| Dato mostrado | Media del eje Z de gravedad por segundo |
| Eje Y | m/s² (aprox. 9.8 en vertical, >7.5 boca abajo) |
| Actualización | Cada 1 segundo |
| Badge | "LIVE" con punto parpadeante |

### Gráfico 4 — Acelerómetro en tiempo real

| Propiedad | Detalle |
|---|---|
| Tipo | Línea temporal (Flot Charts) |
| Color | Rojo (`#ef4444`) |
| Ventana de datos | Últimos 10 segundos |
| Dato mostrado | Varianza de magnitud total del acelerómetro por segundo |
| Eje Y | Magnitud de varianza (m/s²) |
| Actualización | Cada 1 segundo |
| Badge | "LIVE" con punto parpadeante |

### Gráfico 5 — Histórico de concentración

| Propiedad | Detalle |
|---|---|
| Tipo | Barras apiladas verticales (Flot + plugin Stack) |
| Eje X | Tiempo en franjas horarias (`DD/MM HH:00`) |
| Eje Y | Minutos por hora (0–60) |
| Serie verde (`#10b981`) | Minutos "Concentrado" (índice ≥ 75) por hora |
| Serie roja (`#ef4444`) | Minutos "Distraído" (índice < 75) por hora |
| Tooltip | Rango horario + minutos concentrado/distraído + puntos de color |
| Actualización | Cada 60 segundos |
| Fuente de datos | `/api/analytics.php` con CTEs SQL por minuto agrupadas por hora |

**Barra de estadísticas encima del gráfico:**
- Total minutos concentrado (verde)
- Total minutos distraído (rojo)
- Eficiencia % (concentrado / total × 100)
- Período registrado (rango de fechas con datos)

---

## 7. LOS 4 INSIGHTS (TARJETAS KPI)

### KPI 1 — Estado Actual

| Propiedad | Detalle |
|---|---|
| Posición | Tarjeta highlight, ancho completo, primera fila |
| Valor mostrado | `"CONCENTRADO"` / `"DISTRAÍDO"` / `"ESPERANDO..."` |
| Color CONCENTRADO | Verde con efecto glow (`#10b981`) |
| Color DISTRAÍDO | Rojo con efecto glow (`#ef4444`) |
| Color ESPERANDO | Naranja (`#f59e0b`) |
| Actualización | Cada 1 segundo |
| Umbral | concentration_index ≥ 75 → CONCENTRADO |

### KPI 2 — Ruido

| Propiedad | Detalle |
|---|---|
| Valor mostrado | Nivel de audio más reciente en dBFS (ej. `"-38.5 dBFS"`) |
| Fuente | Campo `latest_mic` del JSON de `/api/api_realtime.php` |
| Actualización | Cada 1 segundo |

### KPI 3 — Rotación

| Propiedad | Detalle |
|---|---|
| Valor mostrado | Varianza de giroscopio actual (ej. `"0.0227"`, 4 decimales) |
| Fuente | Campo `variance` del JSON de `/api/api_realtime.php` |
| Actualización | Cada 1 segundo |

### KPI 4 — Brillo

| Propiedad | Detalle |
|---|---|
| Valor mostrado | Nivel de brillo en lux (ej. `"0"` pantalla off, `"2300"` pantalla on) |
| Fuente | Campo `latest_bright` del JSON de `/api/api_realtime.php` |
| Actualización | Cada 1 segundo |
| Uso especial | Brillo = 0 → pantalla OFF → rangos "tolerantes" en giroscopio y acelerómetro |

---

## 8. QUÉ DETERMINA CONCENTRADO VS. DISTRAÍDO

| Comportamiento del usuario | Efecto en el índice |
|---|---|
| Teléfono boca abajo sobre la mesa | **→ 100 (override absoluto)** |
| Teléfono quieto, sin ruido, pantalla apagada | Scores altos en todos los componentes → índice alto |
| Pantalla encendida, mínimo movimiento y rotación | Gyro/accel muy sensibles → requiere quietud extrema |
| Scroll en pantalla encendida + ruido de fondo | Detección "Reels" → **−40 puntos** |
| Muchos movimientos físicos (agitación) | Accel con alta varianza → scoreAccel bajo → distracción |
| Muchas rotaciones del dispositivo | Gyro con alta varianza → scoreGyro bajo → distracción |
| Ambiente muy ruidoso (dBFS cerca de −20) | scoreMic = 0 → contribuye negativamente (10%) |
| Silencio total (dBFS ≈ −45) | scoreMic = 100 → contribuye positivamente (10%) |

---

## 9. LIBRERÍAS Y FRAMEWORKS

### Backend

| Tecnología | Versión | Rol |
|---|---|---|
| **PHP** | 7.4+ | Lenguaje del servidor (toda la lógica API) |
| **PDO** | Built-in PHP | Abstracción de base de datos; prepared statements |
| **MySQL** | 5.7+ | Almacenamiento relacional de series temporales |
| **Apache** | 2.4+ | Servidor HTTP (vía Laragon) |
| **Laragon** | Full | Entorno de desarrollo local (Apache + MySQL + PHP en Windows) |

### Frontend

| Tecnología | Versión | Rol |
|---|---|---|
| **HTML5** | 5 | Estructura semántica de la PWA |
| **CSS3** | 3 | Variables CSS, diseño responsive, animaciones |
| **JavaScript** | ES6+ | Lógica de polling, actualización de UI |
| **jQuery** | 3.7.1 | AJAX, manipulación DOM |
| **Flot Charts** | 0.8.3 | Librería de gráficas (líneas temporales + barras apiladas) |
| **Flot Time Plugin** | 0.8.3 | Eje X con formato de fecha/hora |
| **Flot Stack Plugin** | 0.8.3 | Renderizado de barras apiladas (gráfico histórico) |
| **Flot Resize Plugin** | 0.8.3 | Reescalado responsive de gráficos |
| **Google Fonts** | Latest | Inter (texto general), Outfit (valores KPI) |

### Infraestructura y despliegue

| Tecnología | Rol |
|---|---|
| **Cloudflare Tunnel (cloudflared)** | Exposición HTTPS del servidor local para acceso móvil externo |
| **Service Worker (`sw.js`)** | Caché offline; estrategia Network-Only para API y Cache-First para assets |
| **PWA Manifest (`manifest.json`)** | Instalación como app nativa en iOS y Android |

---

## 10. OTRAS FUNCIONALIDADES

### 10.1 Exportación de datos CSV (`/database/export_dataset.php`)

- **Endpoint:** `GET /Sensores/database/export_dataset.php`
- **Función:** Agrega todos los datos por minuto y los exporta como fichero CSV descargable directamente desde el navegador
- **Columnas del CSV:**

| Columna | Descripción |
|---|---|
| `Fecha_Hora` | Marca temporal del minuto |
| `Ruido_dBFS` | Media del nivel de audio por minuto |
| `Brillo_Medio` | Media del brillo por minuto |
| `Gravedad_Z` | Media del eje Z de gravedad por minuto |
| `Varianza_Giroscopio` | Varianza de rotación por minuto |
| `Varianza_Acelerometro` | Varianza de movimiento por minuto |
| `Eventos_Reels` | Segundos con scroll+ruido detectados por minuto |
| `Indice_Concentracion` | Índice calculado (0–100) |
| `Estado_Concentracion` | "Concentrado" / "Distraído" |

- **Cabeceras HTTP:** `Content-Disposition: attachment` fuerza la descarga en el navegador
- **Uso:** Análisis externo en Excel, Python/pandas, MATLAB, etc.

### 10.2 Progressive Web App (PWA)

- **`manifest.json`:**
  - Nombre: "Dashboard de Concentración"
  - Nombre corto: "Concentración"
  - Display: `standalone` (pantalla completa sin barra del navegador)
  - Iconos: 4 variantes (192px, 512px, 512px maskable, 180px Apple Touch)
  - Color de tema: `#0f172a`
- **Instalación Android:** Banner automático o icono en barra de Chrome
- **Instalación iOS:** Menú Compartir → "Agregar a pantalla de inicio"

### 10.3 Service Worker (`sw.js`)

Gestiona 3 estrategias de caché independientes:

| Recurso | Estrategia | Motivo |
|---|---|---|
| API PHP, HTML, CSS, JS | Network-Only | Siempre datos frescos |
| CDN (jQuery, Flot, Google Fonts) | Stale-While-Revalidate | Rápido, actualiza en background |
| Iconos de la app | Cache-First | Sin cambios frecuentes |

- Versión de caché: `concentracion-v5`
- Fallback offline: HTTP 503 si no hay caché disponible

### 10.4 Diseño responsive y dark theme

**Paleta de colores (CSS variables):**

| Variable | Color | Uso |
|---|---|---|
| `--bg-dark` | `#0f172a` | Fondo principal |
| `--card` | `#1e293b` | Tarjetas y paneles |
| `--text-primary` | `#f8fafc` | Texto principal |
| `--text-secondary` | `#94a3b8` | Texto secundario |
| `--accent-blue` | `#3b82f6` | Acento general |
| `--accent-green` | `#10b981` | Estado CONCENTRADO |
| `--accent-red` | `#ef4444` | Estado DISTRAÍDO |
| `--accent-orange` | `#f59e0b` | Estado ESPERANDO |

**Breakpoints responsive:**

| Ancho de pantalla | Layout |
|---|---|
| > 768px | 2 columnas mini-charts, 3 columnas KPI |
| ≤ 768px | 1 columna (stack vertical para móvil) |
| ≤ 360px | Fuentes comprimidas |

**Efectos visuales:** Glassmorphism en tarjetas, gradiente azul-morado en cabecera, animación pulse/blink en badges "LIVE".

---

## 11. FLUJO COMPLETO DE DATOS (END-TO-END)

```
SENSOR LOGGER (smartphone)
  │
  │  HTTP POST (JSON con lotes de lecturas, timestamps en nanosegundos)
  ▼
ingest.php
  ├── Valida JSON y cabeceras CORS
  ├── Convierte timestamps: ns ÷ 1.000.000 → ms
  ├── Abre Transaction BEGIN en MySQL
  ├── INSERT en accelerometer, brightness, microphone, gravity, gyroscope
  └── COMMIT (éxito) / ROLLBACK (error)

─────────────────────────────────────────────────

CADA 1 SEGUNDO (dashboard → api_realtime.php)
  ├── SELECT últimas 50 lecturas de las 5 tablas
  ├── Calcula 4 scores normalizados (mic, grav, gyro, accel)
  ├── Pondera: 10% + 25% + 40% + 25%
  ├── Ajusta sensibilidad según brillo (pantalla ON/OFF)
  ├── Verifica "Reels" (scroll+ruido en últimos 45s) → −40 si activo
  ├── Verifica boca abajo (gravity_z > 7.5) → 100 si activo
  ├── Clamp [0, 100]
  └── Devuelve JSON:
       ├── concentration_index (0–100)
       ├── estado ("CONCENTRADO" / "DISTRAÍDO")
       ├── latest_mic, variance, latest_bright  (→ KPIs 2, 3, 4)
       └── accel_history, mic_history, gravity_history, gyro_history
            (→ 4 mini-charts, ventana 10 segundos)

─────────────────────────────────────────────────

CADA 60 SEGUNDOS (dashboard → analytics.php)
  ├── CTE SQL: agrupa datos por minuto de las 5 tablas
  ├── Aplica el mismo algoritmo de fusión por minuto
  ├── Clasifica cada minuto: Concentrado (≥75) / Distraído (<75)
  ├── Agrupa minutos por hora
  └── Devuelve JSON:
       ├── Serie verde: [timestamp_hora, minutos_concentrado]
       └── Serie roja: [timestamp_hora, minutos_distraido]
            (→ Gráfico 5: barras apiladas + estadísticas)
```

---

## 12. ESTRUCTURA DE FICHEROS

```
C:\Users\ruben\Desktop\Proyectos\Sensores\
│
├── api/
│   ├── ingest.php .............. 112 líneas — Ingesta de Sensor Logger
│   ├── api_realtime.php ........ 156 líneas — Fusión en tiempo real + KPIs
│   ├── analytics.php ........... 232 líneas — Análisis histórico (CTEs SQL)
│   └── debug_payload.log ....... (generado en errores de validación)
│
├── dashboard/
│   ├── index.html .............. 130 líneas — Estructura PWA
│   ├── app.js .................. 225 líneas — Polling, gráficos, KPIs
│   ├── style.css ............... 561 líneas — Dark theme + responsive
│   ├── sw.js ................... 103 líneas — Service Worker (caché offline)
│   ├── manifest.json ........... 35 líneas  — Metadatos PWA
│   └── icons/
│       ├── apple-touch-icon.png .. 180×180 px — iOS
│       ├── icon-192.png .......... 192×192 px — Android
│       ├── icon-512.png .......... 512×512 px — Alta resolución
│       └── icon-maskable-512.png . 512×512 px — Icono adaptativo Android
│
├── database/
│   ├── database.sql ............ 53 líneas  — Esquema (5 tablas + índices)
│   └── export_dataset.php ...... 183 líneas — Exportación CSV
│
├── MANUAL_PWA.md ............... 195 líneas — Guía de despliegue
├── practica2026.md ............. 85 líneas  — Especificación de la práctica
├── INFORME_TECNICO.md .......... (este fichero)
└── image.png ................... Captura del dashboard en uso

Total: ~2.200 líneas de código + assets
```

---

## 13. MÉTRICAS TÉCNICAS DEL PROYECTO

| Métrica | Valor |
|---|---|
| Sensores registrados | 5 |
| Tablas en base de datos | 5 |
| Endpoints API | 3 (`ingest`, `api_realtime`, `analytics`) |
| Gráficos en dashboard | 5 (4 tiempo real + 1 histórico) |
| Tarjetas KPI | 4 (estado, ruido, rotación, brillo) |
| Tasa de actualización tiempo real | 1 Hz (cada 1 segundo) |
| Tasa de actualización histórico | 1/60 Hz (cada 60 segundos) |
| Ventana de datos tiempo real | 10 segundos |
| Granularidad histórico | 1 minuto (agrupado por hora en el gráfico) |
| Umbral de concentración | ≥ 75 / 100 |
| Peso máximo en el índice | Giroscopio (40%) |
| Penalización "Reels" | −40 puntos |
| Override boca abajo | 100 (incondicional) |
| Versión Service Worker | `concentracion-v5` |
| Almacenamiento estimado | ~4,3 MB/hora de sesión |
| Líneas de código totales | ~2.200 (sin comentarios ni assets) |

---

## 14. SEGURIDAD Y CONSIDERACIONES DE PRODUCCIÓN

### Estado actual (prototipo de desarrollo)

- **CORS:** Wildcard `Access-Control-Allow-Origin: *` — permisivo, válido para red local
- **SQL Injection:** Mitigado con prepared statements PDO
- **Autenticación:** Ninguna (prototipo en red local)
- **HTTPS:** Solo a través del túnel de Cloudflare; el tráfico local es HTTP

### Mejoras para entorno de producción

- Restringir CORS a orígenes específicos
- Añadir API key o autenticación JWT en los endpoints
- Implementar rate limiting en el endpoint de ingesta
- Validar rangos plausibles de valores de sensores
- Usar HTTPS en todos los endpoints sin depender del túnel

---

*Informe generado el 3 de junio de 2026.*
