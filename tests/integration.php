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
foreach (['author', 'watcher', 'muted', 'other', 'assignee', 'groupwatcher', 'legacy'] as $name) {
    $user = $container['userModel']->create([
        'username' => $name,
        'name' => ucfirst($name),
        'email' => $name.'@example.test',
        'role' => $name === 'author' ? Role::APP_ADMIN : Role::APP_USER,
    ]);
    $users[$name] = $user;
    $container['userNotificationModel']->saveSettings($user, [
        'notification_types' => ['web' => true] + ($name === 'watcher' ? ['email' => true] : []),
        'notifications_filter' => $name === 'legacy' ? 1 : 4,
    ]);
}

$project = $container['projectModel']->create(['name' => 'Notification integration'], $users['author'], true);
foreach (['watcher', 'muted', 'assignee', 'legacy'] as $name) {
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
    'description' => "@Watcher (watcher@example.test)\u{200B} please review",
]);
same(Preferences::SUBSCRIBE, $prefs->get(Preferences::TASK, $task, $users['author']), 'creator automatically subscribed');
same(Preferences::INHERIT, $prefs->get(Preferences::TASK, $task, $users['watcher']), 'mention does not subscribe');
same([TaskModel::EVENT_USER_MENTION], $events('watcher'), 'native task mention and watch coalesce');
same([TaskModel::EVENT_CREATE], $events('groupwatcher'), 'group viewer can watch');
same([TaskModel::EVENT_CREATE], $events('legacy'), 'default preserves the existing all-tasks filter');
same([], $events('author'), 'no self notification even when watching');
same([], $events('other'), 'no delivery without membership');
same(1, count($email->messages['watcher@example.test'] ?? []), 'one notification email');
$clear();

$prefs->save(Preferences::TASK, $task, $users['muted'], Preferences::MUTE);
$prefs->save(Preferences::TASK, $task, $users['watcher'], Preferences::SUBSCRIBE);
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
$mentionId = $comment("Hello @Watcher (watcher@example.test)\u{200B} and @Muted (muted@example.test)\u{200B}");
same([CommentModel::EVENT_USER_MENTION], $events('watcher'), 'overlapping subscriptions and inline mention coalesce');
same([CommentModel::EVENT_USER_MENTION], $events('muted'), 'mute does not block mention');
same(1, count($email->messages['watcher@example.test'] ?? []), 'overlap sends a single email');
same(true, strpos($email->messages['watcher@example.test'][0][0], 'mentioned') !== false, 'email describes the mention');
$expectCommentLink($mentionId, 'mention email links to its comment');
$clear();

$comment("@Watcher (watcher@example.test)\u{200B} please review");
same([CommentModel::EVENT_USER_MENTION], $events('watcher'), 'separate native comment mention is suppressed');
$clear();

$comment("Restricted @Watcher (watcher@example.test)\u{200B}", 'author', Role::APP_ADMIN);
same([], $events('watcher'), 'no restricted comment notification despite watch and mention');
same([], $email->messages, 'no restricted comment email');
$clear();

$commentId = $comment('Ordinary comment', 'assignee');
same(Preferences::SUBSCRIBE, $prefs->get(Preferences::TASK, $task, $users['assignee']), 'commenter automatically subscribed');
same([], $events('muted'), 'mute blocks ordinary activity');
$expectCommentLink($commentId, 'new comment email links to its comment');
$clear();

$prefs->save(Preferences::TASK, $task, $users['assignee'], Preferences::UNSUBSCRIBE);
$comment('Do not resubscribe me', 'assignee');
same(Preferences::UNSUBSCRIBE, $prefs->get(Preferences::TASK, $task, $users['assignee']), 'unsubscribe blocks resubscription on comment');
$clear();

$prefs->save(Preferences::TASK, $task, $users['assignee'], Preferences::INHERIT);
$container['userSession']->initialize($container['userModel']->getById($users['author']));
same(true, $container['taskModificationModel']->update([
    'id' => $task, 'owner_id' => $users['assignee'], 'title' => 'Updated title and assignment',
]), 'assignment with other edits');
same(Preferences::SUBSCRIBE, $prefs->get(Preferences::TASK, $task, $users['assignee']), 'assignee automatically subscribed');
same([TaskModel::EVENT_UPDATE], $events('assignee'), 'assignment with other edits is notified once');
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
same(true, strpos($template->hook->render('template:task:sidebar:information', ['task' => $taskData]), 'task_notification_preference') !== false, 'task control renders');
same(true, strpos($template->hook->render('template:project:header:after', ['project' => $projectData]), 'project_notification_preference') !== false, 'project control renders');

// Capture redirects instead of exiting the test process.
unset($container['response']);
$response = new class {
    public $redirected = false;

    public function redirect($url)
    {
        $this->redirected = true;
    }
};
$container['response'] = $response;
$request = function ($method, array $body) use ($container, $task, $project) {
    unset($container['request']);
    $container['request'] = new Request($container, ['REQUEST_METHOD' => $method], ['task_id' => $task, 'project_id' => $project], $body);
};
$controller = new SubscriptionController($container);
$validBody = [
    'csrf_token' => $container['token']->getCSRFToken(),
    'task_notification_preference' => Preferences::MUTE,
];
foreach (['GET' => $validBody, 'POST' => ['task_notification_preference' => Preferences::MUTE]] as $method => $body) {
    $request($method, $body);
    try {
        $controller->task();
        throw new RuntimeException('Invalid save should have been forbidden: '.$method);
    } catch (AccessForbiddenException $e) {
        same(false, $response->redirected, 'invalid save: '.$method);
    }
}
$request('POST', $validBody);
$controller->task();
same(true, $response->redirected, 'successful form save redirects');
same(Preferences::MUTE, $prefs->get(Preferences::TASK, $task, $users['author']), 'authenticated CSRF-protected POST saves preference');

session_write_close();
echo "Plan Notifications integration checks passed.\n";
