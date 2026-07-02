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
│   ├── options.xml                             redeyedSiteKey / redeyedSecretKey / redeyedBaseUrl
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

---

## How it works

**Render** (`captcha_redeyed.html`) emits:

```html
<script src="https://redeyed.com/sentinel.js" async></script>
<div class="sentinel-captcha" data-sitekey="YOUR_SITE_KEY"></div>
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
