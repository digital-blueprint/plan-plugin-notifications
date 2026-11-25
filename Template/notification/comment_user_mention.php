<html>
<body>
<h2><?= e('You were mentioned in a comment on the task #%d', $task['id']) ?></h2>

<p><?= $this->text->e($task['title']) ?></p>

<?= $this->text->markdown($comment['comment'], true) ?>

<?= $this->render('Notifications:notification/comment_link', ['task' => $task, 'comment' => $comment]) ?>
<?= $this->render('notification/footer', ['task' => $task]) ?>
</body>
</html>
