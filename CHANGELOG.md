# CHANGELOG – AUROXLINK

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

