# 📦 AUROXLINK Installation

🇪🇸 [Español](INSTALL.md) | 🇺🇸 English

AUROXLINK includes an automated installer that prepares the web server, PHP, the base SvxLink package, Tailscale, permissions, services and the files required to leave the system ready to use.

Updating **SvxLink to the latest official SM0SVX release is optional** and is handled by a separate installer.

---

## 🖥️ Requirements

### Recommended system

- Raspberry Pi OS or Debian-based system using APT
- Raspberry Pi, mini PC or Linux server
- Internet access
- SSH or local console access
- User with `sudo` privileges

AUROXLINK automatically installs the required dependencies, including Apache, PHP, PHP cURL, PHP Zip, Git, NetworkManager, ALSA, Tailscale and `svxlink-server`.

---

# 🚀 Quick installation

On a clean system run:

```bash
curl -fsSL https://raw.githubusercontent.com/telecov/auroxlink/main/install_auroxlink.sh | sudo bash
```

The installer automatically performs:

- Dependency installation
- Apache and PHP installation
- Installation of `svxlink-server` from the Linux distribution
- Tailscale installation
- Download of AUROXLINK from GitHub
- Initialization of default files
- Permission configuration
- Sudoers configuration required by the web panel
- Creation of the SvxLink monitor service
- AUROXLINK cron configuration
- Service activation
- Final installation verification

> The main installer **does not update SvxLink from GitHub**. The version provided by the Linux distribution is kept as the stable base.

---

## ✅ Verify the installation

Check the main services:

```bash
sudo systemctl status apache2 --no-pager
sudo systemctl status svxlink --no-pager
sudo systemctl status auroralink-monitor --no-pager
```

You can also check the installed AUROXLINK version:

```bash
cat /var/www/html/version.txt
```

---

## 🌐 Access the web panel

Get the server IP address:

```bash
hostname -I
```

Then open in a browser:

```text
http://SERVER-IP/
```

Initial AUROXLINK credential:

```text
Password: admin123
```

For security, change the password from AUROXLINK after the first login.

---

# 📡 Update SvxLink — optional

AUROXLINK includes a second installer for users who want to run the latest stable official SvxLink release published by SM0SVX.

First check whether a newer release is available:

```bash
sudo bash /var/www/html/install_svxlink_latest.sh --check
```

If you want to install it:

```bash
sudo bash /var/www/html/install_svxlink_latest.sh
```

To force a reinstall of the detected release:

```bash
sudo bash /var/www/html/install_svxlink_latest.sh --force
```

The SvxLink updater:

- Automatically checks the latest official stable release
- Keeps the Debian/Raspberry Pi OS SvxLink package as a fallback
- Builds the new release in a separate directory
- Keeps binaries, libraries, plugins and TCL event files from each version together
- Backs up `/etc/svxlink`
- Tests the new version before activation
- Checks that plugins from different versions are not mixed
- Changes the systemd service only if the test completes successfully
- Performs an automatic rollback if anything fails
- Keeps `/etc/svxlink/svxlink.conf` as the active AUROXLINK configuration file

Releases managed by this installer are stored in:

```text
/opt/auroxlink/svxlink/releases/
```

The active release is linked at:

```text
/opt/auroxlink/svxlink/current
```

---

## 🎙️ Audio configuration

List available ALSA devices:

```bash
aplay -l
arecord -l
```

Adjust audio levels with:

```bash
alsamixer
```

Then configure RX/TX from the AUROXLINK web panel.

---

## 📻 EchoLink configuration

Configure from AUROXLINK:

- Callsign
- EchoLink password
- Node name and description
- RX and TX
- Audio device
- SvxLink parameters

Then verify:

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

The installer installs Tailscale, but the node still needs to be associated with your account/Tailnet when required.

You can do this from AUROXLINK or from the console:

```bash
sudo tailscale up
```

Status:

```bash
tailscale status
```

---

## 🤖 Telegram — optional

From AUROXLINK you can configure:

- Bot token
- Chat ID

Create the bot using **@BotFather** and add it to the group or channel that will be used by the node.

Real Telegram credentials are stored locally and must not be committed to the repository.

---

## 🛠️ Useful commands

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

## 🔄 Reinstall or update AUROXLINK

The same installer can be run again:

```bash
curl -fsSL https://raw.githubusercontent.com/telecov/auroxlink/main/install_auroxlink.sh | sudo bash
```

If an existing installation is detected, the installer creates a backup before replacing the application code and preserves the configured data covered by the installer.

---

## 🧯 Support and issue reports

Official repository:

```text
https://github.com/telecov/auroxlink
```

To report a problem or propose an improvement, use the repository **Issues** section and include:

- Problem description
- Operating system
- Raspberry Pi or server model
- AUROXLINK version
- SvxLink version
- Relevant logs

---

**AUROXLINK v1.7**
