<?php

use Kanboard\Plugin\Notifications\Model\SubscriptionModel;

$filters[SubscriptionModel::PROFILE_NO_TASKS] = t('No tasks by default');

// Render the core form with the extra option, without overriding another plugin's filter model.
echo $this->render('kanboard:user_view/notifications', compact('user', 'types', 'notifications', 'filters', 'projects'));
