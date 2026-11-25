import { execFileSync, spawn } from 'node:child_process';
import { cp, mkdir, mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const plugin = fileURLToPath(new URL('../', import.meta.url));

export async function withDemoPlan(capture) {
    const checkout = process.env.PLAN_SCREENSHOT_PLAN_DIR ?? fileURLToPath(new URL('../../plan/', import.meta.url));
    const temp = await mkdtemp(join(tmpdir(), 'plan-notifications-screenshots-'));
    const root = join(temp, 'plan');
    const data = join(temp, 'data');
    const archive = join(temp, 'plan.tar');
    // Do not inherit Plan, database, or PHP server settings from the developer's shell.
    const env = { PATH: process.env.PATH, LC_ALL: 'C', TZ: 'UTC', DATA_DIR: data, LOG_DRIVER: 'stderr' };
    let server;
    let stopped;
    let serverLog = '';
    const interrupt = signal => {
        process.exitCode = signal === 'SIGINT' ? 130 : 143;
        server?.kill();
    };
    process.once('SIGINT', interrupt);
    process.once('SIGTERM', interrupt);
    try {
        await mkdir(root);
        await mkdir(data);
        try {
            execFileSync('git', ['-C', checkout, 'archive', '--format=tar', `--output=${archive}`, 'HEAD',
                'app', 'assets', 'libs', 'static', 'vendor', 'index.php', 'favicon.ico'], { stdio: 'inherit' });
        } catch (error) {
            throw new Error(`Could not export Plan from ${checkout}. Set PLAN_SCREENSHOT_PLAN_DIR to a Plan Git checkout with vendored dependencies.`, { cause: error });
        }
        execFileSync('tar', ['-xf', archive, '-C', root], { stdio: 'inherit' });
        const installed = join(root, 'plugins', 'Notifications');
        await mkdir(installed, { recursive: true });
        for (const entry of ['Plugin.php', 'Assets', 'Controller', 'Job', 'Model', 'Schema', 'Template']) {
            await cp(join(plugin, entry), join(installed, entry), { recursive: true });
        }
        if (process.exitCode) return;

        const fixture = fileURLToPath(new URL('./screenshot-demo.php', import.meta.url));
        const php = ['-d', 'date.timezone=UTC'];
        const ids = JSON.parse(execFileSync('php', [...php, fixture], {
            cwd: root, env, encoding: 'utf8', stdio: ['ignore', 'pipe', 'inherit'],
        }));
        // Port 0 lets the OS choose a free loopback port without a probe/bind race.
        server = spawn('php', [...php, '-S', '127.0.0.1:0'], { cwd: root, env, stdio: ['ignore', 'ignore', 'pipe'] });
        stopped = new Promise(resolve => server.once('close', resolve));
        let timeout;
        const baseUrl = await new Promise((resolve, reject) => {
            timeout = setTimeout(() => reject(new Error('Timed out starting the demo Plan server.')), 10_000);
            server.once('error', reject);
            stopped.then(() => reject(new Error('The demo Plan server exited before it was ready.')));
            server.stderr.setEncoding('utf8').on('data', chunk => {
                serverLog = (serverLog + chunk).slice(-12_000);
                const match = serverLog.match(/Development Server \(http:\/\/127\.0\.0\.1:(\d+)\) started/);
                if (match) resolve(`http://127.0.0.1:${match[1]}/`);
            });
        }).finally(() => clearTimeout(timeout));
        if (process.exitCode) return;
        await capture({ ...ids, baseUrl, username: 'admin', password: 'admin' });
    } catch (error) {
        if (serverLog && !process.exitCode) console.error(serverLog.trimEnd());
        if (!process.exitCode) throw error;
    } finally {
        server?.kill();
        await stopped;
        await rm(temp, { recursive: true, force: true });
        process.removeListener('SIGINT', interrupt);
        process.removeListener('SIGTERM', interrupt);
    }
}
