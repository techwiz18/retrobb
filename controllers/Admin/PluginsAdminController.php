<?php
declare(strict_types=1);

namespace RetroBB\Controllers\Admin;

use RetroBB\Core\Auth;
use RetroBB\Core\Db;
use RetroBB\Core\Guard;
use RetroBB\Core\View;

/** Plugin management: enable/disable/uninstall drop-in plugins. Code runs as
 *  the site owner, so every action here is super-only (issue #47). */
class PluginsAdminController
{
    public function pluginsPage(): void
    {
        Guard::requireSuper();
        View::render('admin/plugins', ['plugins' => $this->pluginList(), 'pageTitle' => 'Plugins — AdminCP']);
    }

    /** @return array{name,version,description,author,enabled}[] */
    private function pluginList(): array
    {
        $states = \RetroBB\Core\Plugins::states();
        $out = [];
        foreach (glob(dirname(__DIR__, 2) . '/plugins/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            $meta = ['version' => '', 'description' => '', 'author' => ''];
            $json = @file_get_contents($dir . '/plugin.json');
            if (is_string($json)) {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $meta = array_merge($meta, $decoded);
                }
            }
            $out[] = [
                'name' => $name,
                'version' => (string) ($meta['version'] ?? ''),
                'description' => (string) ($meta['description'] ?? ''),
                'author' => (string) ($meta['author'] ?? ''),
                'enabled' => \RetroBB\Core\Plugins::isEnabled($name, $states) ? 1 : 0,
            ];
        }
        usort($out, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $out;
    }

    public function pluginToggle(string $name): void
    {
        Guard::requireSuper();
        Guard::csrf('/admin');
        $name = preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        if ($name === '' || !is_dir(dirname(__DIR__, 2) . '/plugins/' . $name)) {
            redirect('/admin/plugins');
        }
        try {
            $next = \RetroBB\Core\Plugins::toggle($name);
            \RetroBB\Core\Modlog::log((int) Auth::user()['id'], $next ? 'plugin_enable' : 'plugin_disable', 'plugin', 0, $name);
            // Lifecycle hook (issue #20): seeding on enable, cleanup on
            // disable — replaces every-request CREATE TABLE IF NOT EXISTS.
            \RetroBB\Core\Hooks::do_action($next ? 'plugin_enabled' : 'plugin_disabled', $name);
        } catch (\Throwable) {
            $_SESSION['flash_error'] = 'Could not save plugin state.';
        }
        redirect('/admin/plugins');
    }

    /** Explicit uninstall (issue #22): super-only, confirmed in the form.
        Fires plugin_uninstalled so the plugin drops its tables, then core
        forgets the row plus its "<name>.*" settings. Files stay put. */
    public function pluginUninstall(string $name): void
    {
        Guard::requireSuper();
        Guard::csrf('/admin');
        $name = preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        $dir = dirname(__DIR__, 2) . '/plugins/' . $name;
        if ($name === '' || !is_dir($dir)) {
            redirect('/admin/plugins');
        }
        try {
            // Disabled plugins never load their bootstrap, so fetch it through
            // the shared accessor (require_once = no-op when already loaded) —
            // otherwise the hook below would fire with no listener.
            \RetroBB\Core\Plugins::loadOne($name);
            \RetroBB\Core\Hooks::do_action('plugin_uninstalled', $name);
            $pdo = Db::pdo();
            $pdo->prepare('DELETE FROM plugins WHERE name=?')->execute([$name]);
            $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $name) . '.%';
            $pdo->prepare("DELETE FROM settings WHERE `key` LIKE ? ESCAPE '\\\\'")->execute([$like]);
            // Forget its migrations too (issue #22): otherwise a reinstall
            // skips them as already-applied while the tables are gone.
            $pdo->prepare('DELETE FROM plugin_migrations WHERE `plugin`=?')->execute([$name]);
            self::removeDir($dir);
            \RetroBB\Core\Modlog::log((int) Auth::user()['id'], 'plugin_uninstall', 'plugin', 0, $name);
            $_SESSION['flash_ok'] = 'Plugin uninstalled: tables, settings, and files removed.';
        } catch (\Throwable) {
            $_SESSION['flash_error'] = 'Could not uninstall plugin.';
        }
        redirect('/admin/plugins');
    }

    /** Recursive plugin removal, jailed to plugins/ (realpath containment). */
    private static function removeDir(string $dir): void
    {
        $base = realpath(dirname(__DIR__, 2) . '/plugins');
        $real = realpath($dir);
        if ($base === false || $real === false || $real === $base || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Refusing to delete outside plugins/.');
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($real, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($real);
    }
}
