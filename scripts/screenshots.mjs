import { mkdir, writeFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const [projectId, taskId] = process.argv.slice(2);
if (![projectId, taskId].every(id => /^[1-9]\d*$/.test(id ?? ''))) {
    console.error('Usage: just screenshots <project-id> <task-id>');
    process.exit(1);
}

const baseUrl = 'http://127.0.0.1:7000/';
const output = new URL('../docs/screenshots/', import.meta.url);
const browser = await chromium.launch({ executablePath: process.env.PLAN_SCREENSHOT_CHROMIUM });
try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 840 }, deviceScaleFactor: 2 });
    page.setDefaultTimeout(10_000);

    await page.goto(`${baseUrl}?controller=AuthController&action=login`);
    await page.locator('input[name="username"]').fill(process.env.PLAN_SCREENSHOT_USER ?? 'admin');
    const password = page.locator('input[name="password"]');
    await password.fill(process.env.PLAN_SCREENSHOT_PASSWORD ?? 'admin');
    await password.press('Enter');
    await password.waitFor({ state: 'hidden' }).catch(() => {
        throw new Error('Could not log in to plan-docker. Check PLAN_SCREENSHOT_USER and PLAN_SCREENSHOT_PASSWORD.');
    });

    async function capture(route, selector, contextSelector, rightPadding = 24) {
        await page.goto(baseUrl + route);
        const toggle = page.locator(selector);
        await toggle.click();
        const menu = page.locator('#dropdown .plan-notifications-menu');
        await menu.waitFor();
        await page.mouse.move(0, 0);
        await page.evaluate(() => document.fonts.ready);

        // Start at the toolbar or sidebar heading, and keep the entire open menu in frame.
        const context = await page.locator(contextSelector).boundingBox();
        const trigger = await toggle.boundingBox();
        const popup = await menu.boundingBox();
        const viewport = page.viewportSize();
        const x = Math.max(0, Math.floor(Math.min(context.x, trigger.x, popup.x)) - 12);
        const y = Math.max(0, Math.floor(Math.min(context.y, trigger.y, popup.y)) - 12);
        const right = Math.min(viewport.width, Math.ceil(Math.max(trigger.x + trigger.width, popup.x + popup.width)) + rightPadding);
        const bottom = Math.min(viewport.height, Math.ceil(Math.max(trigger.y + trigger.height, popup.y + popup.height)) + 16);
        const clip = { x, y, width: right - x, height: bottom - y };
        const png = await page.screenshot({ animations: 'disabled', clip });
        // Add a callout to the captured control, then encode the result as WebP.
        const webp = await page.evaluate(async ({ base64, clip, trigger }) => {
            const bytes = Uint8Array.from(atob(base64), char => char.charCodeAt(0));
            const image = await createImageBitmap(new Blob([bytes], { type: 'image/png' }));
            const canvas = document.createElement('canvas');
            canvas.width = image.width;
            canvas.height = image.height;
            const drawing = canvas.getContext('2d');
            drawing.drawImage(image, 0, 0);
            image.close();
            drawing.scale(canvas.width / clip.width, canvas.height / clip.height);
            drawing.strokeStyle = '#2676c7';
            drawing.lineWidth = 2;
            drawing.beginPath();
            drawing.roundRect(trigger.x - clip.x - 3, trigger.y - clip.y - 2, trigger.width + 6, trigger.height + 2, 4);
            drawing.stroke();
            return canvas.toDataURL('image/webp', 1);
        }, { base64: png.toString('base64'), clip, trigger });
        const prefix = 'data:image/webp;base64,';
        if (!webp.startsWith(prefix)) {
            throw new Error('Chromium did not return a WebP image.');
        }
        return Buffer.from(webp.slice(prefix.length), 'base64');
    }

    const project = await capture(
        `?controller=BoardViewController&action=show&project_id=${projectId}`,
        '.plan-notifications-toggle',
        '.project-header',
    );
    const task = await capture(
        `?controller=TaskViewController&action=show&task_id=${taskId}`,
        '.plan-notifications-task .dropdown > a',
        '#task-view .sidebar-title:first-child',
        220,
    );
    await mkdir(output, { recursive: true });
    await writeFile(new URL('project-notifications.webp', output), project);
    await writeFile(new URL('task-notifications.webp', output), task);
    console.log('Saved both menus to docs/screenshots/.');
} finally {
    await browser.close();
}
