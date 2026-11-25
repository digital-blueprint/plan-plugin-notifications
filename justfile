# Create an isolated demo board and task, then refresh both README screenshots.
screenshots:
    npm ci --prefix scripts
    node scripts/screenshots.mjs

# Capture the menus from an existing Plan instance instead.
screenshots-existing project_id task_id:
    node scripts/screenshots.mjs {{quote(project_id)}} {{quote(task_id)}}
