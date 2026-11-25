# Capture the project and task notification menus from the running plan-docker stack.
screenshots project_id task_id:
    node scripts/screenshots.mjs {{quote(project_id)}} {{quote(task_id)}}
