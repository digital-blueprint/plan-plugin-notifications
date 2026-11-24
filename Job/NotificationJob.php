<?php

namespace Kanboard\Plugin\Notifications\Job;

use Kanboard\Event\GenericEvent;
use Kanboard\Job\NotificationJob as CoreNotificationJob;
use Kanboard\Model\CommentModel;
use Kanboard\Model\TaskModel;
use Kanboard\Plugin\Notifications\Model\SubscriptionModel;

class NotificationJob extends CoreNotificationJob
{
    private const COMBINED_MENTIONS = 'plan_notifications_combined_mentions';

    public function withParams(GenericEvent $event, $eventName)
    {
        if ($eventName === TaskModel::EVENT_CREATE || $eventName === CommentModel::EVENT_CREATE) {
            // Core copies this same event into a separate mention job after dispatch.
            $event[self::COMBINED_MENTIONS] = true;
        }

        return parent::withParams($event, $eventName);
    }

    public function execute(array $eventData, $eventName)
    {
        if (!empty($eventData['mention']) && !empty($eventData[self::COMBINED_MENTIONS])) {
            return;
        }
        unset($eventData[self::COMBINED_MENTIONS]);

        $task = $eventData['task'] ?? [];
        if (empty($task['id']) || empty($task['project_id'])) {
            return;
        }

        $projectId = (int) $task['project_id'];
        $taskId = (int) $task['id'];
        // A queued event from the old project must not deliver after a move or deletion.
        $currentTask = $this->taskFinderModel->getById($taskId);
        if ((int) ($currentTask['project_id'] ?? 0) !== $projectId) {
            return;
        }

        $actor = (int) $this->userSession->getId();
        if (!$actor && $eventName === CommentModel::EVENT_CREATE) {
            $actor = (int) ($eventData['comment']['user_id'] ?? 0);
        }
        if (!$actor && $eventName === TaskModel::EVENT_CREATE) {
            $actor = (int) ($task['creator_id'] ?? 0);
        }

        if (!empty($eventData['mention'])) {
            $userId = (int) $eventData['mention']['id'];
            $users = $this->userNotificationModel->getUsersWithNotificationEnabled($projectId, $actor);
            foreach ($users as $user) {
                if ((int) $user['id'] === $userId) {
                    $this->userNotificationModel->sendUserNotification($user, $eventName, $eventData);
                    break;
                }
            }
            return;
        }

        $assignment = $eventName === TaskModel::EVENT_ASSIGNEE_CHANGE ||
            ($eventName === TaskModel::EVENT_UPDATE && array_key_exists('owner_id', $eventData['changes'] ?? []));
        $this->autoSubscribe($task, $eventData, $eventName, $assignment);

        $watchEvent = $assignment || in_array($eventName, [
            TaskModel::EVENT_CREATE, CommentModel::EVENT_CREATE, TaskModel::EVENT_CLOSE, TaskModel::EVENT_OPEN,
        ], true);
        $taskPreferences = $this->subscriptionModel->getAll(SubscriptionModel::TASK, $taskId);
        $projectPreferences = $watchEvent ? $this->subscriptionModel->getAll(SubscriptionModel::PROJECT, $projectId) : [];
        $mentions = $this->findMentions($eventName, $eventData);
        $mentionEvent = $eventName === TaskModel::EVENT_CREATE ? TaskModel::EVENT_USER_MENTION : CommentModel::EVENT_USER_MENTION;

        foreach ($this->userNotificationModel->getUsersWithNotificationEnabled($projectId, $actor) as $user) {
            $userId = (int) $user['id'];
            if (isset($mentions[$userId])) {
                $this->userNotificationModel->sendUserNotification($user, $mentionEvent, $eventData + ['mention' => $user]);
                continue;
            }

            $taskPreference = $taskPreferences[$userId] ?? SubscriptionModel::INHERIT;
            if ($taskPreference === SubscriptionModel::MUTE) {
                continue;
            }
            $watching = $watchEvent && (
                $taskPreference === SubscriptionModel::SUBSCRIBE ||
                ($projectPreferences[$userId] ?? null) === SubscriptionModel::WATCH
            );
            if ($watching || $this->userNotificationFilterModel->shouldReceiveNotification($user, $eventData)) {
                $this->userNotificationModel->sendUserNotification($user, $eventName, $eventData);
            }
        }

        $this->projectNotificationModel->sendNotifications($projectId, $eventName, $eventData);
    }

    private function autoSubscribe(array $task, array $eventData, $eventName, $assignment)
    {
        $users = [];
        if ($eventName === TaskModel::EVENT_CREATE) {
            $users[] = $task['creator_id'] ?? 0;
            $users[] = $task['owner_id'] ?? 0;
        } elseif ($eventName === CommentModel::EVENT_CREATE) {
            $users[] = $eventData['comment']['user_id'] ?? 0;
        } elseif ($assignment) {
            $users[] = $eventData['changes']['owner_id'] ?? $task['owner_id'] ?? 0;
        }

        foreach (array_unique($users) as $userId) {
            if ($this->subscriptionModel->allows($task['project_id'], $userId) && $this->userModel->isActive($userId)) {
                $this->subscriptionModel->save(SubscriptionModel::TASK, $task['id'], $userId, SubscriptionModel::SUBSCRIBE, true);
            }
        }
    }

    private function findMentions($eventName, array $eventData)
    {
        if ($eventName === TaskModel::EVENT_CREATE) {
            $text = $eventData['task']['description'] ?? '';
        } elseif ($eventName === CommentModel::EVENT_CREATE) {
            $text = $eventData['comment']['comment'] ?? '';
        } else {
            return [];
        }

        // Parse each Plan autocomplete token separately; the native lookup expects
        // a single token at the start of its input.
        preg_match_all('/@[^@\r\n\x{200B}]*\([^()\s]+@[^()\s]+\)\x{200B}/u', $text, $tokens);
        $mentions = [];
        foreach (array_unique($tokens[0] ?? []) as $token) {
            foreach ($this->userMentionJob->getMentionedUsers($token) as $user) {
                $mentions[$user['id']] = true;
            }
        }
        return $mentions;
    }
}
