# Redeyed Sentinel for XenForo

A self-contained XenForo **2.2 / 2.3** add-on that registers **Redeyed Sentinel**
as a CAPTCHA provider. Sentinel is a self-hosted CAPTCHA + IP-reputation
service.

- **Free to install.** No payment required for the add-on.
- **Inert until configured.** With no Secret Key set, the handler *fails open*
  (always passes) so your board is never locked out before you finish setup.
- **Your Secret Key is never exposed.** It is used only server-side, sent in the
  POST body of the `/sentinel/siteverify` call (reCAPTCHA/Turnstile-style). No
  developer API key is required.

Add-on ID: `Redeyed/Sentinel`

---

## Requirements

- XenForo 2.2.0 or newer (tested against 2.2 / 2.3).

## What's in the box

```
src/addons/Redeyed/Sentinel/
├── addon.json                                  add-on manifest
├── Setup.php                                   install/upgrade/uninstall stub
├── Captcha/Redeyed.php                         the CAPTCHA handler (render + isValid)
├── _data/
│   ├── option_groups.xml                       "Redeyed Sentinel" options group
│   ├── options.xml                             keys + optional widget customisation (widget/theme/scheme/difficulty)
│   └── phrases.xml                             option + group titles/explanations
└── _output/templates/public/
    └── captcha_redeyed.html                    widget template (script + captcha div)
```

---

## Installation

### Option A — copy the files (recommended for self-hosted boards)

1. Copy the `src/addons/Redeyed/` folder into your board's `src/addons/`
   directory, so you end up with `src/addons/Redeyed/Sentinel/...`.
2. In the Admin Control Panel go to **Setup → Add-ons**.
3. Find **Redeyed Sentinel** in the *Available* list and click **Install**.

### Option B — upload a ZIP via the installer

1. Zip the **contents of `src/`** so the archive contains
   `addons/Redeyed/Sentinel/...` at its root (XenForo's "Install/upgrade from
   archive" expects the `addons/` path inside the zip).
2. ACP → **Setup → Add-ons → Install/upgrade from archive**, upload the zip,
   then click **Install**.

> If you have CLI access you can also run `php cmd.php xf-addon:install Redeyed/Sentinel`.

---

## Selecting Redeyed as the active CAPTCHA

XenForo **discovers CAPTCHA handlers automatically** — any class that extends
`XF\Captcha\AbstractCaptcha` (here, `Redeyed\Sentinel\Captcha\Redeyed`) becomes a
selectable provider once its add-on is installed. You do **not** register it
manually; you just pick it.

To make Sentinel the active provider:

1. ACP → **Setup → Options → Basic options** (the *CAPTCHA* section).
2. Set the **CAPTCHA question/answer service** (the `captcha` /
   `captcha_handler_class` option) to **Redeyed** / `Redeyed\Sentinel\Captcha\Redeyed`.
3. Save.

That's the only step required to switch XenForo from its built-in CAPTCHA to
Redeyed Sentinel.

---

## Configuration (keys)

ACP → **Setup → Options → Redeyed Sentinel**:

| Option | Required | Where to get it |
| --- | --- | --- |
| **Sentinel Site Key** (`redeyedSiteKey`) | Yes | Redeyed **Lab → Sentinel → Sites** (public, safe to expose) |
| **Sentinel Secret Key** (`redeyedSecretKey`) | Yes | Redeyed **Lab → Sentinel → Sites** (secret, server-side only — shown once) |
| **Sentinel Base URL** (`redeyedBaseUrl`) | No (default `https://redeyed.com`) | Only change for a self-hosted Sentinel instance |

Both keys come from the same place — **Lab → Sentinel → Sites**. The Site Key is
public and renders the widget; the Secret Key is displayed only once, so copy it
when you create the site. **No developer API key is needed.**

Until the Secret Key is filled in, the handler stays inert and passes every
submission. Once configured it renders the widget and verifies each token
server-side.

### Optional widget customisation

ACP → **Setup → Options → Redeyed Sentinel** also exposes four **optional**
appearance/behaviour settings. Each is emitted as a `data-*` attribute on the
captcha element **only when you fill it in** — leave any of them blank to keep
Sentinel's adaptive defaults. All are backward-compatible; a widget with none of
them set renders exactly as before.

| Option | Attribute | Accepted values |
| --- | --- | --- |
| **Sentinel widget type** (`sentinelWidget`) | `data-widget` | `behavioral`, `checkbox`, `press_hold`, `image_pick`, … (blank = site default) |
| **Sentinel theme** (`sentinelTheme`) | `data-theme` | `auto`, `light`, `dark` (blank = `auto`) |
| **Sentinel colour scheme** (`sentinelScheme`) | `data-scheme` | colour scheme / accent (blank = default) |
| **Sentinel difficulty** (`sentinelDifficulty`) | `data-difficulty` | `easy`, `medium`, `hard`, `max` or `1`–`6` (blank = adaptive) |
| **Sentinel widget width** (`sentinelWidth`) | `data-width` | `full`, `100%`, `340px`, … (blank = default) |

> **Difficulty only raises the bar.** `sentinelDifficulty` sets a *minimum*
> challenge strength: it can only **raise** difficulty above Sentinel's adaptive
> baseline, never lower it. Leave it blank to let Sentinel scale the challenge
> per visitor.

---

## How it works

**Render** (`captcha_redeyed.html`) emits:

```html
<script src="https://redeyed.com/sentinel.js" async></script>
<div class="sentinel-captcha" data-sitekey="YOUR_SITE_KEY"></div>
```

When the optional customisation options are set, their `data-*` attributes are
appended to the same element (only the non-empty ones), e.g.:

```html
<div class="sentinel-captcha" data-sitekey="YOUR_SITE_KEY"
     data-widget="checkbox" data-theme="dark" data-difficulty="hard"></div>
```

The Sentinel script injects a hidden `sentinel-token` input into the form.

**Verify** (`Redeyed::isValid()`) reads `sentinel-token` from the request and
POSTs to `{baseUrl}/sentinel/siteverify` using XenForo's HTTP (Guzzle) client
(a reCAPTCHA/Turnstile-style `siteverify` call — no `X-Api-Key` header):

- JSON body: `{"secret":"{secretKey}","response":"<sentinel-token>","remoteip":"<client ip>"}`
  (`remoteip` is optional)

The response has the shape `{"success": true|false, "outcome": "...", "score": N}`.
A submission **passes** only when `success === true`. Missing token or transport
failure fails closed; an unconfigured Secret Key fails open.

---

## Changelog

### 1.0.4
- **Block log.** Blocked submissions (IP, outcome, score) are recorded to
  XenForo's **server error log** (Admin CP → Logs → Server error log), toggled
  by the new **Log blocked attempts** option (on by default). The Secret Key is
  never logged.
- Note: as a CAPTCHA provider, *which* actions require the widget (registration,
  contact, guest posting, …) is controlled by XenForo itself — set Redeyed
  Sentinel as your board CAPTCHA under **Setup → Options → Basic board
  information → CAPTCHA** — so per-form control is native and needs no add-on
  option.

### 1.0.3
- **Added widget Width option.** New optional **Sentinel widget width**
  (`sentinelWidth` → `data-width`) renders on the captcha element only when set,
  e.g. `full`, `100%` or `340px`. Backward-compatible; blank leaves the default.

### 1.0.2
- **Added optional widget customisation.** Four new, optional options render as
  `data-*` attributes on the captcha element only when non-empty, so existing
  installs are unaffected: **Sentinel widget type** (`sentinelWidget` →
  `data-widget`), **Sentinel theme** (`sentinelTheme` → `data-theme`), **Sentinel
  colour scheme** (`sentinelScheme` → `data-scheme`) and **Sentinel difficulty**
  (`sentinelDifficulty` → `data-difficulty`).
- `sentinelDifficulty` only **raises** challenge strength above Sentinel's
  adaptive baseline; it never lowers it. Blank leaves everything adaptive.

### 1.0.1
- **Fixed CAPTCHA verification.** Verification no longer requires a developer API
  key. It now uses a reCAPTCHA/Turnstile-style flow where each site's own
  **Secret Key** authenticates the verify call.
- Renamed the `redeyedApiKey` option → `redeyedSecretKey` ("API Key" → "Secret
  Key"). Existing values are migrated automatically on upgrade.
- Verify now POSTs to `{baseUrl}/sentinel/siteverify` with body
  `{"secret","response","remoteip"}` (no `X-Api-Key` header). Passes when the
  response `success === true`.

### 1.0.0
- Initial release.

## License

MIT © 2026 Redeyed Corporation. See [LICENSE](LICENSE).
