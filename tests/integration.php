<?php

use Kanboard\Core\Security\Role;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Core\Http\Request;
use Kanboard\Model\CommentModel;
use Kanboard\Model\TaskModel;
use Kanboard\Plugin\Notifications\Controller\SubscriptionController;
use Kanboard\Plugin\Notifications\Job\NotificationJob;
use Kanboard\Plugin\Notifications\Model\SubscriptionModel as Preferences;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$core = realpath($argv[1] ?? '');
if (!$core || !is_file($core.'/app/common.php') || !is_file($core.'/plugins/Notifications/Plugin.php')) {
    throw new RuntimeException('Pass a Plan checkout with the plugin installed at plugins/Notifications.');
}

define('DB_DRIVER', 'sqlite');
define('DB_FILENAME', ':memory:');
$smtpPort = getenv('PLAN_NOTIFICATIONS_TEST_SMTP_PORT');
if ($smtpPort) {
    define('MAIL_TRANSPORT', 'smtp');
    define('MAIL_SMTP_HOSTNAME', '127.0.0.1');
    define('MAIL_SMTP_PORT', (int) $smtpPort);
}
require $core.'/app/common.php';

function same($expected, $actual, $label)
{
    if ($expected !== $actual) {
        throw new RuntimeException($label.': expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
}

same(NotificationJob::class, get_class($container['notificationJob']), 'notification job registered by the plugin loader');
$email = new class {
    public $messages = [];
    public $transport;

    public function send($address, $name, $subject, $body)
    {
        $this->messages[$address][] = [$subject, $body];
        if ($this->transport !== null) {
            $this->transport->send($address, $name, $subject, $body);
        }
    }

    public function getTransport($name)
    {
        return $this->transport->getTransport($name);
    }
};
if ($smtpPort) {
    $email->transport = $container['emailClient'];
    unset($container['emailClient']);
}
$container['emailClient'] = $email;

$users = [];
foreach (['author', 'watcher', 'muted', 'other', 'assignee', 'groupwatcher', 'legacy', 'mentioned', 'silent'] as $name) {
    $user = $container['userModel']->create([
        'username' => $name,
        'name' => ucfirst($name),
        'email' => $name.'@example.test',
        'role' => $name === 'author' ? Role::APP_ADMIN : Role::APP_USER,
    ]);
    $users[$name] = $user;
    $profileFilter = in_array($name, ['assignee', 'groupwatcher', 'mentioned'], true)
        ? (string) Preferences::PROFILE_NO_TASKS : ($name === 'legacy' ? 1 : 4);
    $container['userNotificationModel']->saveSettings($user, [
        'notification_types' => $name === 'silent' ? [] : ['web' => true] + ($name === 'watcher' ? ['email' => true] : []),
        'notifications_filter' => $profileFilter,
    ]);
}

$project = $container['projectModel']->create(['name' => 'Notification integration'], $users['author'], true);
foreach (['watcher', 'muted', 'assignee', 'legacy', 'mentioned', 'silent'] as $name) {
    $container['projectUserRoleModel']->addUser($project, $users[$name], Role::PROJECT_MEMBER);
}
$group = $container['groupModel']->create('Notification group');
$container['groupMemberModel']->addUser($group, $users['groupwatcher']);
$container['projectGroupRoleModel']->addGroup($project, $group, Role::PROJECT_VIEWER);
$container['userSession']->initialize($container['userModel']->getById($users['author']));
$baseUrl = 'http://127.0.0.1:7000/';
$container['configModel']->save(['application_url' => $baseUrl]);
$container['memoryCache']->flush();

$prefs = $container['subscriptionModel'];
foreach (['author', 'watcher', 'muted', 'other', 'groupwatcher'] as $name) {
    $prefs->save(Preferences::PROJECT, $project, $users[$name], Preferences::WATCH);
}
$unread = $container['userUnreadNotificationModel'];
$events = function ($name) use ($unread, $users) {
    return array_column($unread->getAll($users[$name]), 'event_name');
};
$clear = function () use ($unread, $users, $email) {
    foreach ($users as $user) {
        $unread->markAllAsRead($user);
    }
    $email->messages = [];
};

$task = $container['taskCreationModel']->create([
    'project_id' => $project,
    'title' => 'Notification test task',
    'owner_id' => $users['legacy'],
    'description' => "@Watcher (watcher@example.test)\u{200B} please review with @Silent (silent@example.test)\u{200B} and @Other (other@example.test)\u{200B}",
]);
same(Preferences::SUBSCRIBE, $prefs->get(Preferences::TASK, $task, $users['author']), 'creator automatically subscribed');
same(Preferences::SUBSCRIBE, $prefs->get(Preferences::TASK, $task, $users['legacy']), 'initial assignee automatically subscribed');
same(Preferences::SUBSCRIBE, $prefs->get(Preferences::TASK, $task, $users['watcher']), 'task mention automatically subscribes');
same(Preferences::SUBSCRIBE, $prefs->get(Preferences::TASK, $task, $users['silent']), 'mention subscribes even without a delivery channel');
same(Preferences::INHERIT, $prefs->get(Preferences::TASK, $task, $users['other']), 'mention does not subscribe a nonmember');
same([TaskModel::EVENT_USER_MENTION], $events('watcher'), 'native task mention and watch coalesce');
same([TaskModel::EVENT_CREATE], $events('groupwatcher'), 'project watch works with No tasks by default, including group viewers');
same([TaskModel::EVENT_CREATE], $events('legacy'), 'initial assignee gets one notification');
same([], $events('author'), 'no self notification even when watching');
same([], $events('other'), 'no delivery without membership');
same([], $events('silent'), 'subscription does not enable a delivery channel');
same(1, count($email->messages['watcher@example.test'] ?? []), 'one notification email');
$clear();

$prefs->save(Preferences::TASK, $task, $users['muted'], Preferences::MUTE);
$prefs->save(Preferences::TASK, $task, $users['legacy'], Preferences::UNSUBSCRIBE);
$comment = function ($body, $name = 'author', $visibility = Role::APP_USER) use ($container, $task, $users) {
    $container['userSession']->initialize($container['userModel']->getById($users[$name]));
    return $container['commentModel']->create([
        'task_id' => $task, 'user_id' => $users[$name], 'comment' => $body, 'visibility' => $visibility,
    ]);
};
$expectCommentLink = function ($commentId, $label) use ($email, $task, $project, $baseUrl) {
    $body = $email->messages['watcher@example.test'][0][1] ?? '';
    $taskUrl = $baseUrl.'?controller=TaskViewController&amp;action=show&amp;task_id='.$task;
    $boardUrl = $baseUrl.'?controller=BoardViewController&amp;action=show&amp;project_id='.$project;
    same(true, strpos($body, 'href="'.$taskUrl.'#comment-'.$commentId.'"') !== false, $label);
    same(true, strpos($body, 'href="'.$taskUrl.'"') !== false, 'task link is preserved');
    same(true, strpos($body, 'href="'.$boardUrl.'"') !== false, 'board link is preserved');
};
$mentionId = $comment("Hello @Watcher (watcher@example.test)\u{200B}, @Muted (muted@example.test)\u{200B}, @Mentioned (mentioned@example.test)\u{200B}, and @Legacy (legacy@example.test)\u{200B}");
same([CommentModel::EVENT_USER_MENTION], $events('watcher'), 'overlapping subscriptions and inline mention coalesce');
same([CommentModel::EVENT_USER_MENTION], $events('muted'), 'mute does not block mention');
same(Preferences::MUTE, $prefs->get(Preferences::TASK, $task, $users['muted']), 'mention preserves mute');
same([CommentModel::EVENT_USER_MENTION], $events('legacy'), 'unsubscribe does not block mention');
same(Preferences::UNSUBSCRIBE, $prefs->get(Preferences::TASK, $task, $users['legacy']), 'mention preserves unsubscribe');
same([CommentModel::EVENT_USER_MENTION], $events('mentioned'), 'mention sends one alert with No tasks by default');
same(Preferences::SUBSCRIBE, $prefs->get(Preferences::TASK, $task, $users['mentioned']), 'comment mention automatically subscribes');
same(1, count($email->messages['watcher@example.test'] ?? []), 'overlap sends a single email');
same(true, strpos($email->messages['watcher@example.test'][0][0], 'mentioned') !== false, 'email describes the mention');
$expectCommentLink($mentionId, 'mention email links to its comment');
$clear();

$comment("@Watcher (watcher@example.test)\u{200B} please review");
same([CommentModel::EVENT_USER_MENTION], $events('watcher'), 'separate native comment mention is suppressed');
$clear();

$comment("Restricted @Watcher (watcher@example.test)\u{200B} and @Assignee (assignee@example.test)\u{200B}", 'author', Role::APP_ADMIN);
same([], $events('watcher'), 'no restricted comment notification despite watch and mention');
same([], $email->messages, 'no restricted comment email');
same(Preferences::INHERIT, $prefs->get(Preferences::TASK, $task, $users['assignee']), 'a hidden mention does not subscribe the recipient');
$clear();

$commentId = $comment('Ordinary comment', 'assignee');
same(Preferences::INHERIT, $prefs->get(Preferences::TASK, $task, $users['assignee']), 'commenting alone does not subscribe');
same([CommentModel::EVENT_CREATE], $events('mentioned'), 'a mention subscriber gets later activity without a project watch');
same([CommentModel::EVENT_CREATE], $events('legacy'), 'unsubscribe preserves the existing all-tasks filter');
same([], $events('muted'), 'mute blocks ordinary activity');
$expectCommentLink($commentId, 'new comment email links to its comment');
$clear();

$comment("@Assignee (assignee@example.test)\u{200B} noting this for myself", 'assignee');
same(Preferences::INHERIT, $prefs->get(Preferences::TASK, $task, $users['assignee']), 'self-mention does not subscribe');
$clear();

$container['userSession']->initialize($container['userModel']->getById($users['author']));
same(true, $container['taskModificationModel']->update([
    'id' => $task, 'owner_id' => $users['assignee'], 'title' => 'Updated title and assignment',
]), 'assignment with other edits');
same(Preferences::SUBSCRIBE, $prefs->get(Preferences::TASK, $task, $users['assignee']), 'assignee automatically subscribed');
same([TaskModel::EVENT_UPDATE], $events('assignee'), 'assignment with other edits is notified once with No tasks by default');
$clear();

$prefs->save(Preferences::TASK, $task, $users['assignee'], Preferences::UNSUBSCRIBE);
$comment('An update after the assignee unsubscribed');
same([], $events('assignee'), 'No tasks by default does not fall back to ordinary alerts for assigned tasks');
$prefs->save(Preferences::TASK, $task, $users['assignee'], Preferences::SUBSCRIBE);
$clear();

$container['groupMemberModel']->removeUser($group, $users['groupwatcher']);
$container['userNotificationModel']->saveSettings($users['watcher'], [
    'notification_types' => ['email' => true], 'notifications_filter' => 4,
]);
$container['taskStatusModel']->close($task);
same([TaskModel::EVENT_CLOSE], $events('assignee'), 'task subscription alone gets close');
same([], $events('watcher'), 'email-only has no unread entry');
same(1, count($email->messages['watcher@example.test'] ?? []), 'email-only gets close notification');
same([], $events('groupwatcher'), 'a removed group member no longer receives updates');

$taskData = $container['taskFinderModel']->getDetails($task);
$projectData = $container['projectModel']->getById($project);
$template = $container['template'];
$profileSettings = $container['userNotificationModel']->readSettings($users['assignee']);
same(Preferences::PROFILE_NO_TASKS, (int) $profileSettings['notifications_filter'], 'the existing profile save path persists No tasks by default');
$profileForm = $template->render('user_view/notifications', [
    'user' => $container['userModel']->getById($users['assignee']),
    'types' => $container['userNotificationTypeModel']->getTypes(),
    'notifications' => $profileSettings,
    'filters' => $container['userNotificationFilterModel']->getFilters(),
    'projects' => [],
]);
same(true, strpos($profileForm, 'No tasks by default') !== false, 'profile form renders the new option');
same(true, preg_match('/name="notifications_filter"[^>]*value="'.Preferences::PROFILE_NO_TASKS.'"[^>]*checked="checked"/', $profileForm) === 1, 'saved profile option renders as selected');
same(true, strpos($profileForm, 'All tasks') !== false, 'the existing profile options remain available');
$renderTask = function () use ($template, $taskData) {
    return $template->hook->render('template:task:sidebar:information', ['task' => $taskData]);
};
$renderProject = function () use ($template, $projectData) {
    return $template->hook->render('template:project-header:view-switcher', ['project' => $projectData, 'filters' => ['search' => '']]);
};
same(true, strpos($renderTask(), 'preference=mute') !== false, 'task menu renders');
same(true, strpos($renderTask(), 'csrf_token=') !== false, 'task menu links carry a CSRF token');
same(false, strpos($renderProject(), 'plan-notifications'), 'project menu waits for bootstrap');
// Bootstrap twice, as a queue worker does for consecutive jobs.
$container['dispatcher']->dispatch(new Symfony\Contracts\EventDispatcher\Event(), 'app.bootstrap');
$container['dispatcher']->dispatch(new Symfony\Contracts\EventDispatcher\Event(), 'app.bootstrap');
$projectMenu = $renderProject();
same(true, strpos($projectMenu, 'preference=watch') !== false, 'project menu renders');
same(1, substr_count($projectMenu, 'class="plan-notifications"'), 'project menu is attached once');
same(true, strpos($projectMenu, '<li class="plan-notifications"') > strrpos($projectMenu, 'class="view-'), 'project menu follows other view links');

// Capture redirects instead of exiting the test process.
unset($container['response']);
$response = new class {
    public $url = null;

    public function redirect($url)
    {
        $this->url = $url;
    }
};
$container['response'] = $response;
$request = function (array $query) use ($container) {
    unset($container['request']);
    $container['request'] = new Request($container, ['REQUEST_METHOD' => 'GET'], $query + ['plugin' => 'Notifications']);
};
$request(['task_id' => $task, 'project_id' => $project]);
same(false, strpos($renderProject(), 'plan-notifications'), 'project control is hidden on task pages');
same(true, strpos($renderTask(), 'preference=mute') !== false, 'task control remains available on task pages');

$controller = new SubscriptionController($container);
$token = $container['token']->getCSRFToken();
$rejected = [
    'missing CSRF token' => ['task_id' => $task, 'preference' => Preferences::MUTE],
    'invalid CSRF token' => ['task_id' => $task, 'preference' => Preferences::MUTE, 'csrf_token' => 'invalid'],
    'unknown preference' => ['task_id' => $task, 'preference' => Preferences::WATCH, 'csrf_token' => $token],
];
foreach ($rejected as $label => $query) {
    $request($query);
    try {
        $controller->task();
        throw new RuntimeException('Invalid save should have been forbidden: '.$label);
    } catch (AccessForbiddenException $e) {
        same(null, $response->url, 'invalid save: '.$label);
    }
}
$request(['task_id' => $task, 'preference' => Preferences::MUTE, 'csrf_token' => $token]);
$controller->task();
same(true, strpos((string) $response->url, 'task_id='.$task) !== false, 'task save returns to the task');
same(Preferences::MUTE, $prefs->get(Preferences::TASK, $task, $users['author']), 'CSRF-protected link saves task preference');

$request(['project_id' => $project, 'preference' => Preferences::WATCH, 'view' => 'TaskListController', 'csrf_token' => $token]);
$controller->project();
same(true, strpos((string) $response->url, 'TaskListController') !== false, 'project save returns to the current view');
same(Preferences::WATCH, $prefs->get(Preferences::PROJECT, $project, $users['author']), 'CSRF-protected link saves project preference');
$request(['project_id' => $project, 'preference' => Preferences::INHERIT, 'view' => 'https://example.test/', 'csrf_token' => $token]);
$controller->project();
same(true, strpos((string) $response->url, 'BoardViewController') !== false, 'unknown views return to the board');

session_write_close();
echo "Plan Notifications integration checks passed.\n";
