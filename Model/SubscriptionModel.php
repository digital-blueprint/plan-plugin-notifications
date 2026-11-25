<?php

namespace Kanboard\Plugin\Notifications\Model;

use Kanboard\Core\Base;

class SubscriptionModel extends Base
{
    const TASK = 'plan_notification_task';
    const PROJECT = 'plan_notification_project';
    const INHERIT = 'inherit';
    const SUBSCRIBE = 'subscribe';
    const UNSUBSCRIBE = 'unsubscribe';
    const MUTE = 'mute';
    const WATCH = 'watch';
    // Profile filter values 1–4 are already used by Plan.
    const PROFILE_NO_TASKS = 5;

    public function allows($projectId, $userId)
    {
        // isMember() includes group roles through ProjectUserRoleModel.
        return $userId > 0 && $this->projectPermissionModel->isMember($projectId, $userId);
    }

    public function get($table, $subjectId, $userId)
    {
        return $this->db->table($table)->eq('subject_id', $subjectId)->eq('user_id', $userId)
            ->findOneColumn('preference') ?: self::INHERIT;
    }

    public function getAll($table, $subjectId)
    {
        $rows = $this->db->table($table)->eq('subject_id', $subjectId)
            ->columns('user_id', 'preference')->findAll();
        return array_column($rows, 'preference', 'user_id');
    }

    public function save($table, $subjectId, $userId, $preference, $automatic = false)
    {
        if ($preference === self::INHERIT) {
            return $this->db->table($table)->eq('subject_id', $subjectId)->eq('user_id', $userId)->remove();
        }

        if (DB_DRIVER === 'mysql') {
            $conflict = $automatic ? 'user_id = user_id' : 'preference = VALUES(preference)';
            $conflict = 'ON DUPLICATE KEY UPDATE '.$conflict;
        } else {
            $conflict = 'ON CONFLICT (subject_id, user_id) DO '.($automatic ? 'NOTHING' : 'UPDATE SET preference = excluded.preference');
        }

        $statement = $this->db->getConnection()->prepare(
            "INSERT INTO $table (subject_id, user_id, preference) VALUES (?, ?, ?) $conflict"
        );
        return $statement->execute([$subjectId, $userId, $preference]);
    }
}
