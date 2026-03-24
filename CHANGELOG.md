# 📡 CHANGELOG – AUROXLINK

Todos los cambios relevantes del proyecto **AUROXLINK** serán documentados en este archivo.  
El versionado sigue un esquema evolutivo basado en hitos funcionales del sistema.

---

##🔧 v1.7 – System Upgrade 
📅 2026-03-24

> *“uniendo al mundo atravez de su propio lenguaje.”*

### ✨ Novedades


* Mejora de dahboard
* Mejoras en status tx-rx
* Se reparara configuracion proxy via web
* Se mejora upgrade.sh

Se agrega
* Libro de guardia 
* Idiomas INGLES - PORTUGUES - ESPAÑOL

## Mencion especial a Fábio Guilherme PY2FGD  quien apoyo la traduccion a idioma PORTUGUES. muchas gracias amigo

* Se cambia boton paypal por miebros youtube
* Se agrega opcion de backup
* Mejoras de seguridad

---

## 🔧 v1.6.3 – System Upgrade  
📅 2025-11-09

> *“Más control de audio, más claridad, más madurez visua.”*

### ✨ Novedades

- Se habilita en la interfaz web (**Configuración / settings.php**) la opción **PREAMP**:
  - Disponible directamente desde `svxlink.conf`
  - Permite ajustar el nivel de audio de entrada
  - Evita saturación y distorsión del canal de audio
- Incorporación de **instrucciones específicas en la página de ayuda** para el uso del PREAMP
- Mejora visual en la página **About**
- Mejora visual y ordenamiento del **Sidebar**
- Ajustes visuales generales orientados a mayor claridad y consistencia del dashboard

---

## ⚙️ v1.6.2 – System Go  
📅 2025-05-21

> *“AUROXLINK mejora cada día.”*

### ✨ Novedades

- Botón de acceso a la **página de ayuda**, orientado a facilitar la configuración
- Función para **guardar configuración de audio (alsamixer)**:
  - Evita reconfigurar niveles tras reinicios del sistema
- Gestión de estaciones conflictivas:
  - Expulsar estaciones desde el nodo
  - Bloquear estaciones que generen interferencias
- Integración de **VPN mediante Tailscale**:
  - Ideal para NAT estricto o redes móviles
  - No requiere apertura de puertos
- Nueva tarjeta informativa en el dashboard:
  - Estado de **VPN activa**
- Botón de **búsqueda rápida de IDs EchoLink**
- Mejoras generales de usabilidad del panel web

### 🛠 Correcciones

- Corrección de error que impedía el envío de mensajes por **Telegram**
- Ajustes menores de estabilidad y rendimiento

---

## ⚙️ v1.6.1 – Versión Estable  
📅 2025-05-17

> *“AUROXLINK comienza a consolidar su ecosistema.”*

### ✨ Novedades

- Inclusión de **tarjeta APRS en el dashboard** con acceso web
- Implementación de **configuración masiva de APRS**
- Nueva opción para **activar o desactivar funcionalidades** directamente desde la web
- Edición de archivos críticos sin uso de consola:
  - `svxlink.conf`
  - `ModuleEchoLink.conf`
- Mejora general en la experiencia de configuración para usuarios no técnicos

### 🛠 Correcciones

- Ajustes internos de estabilidad
- Normalización de rutas y validaciones de configuración

---

## 🚀 v1.5 – Carrier On  
📅 2025-05-04

> *“AUROXLINK está al aire. Y llegó para quedarse.”*

### ✨ Novedades

- Integración completa de **SVXLink 24.02**, compilado desde cero
- Nuevo **dashboard visual** con tarjetas de estado del sistema:
  - Uso de CPU
  - Temperatura
  - RAM
  - Uptime
  - Estado general del nodo
- Identificación precisa del **tipo de conexión** (estación / nodo)
- Sistema de **protección de integridad**:
  - Bloqueo automático si se detectan manipulaciones críticas
- Optimización visual para:
  - Escritorio
  - Dispositivos móviles
- Consolidación del diseño general:
  - Íconos temáticos
  - Indicadores visuales claros
- Reorganización y mejora de la **estructura de archivos y rutas internas**

### 🛠 Correcciones

- Corrección de formato de fecha y hora en conexiones activas
- Mejora en la separación visual y legibilidad del dashboard
- Ajustes de seguridad en sesiones y rutas protegidas
- Corrección en la lectura de gráficos diarios e históricos
- Corrección de bugs de seguridad detectados durante pruebas

---

## 📌 Notas generales

- AUROXLINK es un proyecto en evolución constante.
- Algunas versiones incluyen mejoras internas no visibles directamente para el usuario.
- Se recomienda mantener el sistema actualizado para asegurar compatibilidad, seguridad y estabilidad.
