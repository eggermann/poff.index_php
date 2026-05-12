<?php

final class PoffConfigBuilder
{
    public static function buildClass(string $classContent, array $traitContents): string
    {
        $classContent = self::stripPhpAndRequires($classContent);
        $classContent = preg_replace('/^\s*use\s+PoffConfig[A-Za-z]+Helpers;\s*$/m', '', $classContent);
        $classContent = rtrim((string) $classContent);

        $traitBodies = [];
        foreach ($traitContents as $traitContent) {
            $traitBodies[] = self::extractTraitBody((string) $traitContent);
        }
        $traitBody = trim(implode("\n\n", array_filter($traitBodies, static fn(string $body): bool => $body !== '')));

        if ($traitBody === '') {
            return $classContent;
        }

        $lastBrace = strrpos($classContent, '}');
        if ($lastBrace === false) {
            throw new RuntimeException('Unable to locate the closing brace for PoffConfig.');
        }

        return rtrim(substr($classContent, 0, $lastBrace))
            . "\n\n"
            . $traitBody
            . "\n"
            . substr($classContent, $lastBrace);
    }

    public static function stripPhpAndRequires(string $content): string
    {
        $content = str_replace(['<?php', '?>'], '', $content);
        return (string) preg_replace('/^\s*require_once[^\n]*\n/m', '', $content);
    }

    private static function extractTraitBody(string $traitContent): string
    {
        $traitContent = self::stripPhpAndRequires($traitContent);
        if (!preg_match('/trait\s+\w+\s*\{(.*)\}\s*$/s', $traitContent, $matches)) {
            throw new RuntimeException('Unable to extract helper trait body for PoffConfig build.');
        }

        return trim((string) $matches[1]);
    }
}
