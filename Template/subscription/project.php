<?php if ($notificationPreference !== null): ?>
    <form method="post" action="<?= $this->url->href('SubscriptionController', 'project', ['plugin' => 'Notifications', 'project_id' => $project['id']]) ?>">
        <?= $this->form->csrf() ?>
        <label>
            <?= e('Project notifications') ?>
            <?= $this->form->select('project_notification_preference', [
                'inherit' => t('Default'),
                'watch' => t('Watch'),
            ], ['project_notification_preference' => $notificationPreference]) ?>
        </label>
        <button type="submit" class="btn"><?= e('Save') ?></button>
    </form>
<?php endif ?>
