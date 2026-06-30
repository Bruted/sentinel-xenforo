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

    public function uninstallStep1(): void
    {
        // Options, option group and phrases are removed automatically when the
        // add-on is uninstalled. Nothing custom to tear down.
    }
}
