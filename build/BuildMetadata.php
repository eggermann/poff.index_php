<?php

final class BuildMetadata
{
    public static function htmlComment(string $projectRoot): string
    {
        $branch = self::gitValue($projectRoot, ['branch', '--show-current']);
        $commit = self::gitValue($projectRoot, ['rev-parse', '--short=12', 'HEAD']);
        $dirty = self::gitValue($projectRoot, ['status', '--porcelain']) !== '';

        $version = $commit !== '' ? $commit : 'unknown';
        if ($dirty) {
            $version .= '-dirty';
        }

        $branch = $branch !== '' ? $branch : 'unknown';
        $branch = preg_replace('/[^A-Za-z0-9._\/-]/', '_', $branch) ?? 'unknown';
        $branch = str_replace('--', '- -', $branch);

        return sprintf('<!-- PageOff build: version=%s; branch=%s -->', $version, $branch);
    }

    private static function gitValue(string $projectRoot, array $arguments): string
    {
        if (!function_exists('exec')) {
            return '';
        }

        $escapedArguments = array_map('escapeshellarg', $arguments);
        $command = 'git -C ' . escapeshellarg($projectRoot)
            . ' ' . implode(' ', $escapedArguments) . ' 2>/dev/null';
        $lines = [];
        $exitCode = 1;
        @exec($command, $lines, $exitCode);

        return $exitCode === 0 ? trim(implode("\n", $lines)) : '';
    }
}
