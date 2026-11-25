<html>
<body>
<h2><?= $this->text->e($task['title']) ?> (#<?= $task['id'] ?>)</h2>

<?php if (! empty($comment['username'])): ?>
    <h3><?= e('New comment posted by %s', $comment['name'] ?: $comment['username']) ?></h3>
<?php else: ?>
    <h3><?= e('New comment') ?></h3>
<?php endif ?>

<?= $this->text->markdown($comment['comment'], true) ?>

<?= $this->render('Notifications:notification/comment_link', ['task' => $task, 'comment' => $comment]) ?>
<?= $this->render('notification/footer', ['task' => $task]) ?>
</body>
</html>
