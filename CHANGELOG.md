# CHANGELOG – AUROXLINK
## 1.8.5 - 2026-09-15

### 🌎 Sismógrafo y alertas sísmicas

- Se incorpora el nuevo módulo **Sismógrafo AUROXLINK**.
- Consulta automática de eventos sísmicos mediante proveedores **CSN Chile y USGS**.
- Nuevo modo de proveedor `AUTO`, con selección automática de la fuente disponible.
- Configuración de **latitud y longitud del nodo** desde la interfaz web.
- Configuración de magnitud mínima y radio de visualización de eventos.
- Clasificación de alertas RF según distancia y magnitud:
  - Local.
  - Regional.
  - Amplia.
  - Nacional.
- Transmisión automática de alertas sísmicas por RF mediante `COMMAND_PTY`.
- Configuración independiente para activar o desactivar las alertas RF.
- Prueba manual de anuncio sísmico por RF desde la interfaz.
- Registro del último evento detectado y del último evento transmitido por RF.
- Prevención de anuncios duplicados mediante registro de eventos procesados.
- Monitor sísmico automático ejecutado mediante `systemd timer` cada 60 segundos.

### 🔊 Síntesis de voz Piper

- Se incorpora **Piper TTS** para generación local de anuncios de voz.
- Voz española `es_ES-davefx-medium`.
- Generación de audio local sin depender de servicios externos de síntesis de voz.
- Compatibilidad con audio RF de SvxLink.
- Instalación y configuración automática del modelo de voz.
- Fallback mediante `espeak-ng` cuando sea necesario.

### 📡 Integración con SvxLink

- Configuración automática de `COMMAND_PTY` en la lógica activa de SvxLink.
- Detección automática de la lógica configurada mediante `LOGICS=`.
- Compatibilidad con instalaciones existentes que utilizan `[SimplexLogic]`.
- La configuración existente de `svxlink.conf` se conserva durante las actualizaciones.
- Se incorporan permisos controlados para que AUROXLINK Web pueda administrar las configuraciones necesarias.
- Instalación automática de sonidos oficiales **SvxLink/EchoLink en_US Heather** cuando existe una versión compatible.
- Se agrega `www-data` al grupo `audio` para permitir la detección de dispositivos ALSA desde Settings.

### ⚙️ Instalador AUROXLINK

- Nuevo instalador oficial de AUROXLINK con proceso dividido en etapas y verificación final.
- Instalación automática de Apache, PHP, dependencias, SvxLink base, Piper y componentes requeridos.
- Instalación limpia del código AUROXLINK desde GitHub.
- Se mantiene soporte opcional para instalación mediante ZIP local indicado explícitamente.
- Se elimina la detección automática de archivos ZIP en dispositivos USB, `/media`, `/mnt` y `/run/media`.
- Corrección de la detección de la raíz del paquete AUROXLINK.
- La raíz ahora se valida mediante `index.php`, `settings.php` e `includes/`, evitando seleccionar subdirectorios incorrectos.
- Configuración automática de permisos web, servicios `systemd`, `sudoers` y directorios de runtime.
- Verificación automática de componentes al finalizar la instalación.

### 🔄 Actualizador AUROXLINK

- Nuevo proceso de actualización con respaldo automático previo.
- Actualización basada en la última release oficial publicada en GitHub.
- Preservación de configuraciones y datos del usuario durante la actualización.
- Migración automática de configuraciones sísmicas existentes.
- Actualización idempotente: puede ejecutarse nuevamente para verificar o reparar una instalación.
- El actualizador de AUROXLINK no modifica automáticamente la versión instalada de SvxLink.
- Workers de actualización trasladados a `/usr/local/libexec/auroxlink` y protegidos mediante permisos de `root`.
- Eliminación del antiguo mecanismo de actualización ejecutado desde `/tmp`.
- Validación de sintaxis PHP y Bash antes de reemplazar el código instalado.
- Corrección de la detección de la raíz del paquete descargado para evitar seleccionar un `index.php` perteneciente a un subdirectorio.

### 🔐 Seguridad y permisos

- Revisión general de permisos de archivos y directorios.
- Eliminación de permisos históricos `777/666` en componentes administrados por AUROXLINK.
- Uso de permisos `775/664` y directorios `setgid` donde se requiere escritura compartida.
- Actualizadores privilegiados protegidos fuera del directorio web.
- Revisión y validación automática de reglas `sudoers` mediante `visudo`.
- Separación entre procesos ejecutados por `www-data`, `svxlink` y `root`.

### 🛠️ Mejoras y correcciones

- Mejoras en la compatibilidad entre Raspberry Pi OS y Debian.
- Mejor manejo de instalaciones nuevas y migraciones desde versiones anteriores.
- Mejoras en la detección y configuración de dispositivos de audio.
- Nuevas verificaciones automáticas del estado de Apache, SvxLink, Piper y servicios sísmicos.
- Mejoras en los mensajes de diagnóstico del instalador y actualizador.
- Correcciones generales de estabilidad y preparación de AUROXLINK para futuras ampliaciones.


## 1.8.4 - 2026-09-08

### Identificación CW
- Se incorpora configuración de identificación CW nativa de SvxLink.
- Activación independiente de identificación CW corta y larga.
- Configuración de intervalos de identificación.
- Configuración de velocidad CW en WPM.
- Configuración de frecuencia de tono CW.
- Configuración de nivel de audio CW.
- Compatibilidad con SvxLink 1.7.0 / 19.09 o superior.
- Los parámetros CW ausentes se crean automáticamente en `[SimplexLogic]`.
- Validación y rollback automático si SvxLink no inicia después de guardar cambios.
- Indicador de estado CW en el dashboard: 🟢 CW ON / 🔴 CW OFF.

### Interfaz
- Reorganización visual de `settings.php`.
- Reorganización visual de `custom.php`.
- Se mantiene la lógica y estilos existentes de AUROXLINK.

### Instalación y actualización
- Nuevo directorio interno `includes/backups` para respaldos de configuración.
- El instalador conserva los respaldos durante reinstalaciones/actualizaciones.
- El actualizador crea el directorio automáticamente si no existe.
- Permisos restringidos `www-data:www-data` con modo `750`.

### Identidad
- Actualización del indicativo del desarrollador de CA2RDP a CE2RDP.
- Actualización de la firma de integridad correspondiente.

## v1.8.3 – Servicio de monitor AUROXLINK (2026-08-17)

### Correcciones

- El actualizador crea automáticamente `auroralink-monitor.service` si no existe.
- El servicio se actualiza, habilita y reinicia durante cada actualización.
- Se garantiza la ejecución permanente de `monitor_log_svx.php`.
- Se restablece el monitoreo de conexiones SvxLink y las alertas Telegram.
- Se mantiene el estado diario de Telegram mediante `/etc/cron.d/auroxlink`.

## v1.8.2 – Corrección del actualizador (2026-08-17)

### Correcciones

- Se corrige el fallo del actualizador en el Paso 7 al configurar cron.
- Se reemplaza el uso de `crontab` del usuario root por `/etc/cron.d/auroxlink`.
- El trabajo diario de AUROXLINK se ejecuta correctamente como `www-data`.
- Se agrega `cron` explícitamente a las dependencias del actualizador.
- La actualización ya no debe finalizar con código 1 durante la configuración de cron.

## v1.8.1 – Corrección de versión dinámica (2026-08-17)

### Correcciones

- Se elimina la versión fija `1.7` del sidebar.
- La versión mostrada por AUROXLINK ahora se obtiene automáticamente desde `version.txt`.
- El sidebar queda preparado para futuras versiones sin necesidad de modificar código.
- Se corrige el aviso falso de actualización cuando la versión instalada es superior a la última Release publicada.

## v1.8 – Actualización segura de SvxLink (2026-08-17)

### Novedades

- Nuevo módulo web para actualizar SvxLink desde Configuración.
- El módulo de actualización queda ubicado debajo del Control de Audio.
- Detección automática de la última release estable oficial de SvxLink.
- Descarga mediante Git público, sin tokens ni credenciales privadas.
- Compilación aislada en releases versionadas bajo `/opt/auroxlink/svxlink/releases/`.
- Activación mediante `/opt/auroxlink/svxlink/current`.
- Prueba previa usando la configuración, audio, RX y TX reales del nodo.
- Validación de plugins y eventos pertenecientes a la misma release.
- Rollback automático si la nueva versión no supera las validaciones.
- Barra de progreso, estado y log técnico persistente desde la interfaz web.
- La actualización se bloquea si el nodo SvxLink base no está operativo.
- Detección robusta de la versión SvxLink realmente activa.
- Actualizadores privilegiados instalados fuera del webroot en `/usr/local/libexec/auroxlink`.
- Estado de actualización persistente en `/var/lib/auroxlink/svxlink-update`.
- Actualizador de AUROXLINK con detección automática de tags y protección contra downgrade.
- Preservación de configuración y banner personalizado durante actualizaciones.

### Seguridad y correcciones

- Se elimina la ejecución privilegiada de actualizadores desde `/tmp`.
- Se limita sudoers a ejecutables protegidos propiedad de root.
- Se corrige el tratamiento del código `124` de `timeout` durante la prueba previa de SvxLink.
- Se evita mostrar 100% cuando una actualización termina con error.
- Se elimina el porcentaje duplicado en la barra de progreso.
- Se evita dependencia innecesaria de `jq` en el worker.
- Logs de prueba trasladados fuera de `PrivateTmp` de Apache.
- Se mantiene el paquete SvxLink de la distribución como base/fallback.

### Validación

AUROXLINK v1.8 fue validado actualizando un nodo operativo desde el paquete Debian
SvxLink `24.02-5` a la release oficial SvxLink `26.05.1`, comprobando configuración,
audio, módulos, plugins, eventos TCL, activación mediante systemd y rollback.

## v1.6.2 – System Go (2025-05-21)

> “AUROXLINK está mejorando cada dia.”


### Novedades

- Se integra boton pagina de ayuda para enteder la configuracion
- Creacion de boton para guardar audio alsamixer, evita que en cada reinicio ajustes el audio
- agrega posibilidad de sacar estaciones del nodo o bloquear estaciones que generen conflictos
- integracion de configuracion VPN con herramienta TAILSCALE, si tienes NAT ESTRICTO (uso de red movil) sin apertura de puertos, puedes tener acceso remoto al dashboard con VPN
- se agrega tarjeta de informacion en dashboard de VPN ACTIVA
- boton de busqueda rapida de ID NODOS ECHOLINK
-

### Correcciones

- Se corrige error que impedia envio mensajes telegram
- ajustes menores

## v1.6.3 - System Upgade

### Novedades

- Se habilita en la web CONFIGURACION (setting.php) la web opcion de PREAMP, (disponible en SVXLINK.CONF) opcion que permite ajustar el audio de entrada, evitando saturar y distorcionar el canal de audio
- Se deja instrucciones en la pagina ayuda.
- Se realiza mejora visual en pagina about
- Se realiza mejora visual en sidebar

