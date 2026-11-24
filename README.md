# Plan Notifications

GitHub-style task subscriptions and project watches for Plan, a Kanboard fork.

See [the project plan](PROJECT_PLAN.md) for scope, release checks, and follow-up work.

## Using the plugin

- Choose **Watch** in the project header to follow task creation, new comments, assignment changes, and closing/reopening. **Default** keeps your existing preferences.
- Tasks you create, are assigned to, or comment on are automatically subscribed. Use the task sidebar to subscribe manually, unsubscribe, mute, or return to inherit. Unsubscribe blocks automatic resubscription but still allows inherited activity alerts. Mute also blocks ordinary task event notifications.
- Use Plan's `@mention` picker in a new task description or comment. Mentions respect project and comment permissions, bypass mute, and don't subscribe the recipient. A mention and a watch for the same action yield a single alert per enabled channel.
- Turn on **Web**, **Email**, or both in your profile's **Notifications** settings. Email requires a configured mail transport.

## Installation

`plan-docker` mounts this repository as `plugins/Notifications`. For a manual install, clone it into that same case-sensitive directory in the Plan fork. The plugin has no extra PHP dependencies and creates its own tables on startup. SQLite is covered by the integration test; MySQL and PostgreSQL migration paths are provided but not yet verified. Stock Kanboard is not currently supported.

## Development

From an isolated Plan checkout with the plugin mounted or symlinked at `plugins/Notifications`, run:

```sh
php plugins/Notifications/tests/integration.php "$PWD"
```

The test uses an in-memory SQLite database and captures outgoing email after core renders it. To also send through a local test SMTP server, set `PLAN_NOTIFICATIONS_TEST_SMTP_PORT` to its loopback port. For installations with external queue workers, follow the worker rollout instructions in the [project plan](PROJECT_PLAN.md).
