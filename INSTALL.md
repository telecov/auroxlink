# 📦 Instalación de AUROXLINK

AUROXLINK incluye un instalador automático que prepara el servidor web, PHP, SvxLink base, Tailscale, permisos, servicios y archivos necesarios para dejar el sistema operativo.

La actualización de **SvxLink a la última release oficial de SM0SVX es opcional** y se realiza con un instalador separado.

---

## 🖥️ Requisitos

### Sistema recomendado

- Raspberry Pi OS o Debian basado en APT
- Raspberry Pi, mini PC o servidor Linux
- Acceso a Internet
- Acceso SSH o consola
- Usuario con permisos `sudo`

AUROXLINK instala automáticamente las dependencias necesarias, entre ellas Apache, PHP, PHP cURL, PHP Zip, Git, NetworkManager, ALSA, Tailscale y `svxlink-server`.

---

# 🚀 Instalación rápida

En una instalación limpia ejecuta:

```bash
curl -fsSL https://raw.githubusercontent.com/telecov/auroxlink/main/install_auroxlink.sh | sudo bash
```

El instalador realiza automáticamente:

- Instalación de dependencias
- Instalación de Apache y PHP
- Instalación de `svxlink-server` desde la distribución
- Instalación de Tailscale
- Descarga de AUROXLINK desde GitHub
- Inicialización de archivos por defecto
- Configuración de permisos
- Configuración de sudoers para el panel
- Creación del monitor de SvxLink
- Configuración del cron de AUROXLINK
- Activación de servicios
- Verificación final de la instalación

> El instalador principal **no actualiza SvxLink desde GitHub**. Se mantiene la versión entregada por la distribución como base estable.

---

## ✅ Verificar instalación

Comprueba los servicios principales:

```bash
sudo systemctl status apache2 --no-pager
sudo systemctl status svxlink --no-pager
sudo systemctl status auroralink-monitor --no-pager
```

También puedes comprobar la versión instalada de AUROXLINK:

```bash
cat /var/www/html/version.txt
```

---

## 🌐 Acceder al panel

Obtén la dirección IP del servidor:

```bash
hostname -I
```

Luego abre en un navegador:

```text
http://IP-DEL-SERVIDOR/
```

Credencial inicial de AUROXLINK:

```text
Contraseña: admin123
```

Por seguridad, cambia la contraseña desde AUROXLINK después del primer acceso.

---

# 📡 Actualizar SvxLink — opcional

AUROXLINK incluye un segundo instalador para usuarios que quieran utilizar la última release estable oficial de SvxLink publicada por SM0SVX.

Primero consulta si existe una versión más reciente:

```bash
sudo bash /var/www/html/install_svxlink_latest.sh --check
```

Si deseas instalarla:

```bash
sudo bash /var/www/html/install_svxlink_latest.sh
```

Para forzar una reinstalación de la release detectada:

```bash
sudo bash /var/www/html/install_svxlink_latest.sh --force
```

El actualizador de SvxLink:

- Consulta automáticamente la última release estable oficial
- Conserva el paquete SvxLink de Debian/Raspberry Pi OS como respaldo
- Compila la nueva release en un directorio separado
- Mantiene juntos binarios, librerías, plugins y eventos TCL de cada versión
- Respalda `/etc/svxlink`
- Prueba la nueva versión antes de activarla
- Comprueba que no se mezclen plugins entre versiones
- Solo cambia el servicio si la prueba finaliza correctamente
- Ejecuta rollback automático si algo falla
- Mantiene `/etc/svxlink/svxlink.conf` como configuración activa para AUROXLINK

Las releases administradas por este instalador se almacenan en:

```text
/opt/auroxlink/svxlink/releases/
```

La release activa queda enlazada en:

```text
/opt/auroxlink/svxlink/current
```

---

## 🎙️ Configuración de audio

Lista los dispositivos ALSA disponibles:

```bash
aplay -l
arecord -l
```

Para ajustar niveles:

```bash
alsamixer
```

Después configura RX/TX desde el panel AUROXLINK.

---

## 📻 Configuración EchoLink

Configura desde AUROXLINK:

- Indicativo
- Contraseña EchoLink
- Nombre y descripción del nodo
- RX y TX
- Dispositivo de audio
- Parámetros de SvxLink

Después verifica:

```bash
sudo systemctl restart svxlink
sudo systemctl status svxlink --no-pager
```

Logs:

```bash
sudo tail -f /var/log/svxlink
```

---

## 🔐 Tailscale

El instalador instala Tailscale, pero el nodo debe asociarse a tu cuenta/Tailnet cuando corresponda.

Puedes hacerlo desde AUROXLINK o desde consola:

```bash
sudo tailscale up
```

Estado:

```bash
tailscale status
```

---

## 🤖 Telegram — opcional

Desde AUROXLINK puedes configurar:

- Token del bot
- Chat ID

Crea el bot utilizando **@BotFather** y agrega el bot al grupo o canal que utilizará el nodo.

Las credenciales reales de Telegram se almacenan localmente y no deben subirse al repositorio.

---

## 🛠️ Comandos útiles

```bash
sudo systemctl status svxlink --no-pager
sudo systemctl restart svxlink
sudo systemctl stop svxlink
sudo systemctl start svxlink

sudo systemctl status apache2 --no-pager
sudo systemctl restart apache2

sudo tail -f /var/log/svxlink

aplay -l
arecord -l
alsamixer
lsusb
ip addr
```

---

## 🔄 Reinstalar o actualizar AUROXLINK

El mismo instalador puede ejecutarse nuevamente:

```bash
curl -fsSL https://raw.githubusercontent.com/telecov/auroxlink/main/install_auroxlink.sh | sudo bash
```

Si detecta una instalación existente, crea un respaldo antes de reemplazar el código y conserva los datos configurados contemplados por el instalador.

---

## 🧯 Soporte y reportes

Repositorio oficial:

```text
https://github.com/telecov/auroxlink
```

Para reportar un problema o proponer una mejora utiliza la sección **Issues** del repositorio e incluye:

- Descripción del problema
- Sistema operativo
- Modelo de Raspberry Pi o servidor
- Versión de AUROXLINK
- Versión de SvxLink
- Logs relevantes

---

**AUROXLINK v1.7**
