# Project plan: Plan Notifications

## Goal

Add GitHub-style project watches and task subscriptions to Plan without creating a separate inbox or email system. Use the `plan-plugin-notifications` name. Target the Plan fork pinned by `plan-docker` first; claim stock Kanboard support only after testing it.

## First release

- Projects: **Default** keeps existing Kanboard preferences; **Watch** opts into supported activity on all tasks.
- Tasks: **Inherit** is the initial state. Creating a task, being assigned to it, or commenting subscribes the user automatically. **Subscribe** also works manually. **Unsubscribe** blocks automatic resubscription but allows inherited alerts; **Mute** blocks both. **Inherit** clears the override. Unassigning someone does not remove their subscription.
- Supported watch events: new tasks, new comments, assignment changes, closing and reopening. Other activity retains its existing personal notification rules, subject to task mute.
- Direct mentions in new task descriptions and comments are delivered even if the recipient muted the task. They do not subscribe anyone and take precedence over ordinary watch notifications for the same action. Use Plan's native mention syntax; notifying on edits that introduce mentions is a follow-up.
- Use the existing in-app and email channel preferences. Do not notify users about their own actions, inactive accounts, or users who no longer belong to the project. Leave project-wide integrations unchanged. Do not backfill old subscriptions.

## Implementation

1. Store per-user project and task preferences using plugin-owned migrations. Add compact dropdown menus to project pages and the task sidebar, using authenticated, CSRF-protected links.
2. Replace the notification-job factory at plugin startup. Resolve personal recipients once, combining existing filters, subscription state and native mention lookup. Keep core channel rendering and project-level delivery. The fork emits separate native mention events after creation; mark those already handled by the combined job so they cannot deliver twice.
3. Validate against the pinned Plan fork. The SQLite integration suite covers opt-out precedence, automatic subscription, assignment with other edits, mention overlap, restricted comments, group access, and both channel settings. SMTP delivery was smoke-tested with Docker GreenMail. Run the migration tests on MySQL and PostgreSQL and verify the external queue path before calling those setups supported.
4. Update the `plan-docker` submodule pin. If using external workers, deploy the same plugin version on the web and worker processes, drain pending jobs before switching versions, and restart workers after deployment.

## Later

Consider newly added mentions on edits, more event filters, a subscription overview, and stock Kanboard support once there is a compatibility matrix. Scheduled overdue reminders use a separate Kanboard path and are not changed by the plugin.
