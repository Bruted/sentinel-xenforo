<?php

namespace Redeyed\Sentinel;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\AddOn\StepRunnerUninstallTrait;

/**
 * Install / upgrade / uninstall for the Redeyed Sentinel add-on.
 *
 * The option group, options and phrases are imported automatically from the
 * _data XML files, so this setup mostly exists to satisfy the StepRunner
 * traits and to give us a hook for any future schema work. No custom database
 * tables are required by Sentinel.
 */
class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1(): void
    {
        // Nothing to install beyond the imported _data (options, group, phrases).
    }

    public function upgrade2Step1(): void
    {
        // Reserved for future upgrades.
    }

    /**
     * 1.0.1: verification switched from a developer API key (X-Api-Key header)
     * to a per-site Secret Key posted to /sentinel/siteverify. The option was
     * renamed redeyedApiKey -> redeyedSecretKey. Carry over any existing value
     * so upgraded boards keep working, then drop the stale option row.
     */
    public function upgrade1000171Step1(): void
    {
        $db = $this->db();

        $oldValue = $db->fetchOne(
            'SELECT option_value FROM xf_option WHERE option_id = ?',
            'redeyedApiKey'
        );

        if ($oldValue !== null && $oldValue !== '')
        {
            $db->query(
                'UPDATE xf_option SET option_value = ? WHERE option_id = ?',
                [$oldValue, 'redeyedSecretKey']
            );
        }

        // Remove the obsolete option row (the new options.xml no longer imports it).
        $db->delete('xf_option', 'option_id = ?', 'redeyedApiKey');
    }

    public function uninstallStep1(): void
    {
        // Options, option group and phrases are removed automatically when the
        // add-on is uninstalled. Nothing custom to tear down.
    }
}
