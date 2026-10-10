<?php
/** Atomic staging routing update, called only through the staging release. */
if (!defined('ABSPATH')) exit;

function bioco_import_write_guard_file(string $path, string $content, int $mode): void {
    $stream = @fopen($path, 'x');
    if (!$stream) throw new RuntimeException('Cannot create routing backup or candidate.');
    try {
        if (fwrite($stream, $content) !== strlen($content)) throw new RuntimeException('Incomplete routing file write.');
    } finally { fclose($stream); }
    if (!chmod($path, $mode)) throw new RuntimeException('Cannot set routing file permissions.');
}

function bioco_import_install_editor_guard(string $root, string $backup, bool $apply): array {
    $root = realpath($root);
    $backup_dir = realpath(dirname($backup));
    if (!$root || !$backup_dir || $backup === '' || !str_starts_with($backup, '/')) {
        throw new RuntimeException('WordPress root and private backup directory must exist.');
    }
    if ($backup_dir === $root || str_starts_with($backup_dir, $root . '/')) {
        throw new RuntimeException('Routing backup must be outside the WordPress root.');
    }
    $path = $root . '/.htaccess';
    $original = is_file($path) ? file_get_contents($path) : '';
    $guard = file_get_contents(dirname(__DIR__, 2) . '/bioco-core/content/editor-asset-guard.conf');
    if ($original === false || $guard === false || $guard === '') throw new RuntimeException('Cannot read routing configuration.');
    $begin = '# BEGIN bioco editor asset guard';
    $end = '# END bioco editor asset guard';
    $pattern = '/^' . preg_quote($begin, '/') . '\R.*?^' . preg_quote($end, '/') . '(?:\R|$)/ms';
    $count = preg_match_all($pattern, $original);
    if (substr_count($original, $begin) !== $count || substr_count($original, $end) !== $count || $count > 1) {
        throw new RuntimeException('Malformed or duplicate editor routing guard.');
    }
    $candidate = $count ? preg_replace($pattern, $guard, $original, 1) : $guard . "\n" . $original;
    $report = ['changed' => $original !== $candidate, 'applied' => false, 'backup' => null];
    if (!$report['changed'] || !$apply) return $report;
    bioco_import_write_guard_file($backup, $original, 0600);
    $temp = $path . '.bioco-editor-' . bin2hex(random_bytes(6));
    try {
        bioco_import_write_guard_file($temp, $candidate, is_file($path) ? fileperms($path) & 0777 : 0644);
        $current = is_file($path) ? file_get_contents($path) : '';
        if ($current !== $original) throw new RuntimeException('Routing changed during release; refusing to overwrite it.');
        if (!rename($temp, $path)) throw new RuntimeException('Cannot install editor routing guard.');
    } finally { if (is_file($temp)) unlink($temp); }
    return ['changed' => true, 'applied' => true, 'backup' => $backup];
}
