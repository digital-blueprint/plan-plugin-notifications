# Changelog

## [0.1.0]

- Added a project bell menu for switching between **Participating and @mentions** and **All activity**.
- Added task notification controls: **Default**, **Subscribed**, **Unsubscribed**, and **Muted**. Creating a task, being assigned to it, or commenting on it automatically subscribes active project members unless they have opted out. Existing tasks are not backfilled.
- Project watches and task subscriptions cover new tasks, new comments, assignment changes, and closing or reopening tasks.
- Direct mentions in new tasks and comments bypass mute. Ordinary notifications respect task mute, subscriptions, project watches, and existing profile rules, with at most one alert per enabled channel per action.
- Notifications use Plan’s existing web and email channels and respect project membership and comment visibility. Project-wide integrations and scheduled overdue reminders are unchanged.
- Comment and comment-mention emails include a **View comment** link when Plan’s Application URL is configured.

This release targets the Plan fork, not stock Kanboard. SQLite is covered by integration tests. MySQL and PostgreSQL migration paths are included but not yet verified, and external queue workers have not been verified.
