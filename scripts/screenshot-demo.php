<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

// The runner supplies a fresh data directory and runs this from the staged Plan root.
$dataDir = getenv('DATA_DIR');
if (!$dataDir || !is_dir($dataDir) || file_exists($dataDir.'/db.sqlite')) {
    throw new RuntimeException('Screenshot fixtures require a fresh data directory.');
}

require getcwd().'/app/common.php';

$admin = $container['userModel']->getByUsername('admin');
$container['userSession']->initialize($admin);
$project = $container['projectModel']->create(['name' => 'Website refresh'], $admin['id'], true);
if (!$project) {
    throw new RuntimeException('Could not create the demo project.');
}

$task = $container['taskCreationModel']->create([
    'project_id' => $project,
    'title' => 'Review the homepage',
    'description' => 'Check the copy and links before launch.',
]);
$otherTask = $container['taskCreationModel']->create([
    'project_id' => $project,
    'title' => 'Update the navigation',
    'color_id' => 'blue',
]);

// Creating the task auto-subscribes the demo user. Reset it to show “Default” in the screenshot.
$preferences = $container['subscriptionModel'];
if (!$task || !$otherTask || !$preferences->save($preferences::TASK, $task, $admin['id'], $preferences::INHERIT)) {
    throw new RuntimeException('Could not seed the demo tasks.');
}

echo json_encode(['projectId' => $project, 'taskId' => $task], JSON_THROW_ON_ERROR), PHP_EOL;
