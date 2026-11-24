<?php

namespace Kanboard\Plugin\Notifications\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Plugin\Notifications\Model\SubscriptionModel;

class SubscriptionController extends BaseController
{
    public function task()
    {
        $task = $this->getTask();
        $this->save(SubscriptionModel::TASK, $task['id'], $task['project_id'], 'task_notification_preference', [
            SubscriptionModel::INHERIT, SubscriptionModel::SUBSCRIBE, SubscriptionModel::UNSUBSCRIBE, SubscriptionModel::MUTE,
        ]);
        $this->response->redirect($this->helper->url->to('TaskViewController', 'show', ['task_id' => $task['id']]));
    }

    public function project()
    {
        $project = $this->getProject();
        $this->save(SubscriptionModel::PROJECT, $project['id'], $project['id'], 'project_notification_preference', [
            SubscriptionModel::INHERIT, SubscriptionModel::WATCH,
        ]);
        $this->response->redirect($this->helper->url->to('ProjectViewController', 'show', ['project_id' => $project['id']]));
    }

    private function save($table, $subjectId, $projectId, $field, array $allowed)
    {
        $userId = $this->userSession->getId();
        if (!$this->request->isPost() || !$this->subscriptionModel->allows($projectId, $userId)) {
            throw new AccessForbiddenException();
        }

        $this->checkCSRFForm();
        $preference = $this->request->getRawValue($field);
        if (!in_array($preference, $allowed, true)) {
            throw new AccessForbiddenException();
        }

        if (!$this->subscriptionModel->save($table, $subjectId, $userId, $preference)) {
            $this->flash->failure(t('Unable to save your notification preference.'));
        }
    }
}
