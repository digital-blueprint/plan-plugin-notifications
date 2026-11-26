<ul class="plan-notifications-menu">
    <?php if ($notificationChannelsOff): ?>
        <li class="plan-notifications-warning no-hover">
            <i class="fa fa-fw fa-exclamation-triangle" aria-hidden="true"></i>
            <?= e('Email and web notifications are off.') ?>
            <?= $this->url->link(t('Open notification settings'), 'UserViewController', 'notifications', ['user_id' => $this->user->getId()]) ?>
        </li>
    <?php endif ?>
    <?php foreach ($options as $value => $option): ?>
        <li class="<?= $value === $current ? 'plan-notifications-selected' : '' ?>">
            <a href="<?= $this->url->href('SubscriptionController', $action, $params + ['plugin' => 'Notifications', 'preference' => $value], true) ?>">
                <i class="fa fa-fw <?= $value === $current ? 'fa-check' : '' ?>" aria-hidden="true"></i>
                <span>
                    <strong><?= $this->text->e($option['label']) ?></strong>
                    <span class="plan-notifications-help"><?= $this->text->e($option['help']) ?></span>
                </span>
            </a>
        </li>
    <?php endforeach ?>
</ul>
