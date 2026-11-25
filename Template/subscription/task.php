<?php if ($notificationPreference !== null): ?>
    <?php
    $options = [
        'subscribe' => [
            'label' => t('Subscribed'),
            'help' => t('New comments, assignment changes, and closing or reopening this task.'),
        ],
        'inherit' => [
            'label' => t('Default'),
            'help' => t('Use your project watch and profile rules. Creating, being assigned to, or commenting on this task subscribes you.'),
        ],
        'unsubscribe' => [
            'label' => t('Unsubscribed'),
            'help' => t('Stop following this task. Project watches and profile rules still apply.'),
        ],
        'mute' => [
            'label' => t('Muted'),
            'help' => t('Only direct @mentions.'),
        ],
    ];
    $icons = ['subscribe' => 'fa-bell', 'inherit' => 'fa-bell-o', 'unsubscribe' => 'fa-bell-o', 'mute' => 'fa-bell-slash'];
    ?>
    <li class="plan-notifications-task">
        <div class="dropdown">
            <a href="#" class="dropdown-menu" title="<?= e('Task notifications') ?>">
                <i class="fa fa-fw <?= $icons[$notificationPreference] ?>" aria-hidden="true"></i><?= e('Notifications') ?>:
                <strong><?= $this->text->e($options[$notificationPreference]['label']) ?></strong>
                <i class="fa fa-caret-down" aria-hidden="true"></i>
            </a>
            <?= $this->render('Notifications:subscription/menu', [
                'action' => 'task',
                'params' => ['task_id' => $task['id'], 'project_id' => $task['project_id']],
                'current' => $notificationPreference,
                'options' => $options,
            ]) ?>
        </div>
    </li>
<?php endif ?>
