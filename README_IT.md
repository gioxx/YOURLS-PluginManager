# 🔌 YOURLS Advanced Plugin Manager

[Il file README è disponibile anche in inglese](README.md).

[![Ultima release](https://img.shields.io/github/v/release/gioxx/YOURLS-PluginManager)](https://github.com/gioxx/YOURLS-PluginManager/releases)
[![Licenza](https://img.shields.io/github/license/gioxx/YOURLS-PluginManager)](LICENSE)

**Installa, aggiorna e gestisci i plugin di YOURLS direttamente dall'interfaccia di amministrazione.**  
Ti basta incollare un URL GitHub e fa tutto da solo 🧙‍♂️

---

## 🚀 Funzionalità

- 🧲 Installa plugin da qualsiasi repository GitHub pubblico (release o tag)
- 🔁 Sovrascrive automaticamente i plugin esistenti (come un aggiornamento)
- ⏱️ Controllo aggiornamenti automatico ogni 24 ore (oltre al controllo manuale)
- 🤖 Auto-associa i metadati repository leggendo `Plugin URI` (quando l'URL GitHub è valido)
- ✅ Verifica la struttura di `plugin.php` prima di installare
- 🔐 Supporta GitHub Personal Access Token (per evitare i limiti API)
- 📦 Estrae i file ZIP tramite `ZipArchive` (senza dipendenze esterne)
- 📊 Mostra versione, autore, stato e data dell’ultimo aggiornamento
- 🔗 Associazione/Cambio repository tramite modale dedicato (con URL precompilato in modifica)
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
5. Vai su **Strumenti > Advanced Plugin Manager** per iniziare a usarlo!

---

## ℹ️ Nota sui metadati aggiornamenti

I plugin installati **prima** dell'introduzione del tracciamento dei metadati repository potrebbero mostrare temporaneamente **"No repository metadata"**.

Per abilitare controllo aggiornamenti e update con un click anche su questi plugin, reinstallali o aggiornali una volta tramite Advanced Plugin Manager.

I plugin predefiniti di YOURLS non richiedono associazione repository, e l'interfaccia ora lo esplicita chiaramente.

---

## 🆕 Novità nella 1.1.4

- Rafforzato il flusso di cancellazione:
  - la cancellazione automatica ora segnala la directory esatta del plugin quando YOURLS non riesce a rimuoverla
  - l'admin può eliminare manualmente quella cartella sul server

## 🆕 Novità nella 1.1.3

- Rafforzato il flusso di installazione:
  - l’estrazione automatica parte solo se `user/plugins` è scrivibile da PHP
  - in caso contrario il plugin si ferma con un messaggio chiaro e un link diretto al file ZIP per l’installazione manuale

## 🆕 Novità nella 1.1.2

- Minor fix di coerenza:
  - naming del prodotto uniformato in metadati plugin, testi UI, traduzioni e documentazione a **YOURLS Advanced Plugin Manager**

## 🧾 Highlight precedenti (1.1.1)

- Aggiornamenti naming UI:
  - voce menu plugin rinominata in **Advanced Plugin Manager**
  - titolo pagina aggiornato in **YOURLS Advanced Plugin Manager**
- Qualità vita sottomenu plugin:
  - le voci sotto **Manage Plugins** ora sono ordinate alfabeticamente
- Azioni header plugin installati:
  - aggiunto pulsante **Manage** che punta direttamente alla pagina nativa di gestione plugin YOURLS (`admin/plugins.php`)
  - pulsanti azione ora visivamente coerenti
  - **Aggiorna tutti i disponibili** viene disabilitato quando non ci sono aggiornamenti
- Nuova funzionalità integrata nelle impostazioni:
  - personalizzazione nativa di `admin_view_per_page` (senza plugin separato)
  - se è presente il plugin legacy **Custom number of displayed links**, viene mostrato un avviso per disattivarlo/rimuoverlo
  - credito: integrazione basata sullo snippet condiviso da **ozh** nel ticket YOURLS #2339: https://github.com/YOURLS/YOURLS/issues/2339#issuecomment-352127623

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

Per rendere il tuo plugin YOURLS compatibile con **Advanced Plugin Manager**, segui queste semplici indicazioni:

### ✔️ Cosa fare

- **Crea una release** nel repository GitHub del tuo plugin.  
  In questo modo verrà generato un pacchetto `.zip` che Advanced Plugin Manager potrà rilevare e installare.
- Assicurati che il file `plugin.php` sia:
  - nella **root** dell’archivio ZIP, **oppure**
  - in **una sola sottocartella** insieme agli altri file del plugin.

### ❌ Cosa evitare

- Non lasciare il repository in formato *flat* senza una release:  
  in tal caso, Advanced Plugin Manager **non troverà alcun contenuto installabile**.
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
