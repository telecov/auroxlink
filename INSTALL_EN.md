# 📦 AuroxLink Installation Guide


🇪🇸 [Español](INSTALL.md) | 🇺🇸 English

This document explains how to install **AuroxLink**, the web control system for SVXLink nodes and EchoLink stations, on a Raspberry Pi or compatible Linux server.

---

## 🖥️ Requirements

### Recommended Hardware:
AUROXLINK has been tested and works optimally on:

- **Recommended distribution:** Debian 12 / Raspbian 12 (bookworm)

- **Compatible environments:** Raspberry Pi OS, Ubuntu Server, Armbian (bookworm)
- **Recommended equipment:** Computer or mini-server running Linux

### Required Software:
- Debian-based operating system (Raspbian, Debian, etc.)
- Web server: Apache2
- PHP 8.2
- Git
- SVXLink installed and running
- NetworkManager (nmcli)
- alsa-utils 

## Required software for configuration
- IPSCANNER – to identify device IP
- PUTTY – to manage Linux via SSH
- Raspberry Pi Imager (recommended)

---

## ⚙️ Step-by-step Installation

### 1. Install base packages

```bash
sudo apt update
sudo apt install apache2 -y
sudo apt install php libapache2-mod-php -y
sudo apt install network-manager alsa-utils -y
sudo apt install git -y
```

### 2. Install SVXLink Server

```bash
sudo apt-get update
sudo apt-get install svxlink-server
```

### 2.1 Install SVXLink Server [Upgrade]

...

### 3. Clone AuroxLink into your web server

```bash
cd /var/www/
sudo rm -rf /var/www/html
sudo git clone https://github.com/telecov/auroxlink.git html
```

### 3.1 Install ENGLISH language

```bash
cd /usr/share/svxlink/sounds/
sudo wget https://github.com/sm0svx/svxlink-sounds-en_US-heather/releases/download/14.08/svxlink-sounds-en_US-heather-16k-13.12.tar.bz2
sudo tar xvjf svxlink-sounds-en_US-heather-16k-13.12.tar.bz2
sudo ln -s en_US-heather-16k en_US
```

### 3.2 Install SPANISH language

COMING SOON....

### 4. Configure permissions

```bash
sudo chown -R www-data:www-data /var/www/html/
sudo chmod -R 775 /var/www/html/
sudo usermod -aG audio www-data
sudo chown www-data:www-data /etc/svxlink/svxlink.conf
sudo chown www-data:www-data /etc/svxlink/svxlink.d/ModuleEchoLink.conf
```

```bash
sudo nano /etc/sudoers.d/99-www-data-svxlink
```

```bash
www-data ALL=NOPASSWD: /bin/systemctl restart svxlink
www-data ALL=NOPASSWD: /bin/systemctl start svxlink
www-data ALL=NOPASSWD: /bin/systemctl stop svxlink
www-data ALL=NOPASSWD: /sbin/reboot
www-data ALL=(ALL) NOPASSWD: /usr/bin/nmcli, /usr/sbin/ip, /bin/systemctl
www-data ALL=(ALL) NOPASSWD: /sbin/iwlist
www-data ALL=(ALL) NOPASSWD: /usr/bin/amixer
www-data ALL=(ALL) NOPASSWD: /usr/bin/bash /tmp/update_auroxlink.sh
```

### 5. Create log monitor service

```bash
sudo nano /etc/systemd/system/auroralink-monitor.service
```

```bash
[Unit]
Description=AuroxLink - SVXLink Connection Monitor
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
```

### 6. Start Services

```bash
sudo systemctl daemon-reload
sudo systemctl enable svxlink
sudo systemctl start svxlink
sudo systemctl status svxlink
```

```bash
sudo systemctl enable auroralink-monitor.service
sudo systemctl start auroralink-monitor.service
sudo systemctl status auroralink-monitor.service
```

AuroxLink is ready!

```bash
Access from browser:
http://IP-OF-YOUR-RASPBERRY/

password : admin123

```
```bash
### Basic commands
sudo systemctl enable svxlink
sudo systemctl disable svxlink
sudo systemctl status svxlink
sudo systemctl start svxlink
sudo systemctl stop svxlink
sudo systemctl restart svxlink
sudo -u svxlink svxlink
aplay -l
lsusb
alsamixer
lsb_release -a
sudo ls -l /dev/ttyUSB*
sudo dmesg | grep ttyUSB
```

### Telegram (optional)
- Create bot in @BotFather
- Get token
- Add bot to group/channel
- Get ID:
https://api.telegram.org/bot<TOKEN>/getUpdates

---

### Customization
- Image: 1500x150 px
- Format: .png
- Name: auroxlink_banner.png

---

Support:
Román Carvajal (CA2RDP)

GitHub:
https://github.com/telecov/auroxlink
