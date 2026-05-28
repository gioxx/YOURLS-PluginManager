# 🔌 YOURLS Advanced Plugin Manager

[Readme file is also available in italian](README_IT.md).

[![Latest Release](https://img.shields.io/github/v/release/gioxx/YOURLS-PluginManager)](https://github.com/gioxx/YOURLS-PluginManager/releases)
[![License](https://img.shields.io/github/license/gioxx/YOURLS-PluginManager)](LICENSE)

**Install, update and manage YOURLS plugins directly from the admin interface.**  
Just feed it a GitHub URL and it handles the rest 🧙‍♂️

---

## 🚀 Features

- 🧲 Install plugins from any public GitHub repo (latest release, specific tag/version, or branch)
- ⬆ Upload and install plugins directly from a local `.zip` file
- 🔁 Auto-overwrite existing plugin folder (works like "update")
- ⏱️ Automatic update checks every 24 hours (plus manual on-demand checks per plugin or in bulk)
- 🤖 Auto-associates repository metadata from `Plugin URI` (when valid GitHub URLs are available)
- ✅ Verifies `plugin.php` structure before installing
- 🔐 Supports GitHub Personal Access Token (to avoid API limits)
- 📦 Extracts ZIP via `ZipArchive` (no dependencies)
- 📊 Shows version, author, status, and last update time — with clickable author links
- 🔗 Associate/Change repo via dedicated modal UI (with pre-filled URL on change)
- ▶ Activate / Deactivate each plugin directly from the manager (including self-deactivation)
- 🏷️ Detects plugins with source-code-only repos (no releases) and offers "Reinstall from source"
- ⚠️ Flags archived/abandoned repositories
- 🎨 Theme-aware UI — adapts to dark admin themes (Sleeky dark mode supported)
- 🔍 Filter plugins by All / Active / Inactive / Updatable / No metadata / Abandoned / Errors
- 🧼 Can delete inactive plugins safely
- 💬 Fully translatable (`.po/.mo` ready — Italian 🇮🇹 and Dutch 🇳🇱 included)

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
5. Go to **Tools > Advanced Plugin Manager** to start using it!

---

## ℹ️ Update Metadata Note

Plugins installed **before** repository metadata tracking was introduced may temporarily show **"No repository metadata"**.

To enable update checks and one-click updates for those plugins, reinstall or update each one once via Advanced Plugin Manager.

Default YOURLS plugins do not require repository association, and the UI now reflects this explicitly.

---

## 🆕 What’s New in 1.2.0

- **Theme-aware UI**: the admin panel now adapts to the active admin theme; dark variants are applied only when a dark theme (e.g. Sleeky dark) is explicitly detected — vanilla YOURLS stays light regardless of OS dark mode
- **Branch and release version inputs**: install a specific branch or a specific release tag instead of always pulling latest; when both are empty, falls back automatically: latest release → latest tag → default branch
- **Upload from ZIP**: install a plugin directly from a local `.zip` file without needing a GitHub URL
- **Activate / Deactivate toggle**: each plugin row now has a toggle button; self-deactivation is supported with a confirmation prompt
- **Per-plugin update check**: click the 🔎 button to run an update check for a single plugin without running a full bulk check
- **Source-code-only plugins**: repos with no release and no tag are now surfaced with a "Source code only" badge and a "Reinstall from source" action instead of an error
- **Abandoned repository detection**: repos that are archived on GitHub or have been moved/renamed are flagged as abandoned
- **Active / Inactive / Abandoned filter tabs**: new filter links in the installed plugins header
- **Clickable author links**: `Author URI` plugin header field is now rendered as a link, matching native YOURLS behaviour
- Dutch translation 🇳🇱 contributed by [@toineenzo](https://github.com/toineenzo)

## 🆕 What’s New in 1.1.5

- Self-update notifications:
  - the plugin now checks its own GitHub releases and shows a dashboard notice when a newer version is available
  - the plugin page title now shows an update badge when applicable

## 🆕 What’s New in 1.1.4

- Delete flow hardened:
  - automatic deletion now reports the exact plugin directory when YOURLS cannot remove it
  - the admin can delete that folder manually on the server

## 🆕 What’s New in 1.1.3

- Install flow hardened:
  - automatic extraction now runs only when `user/plugins` is writable by PHP
  - when permissions are missing, the plugin stops with a clear message and a direct ZIP download link for manual installation

## 🆕 What’s New in 1.1.2

- Minor consistency fix:
  - unified product naming across plugin metadata, UI copy, translations, and docs to **YOURLS Advanced Plugin Manager**

## 🧾 Previous highlights (1.1.1)

- UI naming updates:
  - plugin menu entry is now **Advanced Plugin Manager**
  - page title is now **YOURLS Advanced Plugin Manager**
- Plugin submenu quality of life:
  - plugin admin sublinks under **Manage Plugins** are now sorted alphabetically
- Installed plugins header actions:
  - added a **Manage** button that links directly to YOURLS native plugin management page (`admin/plugins.php`)
  - action buttons are now visually consistent
  - **Update all available** is disabled when no updates are available
- New integrated settings feature:
  - built-in `admin_view_per_page` customization (no separate plugin needed)
  - if legacy plugin **Custom number of displayed links** is detected, an in-panel warning suggests deactivating/removing it
  - credit: based on the snippet shared by **ozh** in YOURLS issue #2339: https://github.com/YOURLS/YOURLS/issues/2339#issuecomment-352127623

---

## 🐙 GitHub API Tips

By default, GitHub allows **60 unauthenticated requests/hour per IP**.

To increase the limit to **5000 req/hour**, use a **[GitHub Personal Access Token](https://github.com/settings/tokens/new)** (no scopes needed).

---

## 🌐 Localization

- English (`en_US`) — default
- Italian (`it_IT`) — included
- Dutch (`nl_NL`) — included, contributed by [@toineenzo](https://github.com/toineenzo)

You can contribute other translations by forking and submitting `.po`/`.mo` files to the `languages/` folder.

---

## 🤓 Example Plugin URLs

You can paste any of these into the GitHub URL field:

- `https://github.com/gioxx/YOURLS-LogoSuite`
- `https://github.com/YOURLS/antispam`

The plugin will automatically fetch the latest release, fall back to the latest tag, then fall back to the default branch.

---

## 🧩 Plugin Compatibility

To make your YOURLS plugin compatible with **Advanced Plugin Manager**, follow these simple guidelines:

### ✔️ What to do

- **Create a release** on your GitHub repository.  
  This will generate a `.zip` package that Advanced Plugin Manager can detect and download.
- Ensure your `plugin.php` file is:
  - in the **root** of the ZIP, **or**
  - in a **single subfolder** along with the rest of your plugin files.

### ❌ What to avoid

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

Lovingly developed by the usually-on-vacation brain cell of [Gioxx](https://github.com/gioxx), using Codex to speed up some of the development and correct some rubbish.  

---

## 🤝 Contributing

Pull requests and feature suggestions are welcome.  
If you find bugs or have feature requests, [open an issue](https://github.com/gioxx/YOURLS-PluginManager/issues).  
If you find it useful, leave a ⭐ on GitHub! ❤️
