<?php

namespace Kanboard\Plugin\Notifications\Schema;

const VERSION = 1;

function version_1(\PDO $pdo)
{
    foreach (['task' => 'tasks', 'project' => 'projects'] as $scope => $parent) {
        $table = 'plan_notification_'.$scope;
        $pdo->exec("CREATE TABLE IF NOT EXISTS $table (
            subject_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            preference VARCHAR(16) NOT NULL,
            PRIMARY KEY (subject_id, user_id),
            FOREIGN KEY (subject_id) REFERENCES $parent(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
    }
}
