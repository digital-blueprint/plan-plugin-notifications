<?php

namespace Kanboard\Plugin\Notifications\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Plugin\Notifications\Model\SubscriptionModel;

class SubscriptionController extends BaseController
{
    // Project pages that show the watch menu; other pages return to the board.
    private const PROJECT_VIEWS = ['BoardViewController', 'TaskListController', 'ProjectOverviewController'];

    public function task()
    {
        $task = $this->getTask();
        $this->save(SubscriptionModel::TASK, $task['id'], $task['project_id'], [
            SubscriptionModel::INHERIT, SubscriptionModel::SUBSCRIBE, SubscriptionModel::UNSUBSCRIBE, SubscriptionModel::MUTE,
        ]);
        $this->response->redirect($this->helper->url->to('TaskViewController', 'show', ['task_id' => $task['id']]));
    }

    public function project()
    {
        $project = $this->getProject();
        $this->save(SubscriptionModel::PROJECT, $project['id'], $project['id'], [
            SubscriptionModel::INHERIT, SubscriptionModel::WATCH,
        ]);

        $view = $this->request->getStringParam('view');
        $controller = in_array($view, self::PROJECT_VIEWS, true) ? $view : 'BoardViewController';
        $this->response->redirect($this->helper->url->to($controller, 'show', ['project_id' => $project['id']]));
    }

    private function save($table, $subjectId, $projectId, array $allowed)
    {
        $this->checkCSRFParam();

        $userId = $this->userSession->getId();
        $preference = $this->request->getStringParam('preference');
        if (!$this->subscriptionModel->allows($projectId, $userId) || !in_array($preference, $allowed, true)) {
            throw new AccessForbiddenException();
        }

        if (!$this->subscriptionModel->save($table, $subjectId, $userId, $preference)) {
            $this->flash->failure(t('Unable to save your notification preference.'));
        }
    }
}
