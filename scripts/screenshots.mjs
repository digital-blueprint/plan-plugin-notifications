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

    async function capture(route, selector) {
        await page.goto(baseUrl + route);
        await page.locator(selector).click();
        const menu = page.locator('#dropdown .plan-notifications-menu');
        await menu.waitFor();
        await page.mouse.move(0, 0);
        const png = await menu.screenshot({ animations: 'disabled' });
        // Playwright captures PNG; Chromium can encode it as WebP without another dependency.
        const webp = await page.evaluate(async base64 => {
            const bytes = Uint8Array.from(atob(base64), char => char.charCodeAt(0));
            const image = await createImageBitmap(new Blob([bytes], { type: 'image/png' }));
            const canvas = document.createElement('canvas');
            canvas.width = image.width;
            canvas.height = image.height;
            canvas.getContext('2d').drawImage(image, 0, 0);
            image.close();
            return canvas.toDataURL('image/webp', 1);
        }, png.toString('base64'));
        const prefix = 'data:image/webp;base64,';
        if (!webp.startsWith(prefix)) {
            throw new Error('Chromium did not return a WebP image.');
        }
        return Buffer.from(webp.slice(prefix.length), 'base64');
    }

    const project = await capture(`?controller=BoardViewController&action=show&project_id=${projectId}`, '.plan-notifications-toggle');
    const task = await capture(`?controller=TaskViewController&action=show&task_id=${taskId}`, '.plan-notifications-task .dropdown > a');
    await mkdir(output, { recursive: true });
    await writeFile(new URL('project-notifications.webp', output), project);
    await writeFile(new URL('task-notifications.webp', output), task);
    console.log('Saved both menus to docs/screenshots/.');
} finally {
    await browser.close();
}
