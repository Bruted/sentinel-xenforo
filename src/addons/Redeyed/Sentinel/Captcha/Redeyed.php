<?php

namespace Redeyed\Sentinel\Captcha;

use XF\Captcha\AbstractCaptcha;
use XF\Template\Templater;

/**
 * Redeyed Sentinel CAPTCHA provider.
 *
 * Sentinel is a self-hosted CAPTCHA + IP-reputation service. This handler is
 * free to install and stays INERT (fails open) until a Site Key and a
 * Secret Key are configured under Setup -> Options -> Redeyed Sentinel.
 *
 * Verification uses a reCAPTCHA/Turnstile-style flow: the per-site Secret Key
 * authenticates the server-side verify call. The Secret Key is ONLY ever sent
 * in the POST body of that request and is never rendered to the page.
 */
class Redeyed extends AbstractCaptcha
{
    /**
     * Render the public widget. Outputs the Sentinel loader script and the
     * captcha container div; the Sentinel JS injects a hidden input named
     * "sentinel-token" that we read back on verification.
     *
     * @param Templater $templater
     * @return string
     */
    public function render(Templater $templater)
    {
        $options = \XF::options();

        $siteKey = isset($options->redeyedSiteKey) ? trim((string) $options->redeyedSiteKey) : '';
        $baseUrl = isset($options->redeyedBaseUrl) ? trim((string) $options->redeyedBaseUrl) : '';

        if ($baseUrl === '')
        {
            $baseUrl = 'https://redeyed.com';
        }
        // Normalise: strip any trailing slash so concatenation is predictable.
        $baseUrl = rtrim($baseUrl, '/');

        // Optional widget customisation. Each is rendered as a data-* attribute
        // on the captcha element, but ONLY when non-empty, so the widget keeps
        // its adaptive defaults when these are left blank (backward-compatible).
        $widget     = isset($options->sentinelWidget) ? trim((string) $options->sentinelWidget) : '';
        $theme      = isset($options->sentinelTheme) ? trim((string) $options->sentinelTheme) : '';
        $scheme     = isset($options->sentinelScheme) ? trim((string) $options->sentinelScheme) : '';
        $difficulty = isset($options->sentinelDifficulty) ? trim((string) $options->sentinelDifficulty) : '';
        $width      = isset($options->sentinelWidth) ? trim((string) $options->sentinelWidth) : '';

        return $templater->renderTemplate('public:captcha_redeyed', [
            'siteKey'    => $siteKey,
            'baseUrl'    => $baseUrl,
            'widget'     => $widget,
            'theme'      => $theme,
            'scheme'     => $scheme,
            'difficulty' => $difficulty,
            'width'      => $width,
        ]);
    }

    /**
     * Verify the submitted Sentinel token server-side.
     *
     * Contract (reCAPTCHA/Turnstile-style siteverify):
     *  - Empty Secret Key => fail open (return true) so the board is never
     *    locked out before configuration.
     *  - POST {baseUrl}/sentinel/siteverify with JSON body
     *    {"secret":"<secretKey>","response":"<sentinel-token>","remoteip":"<ip>"}.
     *    No X-Api-Key header is sent; the Secret Key alone authenticates.
     *  - PASSED only when decoded success === true.
     *
     * @return bool
     */
    public function isValid()
    {
        $options = \XF::options();

        $siteKey   = isset($options->redeyedSiteKey) ? trim((string) $options->redeyedSiteKey) : '';
        $secretKey = isset($options->redeyedSecretKey) ? trim((string) $options->redeyedSecretKey) : '';
        $baseUrl   = isset($options->redeyedBaseUrl) ? trim((string) $options->redeyedBaseUrl) : '';

        if ($baseUrl === '')
        {
            $baseUrl = 'https://redeyed.com';
        }
        $baseUrl = rtrim($baseUrl, '/');

        // INERT until configured: fail open when the Secret Key is missing.
        if ($secretKey === '')
        {
            return true;
        }

        $token = (string) $this->filterer->filter('sentinel-token', 'str');

        if ($token === '')
        {
            return false;
        }

        try
        {
            $client = \XF::app()->http()->client();

            $payload = [
                'secret'   => $secretKey,
                'response' => $token,
            ];

            // Optional: forward the client IP for reputation scoring.
            $remoteIp = \XF::app()->request()->getIp();
            if ($remoteIp !== '')
            {
                $payload['remoteip'] = $remoteIp;
            }

            $response = $client->post($baseUrl . '/sentinel/siteverify', [
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json'            => $payload,
                'timeout'         => 10,
                'connect_timeout' => 5,
                // Never throw on 4xx/5xx; we inspect the body ourselves.
                'http_errors'     => false,
            ]);

            $body = (string) $response->getBody();
            $data = @json_decode($body, true);

            if (!is_array($data))
            {
                return false;
            }

            // Response shape: {"success": true|false, "outcome": "...", "score": N}
            if (isset($data['success']) && $data['success'] === true)
            {
                return true;
            }

            return false;
        }
        catch (\Exception $e)
        {
            \XF::logException($e, false, 'Redeyed Sentinel CAPTCHA verification error: ');

            // Network/transport failure => treat as not verified (fail closed).
            return false;
        }
    }
}
