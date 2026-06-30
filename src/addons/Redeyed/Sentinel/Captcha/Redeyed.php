<?php

namespace Redeyed\Sentinel\Captcha;

use XF\Captcha\AbstractCaptcha;
use XF\Template\Templater;

/**
 * Redeyed Sentinel CAPTCHA provider.
 *
 * Sentinel is a self-hosted CAPTCHA + IP-reputation service. This handler is
 * free to install and stays INERT (fails open) until both a Site Key and an
 * API Key are configured under Setup -> Options -> Redeyed Sentinel.
 *
 * The secret API key is ONLY ever sent in a server-side request header
 * (X-Api-Key) and is never rendered to the page.
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

        return $templater->renderTemplate('public:captcha_redeyed', [
            'siteKey' => $siteKey,
            'baseUrl' => $baseUrl,
        ]);
    }

    /**
     * Verify the submitted Sentinel token server-side.
     *
     * Contract:
     *  - Empty keys => fail open (return true) so the board is never locked out
     *    before configuration.
     *  - POST {baseUrl}/api/v1/verify with header X-Api-Key: {apiKey}
     *    and JSON body {"site_key":"{siteKey}","token":"<sentinel-token>"}.
     *  - PASSED only when decoded data.success === true OR success === true.
     *
     * @return bool
     */
    public function isValid()
    {
        $options = \XF::options();

        $siteKey = isset($options->redeyedSiteKey) ? trim((string) $options->redeyedSiteKey) : '';
        $apiKey  = isset($options->redeyedApiKey) ? trim((string) $options->redeyedApiKey) : '';
        $baseUrl = isset($options->redeyedBaseUrl) ? trim((string) $options->redeyedBaseUrl) : '';

        if ($baseUrl === '')
        {
            $baseUrl = 'https://redeyed.com';
        }
        $baseUrl = rtrim($baseUrl, '/');

        // INERT until configured: fail open when either key is missing.
        if ($siteKey === '' || $apiKey === '')
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

            $response = $client->post($baseUrl . '/api/v1/verify', [
                'headers' => [
                    'X-Api-Key'    => $apiKey,
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'site_key' => $siteKey,
                    'token'    => $token,
                ],
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

            if (isset($data['data']['success']) && $data['data']['success'] === true)
            {
                return true;
            }

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
