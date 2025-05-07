# 📦 Guía de Instalación de AuroxLink

Este documento explica cómo instalar **AuroxLink**, el sistema de control web para nodos SVXLink y estaciones EchoLink, en una Raspberry Pi o servidor Linux compatible.

---

## 🖥️ Requisitos

### Hardware recomendado:
- Raspberry Pi 3, 4, Zero 2 W
- Conectividad a Internet (Ethernet o WiFi)
- BANANA PI M2 ZERO EN PRUEBAS<------

### Software necesario:
- Sistema operativo basado en Debian (Raspbian, Raspberry Pi OS, etc.)
- Servidor web: Apache2
- PHP 7.4 o superior
- Git
- SVXLink instalado y funcionando
- NetworkManager nmcli
- alsa-utils 

## Software necesario para configurar 
- IPSCANNER - para identificar ip de equipo
- PUTTY - para administrar Linux por SSH
- Raspberry pi Imager (recomendado)
---

## ⚙️ Instalación paso a paso

### 1. Instala los paquetes base
```bash
sudo apt update
sudo apt install apache2 -y
sudo apt install php libapache2-mod-php -y
sudo apt install network-manager alsa-utils -y
```
### 2. Instalar SVXlink Server  (SvxLink v1.7.0 Copyright (C) 2003-2019 Tobias Blomberg / SM0SVX)
```bash
sudo apt-get update
sudo apt-get install svxlink-server
```
### 2.1 Instalar SVXlink Server [Upgrade] (SvxLink v1.8.0@24.02 Copyright (C) 2003-2023 Tobias Blomberg / SM0SVX)

...

### 3. Clona AuroxLink en tu servidor web (Auroxlink v1.4 2025 Roman Carvajal / CA2RDP)
```bash
cd /var/www/
sudo rm -rf /var/www/html
sudo git clone https://github.com/telecov/auroxlink.git html
```

### 3.1 Instalacion de idioma INGLES (sm0svx)
```bash
cd /usr/share/svxlink/sounds/
sudo wget https://github.com/sm0svx/svxlink-sounds-en_US-heather/releases/download/14.08/svxlink-sounds-en_US-heather-16k-13.12.tar.bz2
sudo tar xvjf svxlink-sounds-en_US-heather-16k-13.12.tar.bz2
sudo ln -s en_US-heather-16k en_US
```

### 3.2 Instalacion de idioma ESPAÑOL

PROXIMAMENTE....

### 4. Configura permisos para ejecutar comandos del sistema 
```bash
sudo chown -R www-data:www-data /var/www/html/
sudo chmod -R 775 /var/www/html/
sudo usermod -aG audio www-data
sudo chown www-data:www-data /etc/svxlink/svxlink.conf
sudo chown www-data:www-data /etc/svxlink/svxlink.d/ModuleEchoLink.conf

sudo nano /etc/sudoers.d/99-www-data-svxlink
#escribir estos permisos 

www-data ALL=NOPASSWD: /bin/systemctl restart svxlink
www-data ALL=NOPASSWD: /bin/systemctl start svxlink
www-data ALL=NOPASSWD: /bin/systemctl stop svxlink
www-data ALL=NOPASSWD: /sbin/reboot
www-data ALL=(ALL) NOPASSWD: /usr/bin/nmcli, /usr/sbin/ip, /bin/systemctl
www-data ALL=(ALL) NOPASSWD: /sbin/iwlist
www-data ALL=(ALL) NOPASSWD: /usr/bin/amixer

#guardar & cerrar
```
### 5. Crear servicio log monitor para telegram
```bash
sudo nano /etc/systemd/system/auroralink-monitor.service
#escribir esto

[Unit]
Description=AuroraLink - Monitor de Conexiones SVXLink
After=network.target

[Service]
ExecStart=/usr/bin/php /var/www/html/monitor_log_svx.php
Restart=always
User=www-data
Group=www-data
StandardOutput=append:/var/log/auroralink_monitor.log
StandardError=append:/var/log/auroralink_monitor_error.log

[Install]
WantedBy=multi-user.target

#guardar & cerrar
```


### 6. Inicia Servicios
```bash
sudo systemctl daemon-reload
sudo systemctl enable auroralink-monitor.service
sudo systemctl enable svxlink

sudo systemctl start svxlink
sudo systemctl start auroralink-monitor.service
```

✅ ¡AuroxLink está listo!
Ahora puedes acceder a tu panel desde el navegador:

http://IP-DE-TU-RASPBERRY/

🔧 Archivos importantes
index.php: dashboard principal
configuracion.php: editor de configuración EchoLink y SVXlink
----- password : admin123

estado-nodo.php: monitoreo del nodo
control_audio.php: control de volumen

### Comandos basicos 
```bash
sudo systemctl enable svxlink	-- INICIA SERVICIOS AUTOMATICAMENTE
sudo systemctl disable svxlink	-- PARA QUE NO INICIE AUTOMATICAMENTE
sudo systemctl status svxlink	-- CONSULTA STATUS OPERACIONAL DE SERVICIO
sudo systemctl start svxlink 	-- INICIA SERVICIOS
sudo systemctl stop svxlink	-- DETIENEN SERVICIO
sudo systemctl restart svxlink	-- REINICIA SERVICIO
sudo -u svxlink svxlink		-- INICIA SERVICIO EN VIVO
aplay -l 			-- LISTA DISPOSITIVOS CONECTADO
lsusb 				-- LISTA DISPOSITIVOS USB 
alsamixer			-- AJUSTAR AUDIO DE INTERFACE
lsb_release -a			-- REVISAR VERSION DE LINUX
```
### 7. Configura Telegram (opcional)

- Crea un bot en @BotFather
- Obten el token http api
- crea un canal o agraga tu bot como admin al grupo Telegram
- buscar el ID del canal o grupo a utilizar
	https://api.telegram.org/bot<token-de-telegram->/getUpdates
### 8. Personalizacion

- imagen debes subirla en 1500 px x 150 px
- debe estar en formato .png
- debes subirla con el nombre ----->>>>  auroxlink_banner.png
- puede ser cualquier imagen personalizada a tu antojo pero debe ser subida con ese nombre.

🧯 Soporte
Cualquier duda o aporte, estare agradecido de tu feedback - Román (CA2RDP)

🛠️ ¿Cómo crear un issue?
Ve al repositorio en GitHub (por ejemplo: https://github.com/telecov/auroxlink)
- Haz clic en la pestaña "Issues"
- Luego clic en "New Issue"
- Escribe:
---- Un título claro (ej: Agregar panel de control de audio)
-Una descripción detallada del problema o idea

- Haz clic en "Submit new issue"

