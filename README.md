# Redeyed Sentinel for XenForo

A self-contained XenForo **2.2 / 2.3** add-on that registers **Redeyed Sentinel**
as a CAPTCHA provider. Sentinel is a self-hosted CAPTCHA + IP-reputation
service.

- **Free to install.** No payment required for the add-on.
- **Inert until configured.** With no keys set, the handler *fails open* (always
  passes) so your board is never locked out before you finish setup.
- **Your secret API key is never exposed.** It is used only server-side, sent as
  the `X-Api-Key` header during verification.

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
│   ├── options.xml                             redeyedSiteKey / redeyedApiKey / redeyedBaseUrl
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
| **Sentinel Site Key** (`redeyedSiteKey`) | Yes | Redeyed **Lab → Developer → Sentinel Sites** (public, safe to expose) |
| **Sentinel API Key** (`redeyedApiKey`) | Yes | Redeyed **Developer → API Keys** (secret, server-side only) |
| **Sentinel Base URL** (`redeyedBaseUrl`) | No (default `https://redeyed.com`) | Only change for a self-hosted Sentinel instance |

Until **both** the Site Key and API Key are filled in, the handler stays inert
and passes every submission. Once configured it renders the widget and verifies
each token server-side.

---

## How it works

**Render** (`captcha_redeyed.html`) emits:

```html
<script src="https://redeyed.com/sentinel.js" async></script>
<div class="sentinel-captcha" data-sitekey="YOUR_SITE_KEY"></div>
```

The Sentinel script injects a hidden `sentinel-token` input into the form.

**Verify** (`Redeyed::isValid()`) reads `sentinel-token` from the request and
POSTs to `{baseUrl}/api/v1/verify` using XenForo's HTTP (Guzzle) client:

- Header: `X-Api-Key: {apiKey}`
- JSON body: `{"site_key":"{siteKey}","token":"<sentinel-token>"}`

A submission **passes** only when the decoded response has `data.success === true`
**or** `success === true`. Missing token or transport failure fails closed;
unconfigured keys fail open.

---

## License

MIT © 2026 Redeyed Corporation. See [LICENSE](LICENSE).
