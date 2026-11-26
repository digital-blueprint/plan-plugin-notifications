<?php if ($notificationPreference !== null): ?>
    <?php
    $watching = $notificationPreference === 'watch';
    $title = $watching ? t('Project notifications: All activity') : t('Project notifications: Participating and @mentions');
    ?>
    <li class="plan-notifications">
        <div class="dropdown">
            <a href="#" class="dropdown-menu plan-notifications-toggle" title="<?= $this->text->e($title) ?>" aria-label="<?= $this->text->e($title) ?>">
                <i class="fa fa-fw <?= $watching ? 'fa-bell' : 'fa-bell-o' ?>" aria-hidden="true"></i><i class="fa fa-caret-down" aria-hidden="true"></i>
            </a>
            <?= $this->render('Notifications:subscription/menu', [
                'action' => 'project',
                'params' => ['project_id' => $project['id'], 'view' => $this->app->getRouterController()],
                'current' => $notificationPreference,
                'notificationChannelsOff' => $notificationChannelsOff,
                'options' => [
                    'inherit' => [
                        'label' => t('Participating and @mentions'),
                        'help' => t('Only tasks you follow, your profile notification rules, and direct mentions.'),
                    ],
                    'watch' => [
                        'label' => t('All activity'),
                        'help' => t('New tasks, comments, assignments, and closing or reopening on every task.'),
                    ],
                ],
            ]) ?>
        </div>
    </li>
<?php endif ?>
