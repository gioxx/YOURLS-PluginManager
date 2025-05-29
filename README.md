# 🔌 YOURLS Plugin Manager

[Readme file is also available in italian](README_IT.md).

[![Latest Release](https://img.shields.io/github/v/release/gioxx/YOURLS-PluginManager)](https://github.com/gioxx/YOURLS-PluginManager/releases)
[![License](https://img.shields.io/github/license/gioxx/YOURLS-PluginManager)](LICENSE)

**Install, update and manage YOURLS plugins directly from the admin interface.**  
Just feed it a GitHub URL and it handles the rest 🧙‍♂️

---

## 🚀 Features

- 🧲 Install plugins from any public GitHub repo (release or tag)
- 🔁 Auto-overwrite existing plugin folder (works like "update")
- ✅ Verifies `plugin.php` structure before installing
- 🔐 Supports GitHub Personal Access Token (to avoid API limits)
- 📦 Extracts ZIP via `ZipArchive` (no dependencies)
- 📊 Shows version, author, status, and last update time
- 🧼 Can delete inactive plugins safely
- 💬 Fully translatable (`.po/.mo` ready — Italian included 🇮🇹)

---

## 🔧 Requirements

- PHP with [`ZipArchive`](https://www.php.net/manual/en/class.ziparchive.php) extension (default in most hosting)
- YOURLS 1.8+

---

## 🛠️ Installation

1. Clone or download this repo
2. Copy the folder `yourls-plugin-manager` into your `user/plugins/` directory
3. (Optional) Create a `/languages` folder and add `.mo` translations
4. Activate the plugin from the YOURLS admin interface
5. Go to **Tools > Plugin Manager** to start using it!

---

## 🐙 GitHub API Tips

By default, GitHub allows **60 unauthenticated requests/hour per IP**.

To increase the limit to **5000 req/hour**, use a **[GitHub Personal Access Token](https://github.com/settings/tokens/new)** (no scopes needed).

---

## 🌐 Localization

- English (`en_US`) — default
- Italian (`it_IT`) — included  
You can contribute other translations by forking and submitting `.po`/`.mo` files to the `languages/` folder.

---

## 🤓 Example Plugin URLs

You can paste any of these into the GitHub URL field:

- `https://github.com/gioxx/YOURLS-LogoSuite`
- `https://github.com/YOURLS/antispam`

The plugin will automatically fetch the latest release or fallback to the latest tag.

---

## 🧩 Plugin Compatibility

To make your YOURLS plugin compatible with **Plugin Manager**, follow these simple guidelines:

### ✔️ What to do

- **Create a release** on your GitHub repository.  
  This will generate a `.zip` package that Plugin Manager can detect and download.
- Ensure your `plugin.php` file is:
  - in the **root** of the ZIP, **or**
  - in a **single subfolder** along with the rest of your plugin files.

### ❌ What to avoid

- Do not leave your repository in a flat layout without a release:  
  in this case, Plugin Manager will **not find any installable content**.
- Avoid deeply nested folders like `your-plugin/another-folder/plugin.php`.

### 📦 Example structure

```text
your-plugin/
├── plugin.php
├── readme.md
└── ...
```

Then publish a release starting with the link: https://github.com/tuo-utente/tuo-plugin/releases/new (replace your-user and your-plugin with the correct informations).

---

## 📄 License

This plugin is licensed under the [MIT License](LICENSE).  
It uses only native PHP features — no bundled third-party code or copyleft libraries.

---

## 💬 About

Lovingly developed by the usually-on-vacation brain cell of [Gioxx](https://github.com/gioxx), using ChatGPT to speed up some of the development and correct some rubbish.  

---

## 🤝 Contributing

Pull requests and feature suggestions are welcome.  
If you find bugs or have feature requests, [open an issue](https://github.com/gioxx/YOURLS-PluginManager/issues).  
If you find it useful, leave a ⭐ on GitHub! ❤️
