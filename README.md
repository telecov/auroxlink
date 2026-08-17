# 🌌 AUROXLINK — Panel web para SvxLink y EchoLink

🇪🇸 Español | 🇺🇸 [English](README_EN.md)

<p align="center">
  <img src="img/dashboard.png" alt="Dashboard AUROXLINK" width="1000">
</p>

<p align="center">
  <b>Monitoreo, configuración y control visual para nodos SvxLink y EchoLink.</b><br>
  Diseñado para radioaficionados y operadores que quieren administrar su nodo desde una interfaz web clara y moderna. 📡
</p>

---

## 🚀 ¿Qué es AUROXLINK?

**AUROXLINK** es una interfaz web para administrar un nodo basado en **SvxLink** y **EchoLink** desde el navegador, reduciendo la necesidad de trabajar directamente en consola Linux para tareas habituales.

Permite visualizar el estado del nodo, revisar actividad, controlar servicios, editar parámetros principales, ajustar audio, administrar red y personalizar la interfaz.

> **SvxLink es un proyecto independiente creado y mantenido por SM0SVX / Tobias Blomberg.** AUROXLINK no reemplaza ni modifica su autoría; proporciona una capa web de administración y automatización alrededor del software oficial.

- SvxLink oficial: https://github.com/sm0svx/svxlink
- SM0SVX: https://github.com/sm0svx

---

## ✨ Características principales

- 📡 **Dashboard en tiempo real** con estado del nodo y conexiones.
- 📊 **Actividad y estadísticas** de transmisiones y tráfico EchoLink.
- 🎚️ **Control de audio ALSA desde la web**.
- 🔄 **Actualización segura de SvxLink desde la interfaz web**, con compilación aislada, prueba previa y rollback automático.
- ⚙️ **Edición de `svxlink.conf` y `ModuleEchoLink.conf`**.
- ▶️ **Control del servicio SvxLink**: iniciar, detener y reiniciar.
- 🌐 **Configuración de red y WiFi** desde la interfaz.
- 🔐 **Acceso protegido** para áreas sensibles.
- 🔔 **Alertas y estado por Telegram** de forma opcional.
- 🖼️ **Personalización visual** de banner, colores, nombre y datos del nodo.
- 🔒 **Tailscale** para acceso remoto seguro.
- 🧾 **Monitoreo de logs** y estado del sistema.
- 💾 **Backup y restauración** de configuración de AUROXLINK.
- 📱 **Diseño responsive** para escritorio, tablet y móvil.

---

## 📦 Instalación rápida

En una instalación limpia de Raspberry Pi OS o Debian compatible:

```bash
curl -fsSL https://raw.githubusercontent.com/telecov/auroxlink/main/install_auroxlink.sh | sudo bash
```

El instalador prepara automáticamente Apache, PHP, dependencias, `svxlink-server`, Tailscale, permisos, servicios y archivos requeridos por AUROXLINK.

👉 [Guía completa de instalación](INSTALL.md)

---

## 📡 Actualización opcional de SvxLink

AUROXLINK incluye un actualizador independiente para instalar la última release estable oficial de SvxLink publicada por SM0SVX.

Consulta primero el estado:

```bash
sudo bash /var/www/html/install_svxlink_latest.sh --check
```

Para actualizar:

```bash
sudo bash /var/www/html/install_svxlink_latest.sh
```

### ⚠️ Importante

**Actualiza SvxLink solamente después de que la versión base instalada por la distribución esté completamente configurada y operativa.**

Antes de actualizar, confirma:

```bash
sudo systemctl status svxlink --no-pager
```

El servicio debe estar funcionando correctamente con el audio, RX/TX y la configuración real del nodo. El actualizador utiliza esa configuración como referencia para probar la nueva release antes de activarla y dispone de rollback automático si la validación falla.

---

## 🌐 Acceso al panel

Después de instalar:

```bash
hostname -I
```

Abre en el navegador:

```text
http://IP-DEL-SERVIDOR/
```

Credencial inicial:

```text
Contraseña: admin123
```

Cambia la contraseña después del primer acceso.

---

## 🛠️ Componentes principales

```text
index.php                      Dashboard principal
settings.php                   Configuración SvxLink / EchoLink / audio
status-node.php                Estado del servidor y servicio SvxLink
connections.php                Conexiones activas
activity_log.php               Bitácora de actividad
qsl_generator.php              Gestión de actividades y QSL
custom.php                     Personalización y red
monitor_log_svx.php            Monitor de eventos SvxLink
install_auroxlink.sh           Instalador principal
install_svxlink_latest.sh      Actualizador opcional de SvxLink
update_auroxlink.sh            Actualizador de AUROXLINK
```

---

## 📦 Versión actual

**AUROXLINK v1.8.1**

La rama `main` puede contener mejoras posteriores en preparación. Las versiones estables quedan identificadas mediante tags/releases.

👉 [Ver CHANGELOG.md](CHANGELOG.md)

---

## 🧑‍💻 Autor

**Román Carvajal — CE2RDP / TelecoViajero**  
Radioaficionado, telecomunicaciones y desarrollo de herramientas para la comunidad.

- GitHub: https://github.com/telecov
- QRZ: https://www.qrz.com/db/CE2RDP
- YouTube: https://www.youtube.com/@Telecoviajero
- Instagram: https://instagram.com/telecoviajero
- TikTok: https://tiktok.com/@telecoviajero

---

## 🙌 Agradecimientos

- **Esteban — CA3EUO**: auditoría de seguridad, desarrollo y colaboración técnica.
- **Fábio Guilherme — PY2FGD**: colaboración en traducciones.
- **Tobias Blomberg / SM0SVX** y colaboradores: desarrollo y mantenimiento del proyecto SvxLink.

---

## ❤️ Apoya AUROXLINK y futuros proyectos

AUROXLINK es un proyecto desarrollado para la comunidad. Si este software te ha sido útil y quieres ayudar a que siga creciendo, puedes **hacerte miembro del canal TelecoViajero en YouTube**.

Tu apoyo ayuda a seguir desarrollando **AUROXLINK y otros proyectos de radioafición, telecomunicaciones, redes y software**, además de permitir nuevas pruebas, documentación y mejoras para la comunidad.

<p align="center">
  <a href="https://www.youtube.com/channel/UCekZOnVxrOoDuJlFCgGKi9A/join">
    <img src="https://img.shields.io/badge/Hazte%20Miembro-YouTube-red?style=for-the-badge&logo=youtube" alt="Hazte miembro de TelecoViajero en YouTube">
  </a>
</p>

<p align="center">
  <b>Gracias por apoyar el desarrollo de herramientas abiertas para la comunidad de radioaficionados. 📡</b>
</p>

También puedes apoyar compartiendo AUROXLINK, reportando errores, proponiendo mejoras o marcando el repositorio con una ⭐.

---

## 🧯 Reportar problemas

Utiliza la sección **Issues** del repositorio e incluye, cuando sea posible:

- Modelo de Raspberry Pi o servidor
- Sistema operativo
- Versión de AUROXLINK
- Versión de SvxLink
- Descripción del problema
- Logs relevantes

---

**AUROXLINK — hecho para la comunidad de radioaficionados. 📡**
