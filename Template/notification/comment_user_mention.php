<html>
<body>
<h2><?= t('You were mentioned in a comment on the task #%d', $task['id']) ?></h2>

<p><?= $this->text->e($task['title']) ?></p>

<?= $this->text->markdown($comment['comment'], true) ?>

<?= $this->render('Notifications:notification/comment_link', ['task' => $task, 'comment' => $comment, 'label' => $plan_notifications_comment_link_label ?? t('View comment')]) ?>
<?= $this->render('notification/footer', ['task' => $task]) ?>
</body>
</html>
