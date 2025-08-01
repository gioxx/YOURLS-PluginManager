# 🔌 YOURLS Plugin Manager

[Il file README è disponibile anche in inglese](README.md).

[![Ultima release](https://img.shields.io/github/v/release/gioxx/YOURLS-PluginManager)](https://github.com/gioxx/YOURLS-PluginManager/releases)
[![Licenza](https://img.shields.io/github/license/gioxx/YOURLS-PluginManager)](LICENSE)

**Installa, aggiorna e gestisci i plugin di YOURLS direttamente dall'interfaccia di amministrazione.**  
Ti basta incollare un URL GitHub e fa tutto da solo 🧙‍♂️

---

## 🚀 Funzionalità

- 🧲 Installa plugin da qualsiasi repository GitHub pubblico (release o tag)
- 🔁 Sovrascrive automaticamente i plugin esistenti (come un aggiornamento)
- ✅ Verifica la struttura di `plugin.php` prima di installare
- 🔐 Supporta GitHub Personal Access Token (per evitare i limiti API)
- 📦 Estrae i file ZIP tramite `ZipArchive` (senza dipendenze esterne)
- 📊 Mostra versione, autore, stato e data dell’ultimo aggiornamento
- 🧼 Permette di eliminare i plugin non attivi
- 💬 Completamente localizzabile (file `.po/.mo` pronti — incluso italiano 🇮🇹)

---

## 🔧 Requisiti

- PHP con estensione [`ZipArchive`](https://www.php.net/manual/it/class.ziparchive.php) attiva (presente nella maggior parte degli hosting)
- YOURLS versione 1.8 o superiore

---

## 🛠️ Installazione

1. Clona o scarica questo repository
2. Copia la cartella `yourls-plugin-manager` dentro `user/plugins/`
3. (Facoltativo) Crea la cartella `/languages` e aggiungi i file `.mo`
4. Attiva il plugin dal pannello amministrativo di YOURLS
5. Vai su **Strumenti > Plugin Manager** per iniziare a usarlo!

---

## 🐙 Suggerimenti per l’API GitHub

Per impostazione predefinita, GitHub consente **60 richieste API non autenticate/ora per IP**.

Per aumentare il limite a **5.000 richieste/ora**, puoi usare un **[token personale GitHub](https://github.com/settings/tokens/new)** (nessun permesso richiesto).

---

## 🌐 Localizzazione

- Inglese (`en_US`) — predefinito
- Italiano (`it_IT`) — incluso  
Puoi contribuire con altre lingue inviando i file `.po`/`.mo` nella cartella `languages/`.

---

## 🤓 Esempi di URL plugin

Puoi incollare uno di questi URL nel campo GitHub del plugin:

- `https://github.com/gioxx/YOURLS-LogoSuite`
- `https://github.com/YOURLS/antispam`

Il plugin cercherà automaticamente l’ultima release o, se assente, il tag più recente.

---

## 🧩 Compatibilità dei plugin

Per rendere il tuo plugin YOURLS compatibile con **Plugin Manager**, segui queste semplici indicazioni:

### ✔️ Cosa fare

- **Crea una release** nel repository GitHub del tuo plugin.  
  In questo modo verrà generato un pacchetto `.zip` che Plugin Manager potrà rilevare e installare.
- Assicurati che il file `plugin.php` sia:
  - nella **root** dell’archivio ZIP, **oppure**
  - in **una sola sottocartella** insieme agli altri file del plugin.

### ❌ Cosa evitare

- Non lasciare il repository in formato *flat* senza una release:  
  in tal caso, Plugin Manager **non troverà alcun contenuto installabile**.
- Evita strutture annidate come `tuo-plugin/cartella/plugin.php`.

### 📦 Struttura corretta

```text
tuo-plugin/
├── plugin.php
├── readme.md
└── ...
```

Poi pubblica una release partendo dal link: https://github.com/tuo-utente/tuo-plugin/releases/new (sostituisci tuo-utente e tuo-plugin con i dati corretti).

---

## 📄 Licenza

Questo plugin è distribuito con licenza [MIT](LICENSE).  
Utilizza solo funzionalità native PHP — nessuna libreria copyleft o esterna inclusa.

---

## 💬 Info

Sviluppato con ❤️ dal neurone solitamente in ferie di [Gioxx](https://github.com/gioxx), utilizzando anche ChatGPT per velocizzare parte dello sviluppo e correggere alcune baggianate.

---

## 🤝 Contribuisci

Pull request e suggerimenti sono benvenuti.  
Se trovi bug o hai richieste di funzionalità, [apri una issue](https://github.com/gioxx/YOURLS-PluginManager/issues).  
Se lo trovi utile, lascia una ⭐ su GitHub! ❤️
