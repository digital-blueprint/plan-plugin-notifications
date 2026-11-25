# Plan Notifications

GitHub-style task subscriptions and project watches for Plan, a Kanboard fork.

See [the project plan](PROJECT_PLAN.md) for scope, release checks, and follow-up work.

## How notifications work

In your profile’s **Notifications** settings, enable **Web** for in-app alerts, **Email**, or both. Watching, subscribing, and being mentioned do not turn on a delivery channel. Email also requires an email address on your account and a configured mail server.

### Project and task settings

On the board, task list, or overview pages, use the bell menu after the view links (Overview, Board, List, …). A filled bell means you’re watching all activity. On a task page, use **Notifications** in the task sidebar.

| Where | Setting | What it does |
| --- | --- | --- |
| Project bell menu | **Participating and @mentions** (default) | You are not watching the entire project. Task subscriptions, direct mentions, and your profile’s notification rules still apply. |
| Project bell menu | **All activity** (filled bell) | Follow supported activity on every task in this project, even tasks you didn’t create or aren’t assigned to. |
| Task **Notifications** | **Default** | Clear your task-level choice. Project watching and profile rules still apply, and future activity can automatically subscribe you. |
| Task **Notifications** | **Subscribed** | Follow supported activity on this task, even if you don’t watch the project or the task doesn’t match your profile rules. |
| Task **Notifications** | **Unsubscribed** | Stop following this task and prevent automatic resubscription. **You can still get alerts through a project watch or a profile rule.** |
| Task **Notifications** | **Muted** | Stop ordinary task alerts from subscriptions, project watches, and profile rules. It also prevents automatic resubscription. Direct `@mentions` still notify you. |

**Supported activity** for a project watch or task subscription: new tasks, new comments, assignment changes, and closing or reopening a task. Other events, such as ordinary task edits, still follow your existing profile rules unless the task is muted.

### When Plan subscribes you automatically

You automatically subscribe to a task when you **create it**, **are assigned to it**, or **receive a direct `@mention`** in a new task description or a comment you can see, provided you’re an active member of the project (directly or through a group). Posting a comment alone does not subscribe you. Existing tasks are not backfilled when the plugin is installed. Being unassigned does not remove your subscription. **Unsubscribed** and **Muted** are remembered until you change them; choosing **Default** makes automatic subscription possible again.

### Which rule wins?

Plan only sends personal alerts to active project members with an enabled channel. It doesn’t notify you about your own actions or send you a comment you don’t have permission to see.

1. **Direct `@mention`:** Use Plan’s mention picker in a new task description or a new comment. A direct mention bypasses task mute, project watch settings, and profile filters for the alert, but it only subscribes a recipient whose task setting is **Default**. Adding a mention by editing an existing task or comment does not trigger a new mention alert or subscription.
2. **Mute:** If there’s no direct mention, a muted task sends no ordinary personal alert.
3. **Subscription or project watch:** For supported activity, either one is enough. These rules can notify you even if your profile filters only allow tasks assigned to or created by you.
4. **Profile rules:** If none of the above sends an alert or blocks it, Plan checks your usual task and project filters. For example, **Only for tasks assigned to me** still matches an assigned task even after you unsubscribe from it.

If several rules match the same action, you get **one alert per enabled channel**. If that action mentions you, the alert is labeled as a mention. Scheduled overdue reminders and project-wide integrations use separate settings and aren’t changed by these rules.

### Common examples

- **“I’m not watching the project. Why did I get an email?”** If you created the task, were assigned to it, or were mentioned, you may already be subscribed. Check the task sidebar.
- **“I unsubscribed. Why am I still getting emails?”** Check whether you watch the project or have a profile rule that matches the task. Choose **Muted** to stop ordinary alerts for just that task.
- **“Why is there no link to the comment?”** An administrator must set the **Application URL** in Plan’s application settings. Once set, new-comment and comment-mention emails include a **View comment** link that jumps straight to the comment.

## Installation

`plan-docker` mounts this repository as `plugins/Notifications`. For a manual install, clone it into that same case-sensitive directory in the Plan fork. The plugin has no extra PHP dependencies and creates its own tables on startup. SQLite is covered by the integration test; MySQL and PostgreSQL migration paths are provided but not yet verified. Stock Kanboard is not currently supported.

## Development

From an isolated Plan checkout with the plugin mounted or symlinked at `plugins/Notifications`, run:

```sh
php plugins/Notifications/tests/integration.php "$PWD"
```

The test uses an in-memory SQLite database and captures outgoing email after core renders it. To also send through a local test SMTP server, set `PLAN_NOTIFICATIONS_TEST_SMTP_PORT` to its loopback port. For installations with external queue workers, follow the worker rollout instructions in the [project plan](PROJECT_PLAN.md).

## Releasing

1. Set `getPluginVersion()` in `Plugin.php` to the next `X.Y.Z` and add a matching `## [X.Y.Z]` section to `CHANGELOG.md` (optionally `## [X.Y.Z] - YYYY-MM-DD`). Run the integration test against the Plan revision you intend to support.
2. Push the reviewed release commit to the `release` branch. GitHub Actions lints PHP, creates the `vX.Y.Z` tag and release, and uses only that changelog section’s body as the release notes. The attached `Notifications-X.Y.Z.zip` extracts to `Notifications/` for manual installation under Plan’s `plugins/` directory.
3. Update `plan-docker` separately to pin the released commit. Publishing a release does not change that submodule pin.

To preview the ZIP and release notes from the current **committed** snapshot, run `python3 scripts/prepare-release.py /path/to/output`. To retry a failed release, rerun the workflow on the same commit; it won’t move an existing tag or overwrite a published release.
