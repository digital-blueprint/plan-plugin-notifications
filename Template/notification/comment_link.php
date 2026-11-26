<?php if ($this->app->config('application_url') && !empty($task['id']) && !empty($comment['id'])): ?>
    <p><a href="<?= $this->url->href('TaskViewController', 'show', ['task_id' => $task['id']], false, 'comment-'.(int) $comment['id'], true) ?>"><?= $this->text->e($label) ?></a></p>
<?php endif ?>
