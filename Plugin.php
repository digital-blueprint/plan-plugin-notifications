<?php

namespace Kanboard\Plugin\Notifications;

use Kanboard\Core\Plugin\Base;

class Plugin extends Base
{
    public function initialize()
    {
        // No notification behavior is enabled in the initial scaffold.
    }

    public function getPluginName()
    {
        return 'Plan Notifications';
    }

    public function getPluginDescription()
    {
        return 'Scaffold for task subscriptions and project watches.';
    }

    public function getPluginAuthor()
    {
        return 'digital-blueprint';
    }

    public function getPluginVersion()
    {
        return '0.1.0-dev';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/digital-blueprint/plan-plugin-notifications';
    }
}
