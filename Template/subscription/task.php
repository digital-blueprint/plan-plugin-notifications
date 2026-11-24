<?php if ($notificationPreference !== null): ?>
    <li>
        <form method="post" action="<?= $this->url->href('SubscriptionController', 'task', ['plugin' => 'Notifications', 'task_id' => $task['id'], 'project_id' => $task['project_id']]) ?>">
            <?= $this->form->csrf() ?>
            <label>
                <?= e('Task notifications') ?>
                <?= $this->form->select('task_notification_preference', [
                    'inherit' => t('Inherit'),
                    'subscribe' => t('Subscribe'),
                    'unsubscribe' => t('Unsubscribe'),
                    'mute' => t('Mute'),
                ], ['task_notification_preference' => $notificationPreference]) ?>
            </label>
            <button type="submit" class="btn"><?= e('Save') ?></button>
        </form>
    </li>
<?php endif ?>
