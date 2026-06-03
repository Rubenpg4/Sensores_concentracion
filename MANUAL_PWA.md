# Manual: Levantar el Dashboard PWA con HTTPS

## Lo que necesitas tener corriendo
- **Laragon** encendido (Apache + MySQL)
- El proyecto en `C:\laragon\www\Sensores\` (ya está copiado)

---

## Paso 1 — Abrir Laragon y arrancar servicios
Abre Laragon y pulsa **Start All**. Comprueba que Apache y MySQL están en verde.

---

## Paso 2 — Lanzar el túnel HTTPS
Abre una terminal PowerShell normal (no hace falta admin) y ejecuta:

```powershell
& "C:\Program Files (x86)\cloudflared\cloudflared.exe" tunnel --url http://localhost:80
```

Espera ~5 segundos hasta que aparezca una línea como:
```
https://palabras-aleatorias.trycloudflare.com
```
**Copia esa URL** — cambia cada vez que relanzas el túnel.

---

## Paso 3 — Abrir en el móvil
En el móvil, abre el navegador y ve a:
```
https://palabras-aleatorias.trycloudflare.com/Sensores/dashboard/index.html
```

- **Android (Chrome):** aparece un banner "Añadir a pantalla de inicio" o un icono de instalar en la barra de direcciones.
- **iOS (Safari):** botón compartir → "Añadir a pantalla de inicio".

---

## Paso 4 — Mantener el túnel abierto
**No cierres la terminal** mientras necesites acceso. Al cerrarla, el túnel muere y la URL deja de funcionar.

---

## Paso 5 — Cerrar el túnel
Cuando hayas terminado, ve a la terminal donde está corriendo cloudflared y pulsa:
```
Ctrl + C
```
El proceso se cierra y la URL deja de funcionar. Laragon puedes pararlo desde su interfaz con **Stop All**.

---

## Configurar Sensor Logger (endpoint)

La app **Sensor Logger** (Android/iOS) envía los datos al servidor vía HTTP Push. El endpoint al que apuntar es siempre:

```
<BASE_URL>/Sensores/api/ingest.php
```

La `<BASE_URL>` depende de cómo hayas levantado el servidor:

### Caso A — Túnel Cloudflare (recomendado para el móvil fuera de la red local)

Usa la URL que apareció al ejecutar el túnel en el Paso 2:

```
https://palabras-aleatorias.trycloudflare.com/Sensores/api/ingest.php
```

> **Recuerda:** esta URL cambia cada vez que relanzas el túnel. Actualiza el endpoint en Sensor Logger cuando la reinicies.

### Caso B — IP local WiFi (móvil y PC en la misma red)

1. Averigua la IP de tu PC: abre PowerShell y ejecuta `ipconfig`. Busca la línea `Dirección IPv4` bajo el adaptador WiFi (ej: `192.168.1.42`).
2. El endpoint será:

```
http://192.168.1.42/Sensores/api/ingest.php
```

> **Nota:** HTTP sin HTTPS solo funciona si el móvil está en la misma WiFi. No sirve para instalar la PWA (requiere HTTPS), pero sí para enviar datos desde Sensor Logger.

### Cómo configurarlo en Sensor Logger

1. Abre Sensor Logger → **Settings** (icono engranaje).
2. Ve a **Push URL** (o "HTTP Push" según la versión).
3. Pega la URL completa del endpoint (`…/Sensores/api/ingest.php`).
4. Activa los sensores que quieras registrar: **Accelerometer**, **Gyroscope**, **Gravity**, **Microphone**, **Brightness**.
5. Pulsa el botón de grabar — los datos llegarán a la base de datos en tiempo real.

---

## Si cambias archivos del proyecto
Como el proyecto está **copiado** (no enlazado), después de modificar algo en `Desktop\Proyectos\Sensores` hay que volver a copiar:

```powershell
Copy-Item -Recurse -Force "C:\Users\ruben\Desktop\Proyectos\Sensores" "C:\laragon\www\Sensores"
```

O bien, una sola vez con terminal **como administrador**, crear un enlace simbólico que sincronice automáticamente:

```powershell
# Primero borrar la copia actual
Remove-Item -Recurse -Force "C:\laragon\www\Sensores"
# Luego crear el enlace
New-Item -ItemType SymbolicLink -Path "C:\laragon\www\Sensores" -Target "C:\Users\ruben\Desktop\Proyectos\Sensores"
```

Con el enlace, no hay que volver a copiar nunca.

---

---

## Instalación en un PC nuevo (desde cero)

### Requisitos previos — qué instalar

| Herramienta | Descarga | Notas |
|---|---|---|
| **Laragon** (Full) | laragon.dev | Incluye Apache, MySQL y PHP. Instalar con opciones por defecto. |
| **cloudflared** | github.com/cloudflare/cloudflared/releases | Descargar `cloudflared-windows-amd64.exe`, renombrarlo a `cloudflared.exe` y copiarlo en `C:\Program Files (x86)\cloudflared\` (crear la carpeta si no existe). |
| **Sensor Logger** | Play Store / App Store | Buscar "Sensor Logger" (icono naranja, de Kelvin Lau). |

---

### Paso A — Copiar el proyecto a Laragon

Abre PowerShell **como administrador** y ejecuta una sola vez para crear un enlace simbólico (lo más cómodo):

```powershell
# Si ya existe una carpeta Sensores en www, borrarla primero
Remove-Item -Recurse -Force "C:\laragon\www\Sensores" -ErrorAction SilentlyContinue

# Crear el enlace simbólico (los cambios en Desktop se reflejan en www automáticamente)
New-Item -ItemType SymbolicLink -Path "C:\laragon\www\Sensores" -Target "C:\Users\<TU_USUARIO>\Desktop\Proyectos\Sensores"
```

> Sustituye `<TU_USUARIO>` por el nombre de usuario real del PC (ej: `ruben`).

Si prefieres solo copiar sin enlace simbólico:

```powershell
Copy-Item -Recurse -Force "C:\Users\<TU_USUARIO>\Desktop\Proyectos\Sensores" "C:\laragon\www\Sensores"
```

---

### Paso B — Crear la base de datos

1. Abre Laragon → **Start All** (espera que Apache y MySQL estén en verde).
2. En Laragon → **Database** (o abre HeidiSQL / phpMyAdmin desde el menú).
3. Conéctate con usuario `root` y contraseña vacía.
4. Abre una nueva pestaña de consulta y ejecuta el contenido de `database\database.sql` (puedes abrirlo directamente desde HeidiSQL con **Archivo → Cargar**).

Esto crea la base de datos `wsn_sensors` con las 5 tablas: `accelerometer`, `gyroscope`, `gravity`, `microphone`, `brightness`.

---

### Paso C — Verificar que el API responde

Con Laragon corriendo, abre en el navegador:

```
http://localhost/Sensores/api/ingest.php
```

Debe responder algo como `{"error":"Formato incorrecto..."}` — eso confirma que Apache y PHP están sirviendo el proyecto correctamente.

---

### Paso D — Configurar cloudflared

No requiere instalación ni cuenta. Solo verificar que el ejecutable está en la ruta correcta:

```powershell
& "C:\Program Files (x86)\cloudflared\cloudflared.exe" --version
```

Debe mostrar la versión. Si da error, comprueba que el archivo está en esa ruta y se llama exactamente `cloudflared.exe`.

---

A partir de aquí, seguir el flujo normal desde el **Paso 1** de este manual.

---

## Resumen rápido (día de la demo)
1. Laragon → **Start All**
2. Terminal → `& "C:\Program Files (x86)\cloudflared\cloudflared.exe" tunnel --url http://localhost:80`
3. Copiar la URL `https://….trycloudflare.com/Sensores/dashboard/index.html` al móvil
4. Instalar como PWA
