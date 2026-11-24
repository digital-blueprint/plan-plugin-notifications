<?php

namespace Kanboard\Plugin\Notifications;

use Kanboard\Core\Plugin\Base;
use Kanboard\Plugin\Notifications\Job\NotificationJob;

class Plugin extends Base
{
    public function initialize()
    {
        if (!in_array(DB_DRIVER, ['sqlite', 'mysql', 'postgres'], true)) {
            return;
        }

        $this->container['notificationJob'] = $this->container->factory(function ($container) {
            return new NotificationJob($container);
        });
        $this->template->hook->attachCallable('template:task:sidebar:information', 'Notifications:subscription/task', function (array $task) {
            return $this->getControlState('task', $task['id'], $task['project_id']);
        });
        $this->template->hook->attachCallable('template:project:header:after', 'Notifications:subscription/project', function (array $project) {
            return $this->getControlState('project', $project['id'], $project['id']);
        });
    }

    public function getClasses()
    {
        return ['Plugin\\Notifications\\Model' => ['SubscriptionModel']];
    }

    private function getControlState($scope, $subjectId, $projectId)
    {
        $userId = $this->userSession->getId();
        if (!$this->subscriptionModel->allows($projectId, $userId)) {
            return ['notificationPreference' => null];
        }

        $table = $scope === 'task' ? Model\SubscriptionModel::TASK : Model\SubscriptionModel::PROJECT;
        return ['notificationPreference' => $this->subscriptionModel->get($table, $subjectId, $userId)];
    }

    public function getPluginName()
    {
        return 'Plan Notifications';
    }

    public function getPluginDescription()
    {
        return 'Task subscriptions and project watches for Plan.';
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
