# Plan Notifications

GitHub-style task subscriptions and project watches for Plan, a Kanboard fork.

This repository is the starting point for the plugin. It does not yet change Plan's notifications.

## Planned behavior

- Watch all task activity in a project, or keep your existing notification preferences.
- Automatically subscribe to tasks you create, are assigned to, or comment on. Unsubscribing stops automatic resubscription; muting also blocks inherited activity notifications.
- Notify users about direct mentions even when they do not watch the task or have muted it, provided they have access and have enabled a delivery channel. Mentions do not automatically subscribe users.
- Reuse Kanboard's in-app and email delivery. Overlapping watches and mentions should produce one notification per enabled channel.

## Development

The Plan Docker setup mounts this repository as `plugins/Notifications`. The intended integration point is Kanboard's notification-job service, which the built-in event subscriber uses before enqueuing deliveries. In the Plan fork, ordinary activity and `@mention` events are emitted separately, so the implementation needs to coordinate both paths to avoid duplicate deliveries.
