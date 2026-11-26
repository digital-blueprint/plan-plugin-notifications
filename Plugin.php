<?php

namespace Kanboard\Plugin\Notifications;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Translator;
use Kanboard\Plugin\Notifications\Job\NotificationJob;

class Plugin extends Base
{
    private $projectMenuAttached = false;

    public function initialize()
    {
        if (!in_array(DB_DRIVER, ['sqlite', 'mysql', 'postgres'], true)) {
            return;
        }

        $this->container['notificationJob'] = $this->container->factory(function ($container) {
            return new NotificationJob($container);
        });
        $this->template->setTemplateOverride('notification/comment_create', 'Notifications:notification/comment_create');
        $this->template->setTemplateOverride('notification/comment_user_mention', 'Notifications:notification/comment_user_mention');
        $this->template->setTemplateOverride('user_view/notifications', 'Notifications:user_view/notifications');
        $this->hook->on('template:layout:css', ['template' => 'plugins/Notifications/Assets/css/notifications.css']);
        $this->template->hook->attachCallable('template:task:sidebar:information', 'Notifications:subscription/task', function (array $task) {
            return $this->getControlState('task', $task['id'], $task['project_id'], $task);
        });
    }

    public function onStartup()
    {
        if (!in_array(DB_DRIVER, ['sqlite', 'mysql', 'postgres'], true)) {
            return;
        }

        Translator::load($this->languageModel->getCurrentLanguage(), __DIR__.'/Locale');

        // Attach at bootstrap so the bell follows view links added by other plugins.
        // Queue workers bootstrap once per job, so attach only once per process.
        if ($this->projectMenuAttached) {
            return;
        }

        $this->projectMenuAttached = true;
        $this->template->hook->attachCallable('template:project-header:view-switcher', 'Notifications:subscription/project', function (array $project) {
            // Task pages reuse the project header but have their own notification control.
            if ($this->request->getIntegerParam('task_id') > 0) {
                return ['notificationPreference' => null];
            }

            return $this->getControlState('project', $project['id'], $project['id']);
        });
    }

    public function getClasses()
    {
        return ['Plugin\\Notifications\\Model' => ['SubscriptionModel']];
    }

    private function getControlState($scope, $subjectId, $projectId, array $task = [])
    {
        $userId = $this->userSession->getId();
        if (!$this->subscriptionModel->allows($projectId, $userId)) {
            return ['notificationPreference' => null];
        }

        $table = $scope === 'task' ? Model\SubscriptionModel::TASK : Model\SubscriptionModel::PROJECT;
        $preference = $this->subscriptionModel->get($table, $subjectId, $userId);
        return [
            'notificationPreference' => $preference,
            'notificationWarnings' => $this->getWarnings($userId, $projectId, $scope === 'task' ? $preference : null, $task),
        ];
    }

    // Explain settings that make the menu choice behave differently than its label suggests.
    private function getWarnings($userId, $projectId, $taskPreference, array $task)
    {
        $types = $this->userNotificationTypeModel->getSelectedTypes($userId);
        $email = in_array('email', $types, true);
        $web = in_array('web', $types, true);
        if (!$email && !$web) {
            return [t('Email and web notifications are off.')];
        }

        $user = $this->userModel->getById($userId);
        $warnings = [];
        if ($email && empty($user['email'])) {
            $warnings[] = $web
                ? t('Your account has no email address, so you only get web notifications.')
                : t('Your account has no email address, so you get no notifications. Add an email address or turn on web notifications.');
        }

        $projects = $this->userNotificationFilterModel->getSelectedProjects($userId);
        if (!empty($projects) && !in_array($projectId, $projects)) {
            $warnings[] = t('Your profile only allows notifications from other projects. In this project you only get direct @mentions, even if you are subscribed or watching.');
            return $warnings;
        }

        if ($taskPreference === Model\SubscriptionModel::UNSUBSCRIBE) {
            $projectPreference = $this->subscriptionModel->get(Model\SubscriptionModel::PROJECT, $projectId, $userId);
            if ($projectPreference === Model\SubscriptionModel::WATCH) {
                $warnings[] = t('You watch all activity in this project, so you still get alerts for this task. Choose Muted to stop them.');
            } elseif ((int) $user['notifications_filter'] !== Model\SubscriptionModel::PROFILE_NO_TASKS &&
                $this->userNotificationFilterModel->shouldReceiveNotification($user, ['task' => $task])) {
                $warnings[] = t('Your profile notification rules match this task, so you still get alerts for it. Choose Muted to stop them.');
            }
        }

        return $warnings;
    }

    public function getPluginName()
    {
        return 'Plan Notifications';
    }

    public function getPluginDescription()
    {
        return t('Task subscriptions and project watches for Plan.');
    }

    public function getPluginAuthor()
    {
        return 'digital-blueprint';
    }

    public function getPluginVersion()
    {
        return '0.1.0';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/digital-blueprint/plan-plugin-notifications';
    }
}
