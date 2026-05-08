<?php

trait WorktypeRenderTrait
{
    use WorktypeStateTrait;
    use WorktypeLayoutTrait;
    use WorktypeContextTrait;

    /**
     * Render body content for a given kind using templates.
     */
    public static function render(string $kind, array $ctx): string
    {
        $work = $ctx['work'] ?? [];
        $layout = self::normalizeLayout(is_array($work) ? ($work['layout'] ?? null) : null, $kind === 'folder' ? 'works' : 'work');
        $resolvedWork = is_array($work) ? $work : [];
        $resolvedWork['layout'] = $layout;
        $workTemplateKey = trim((string) ($resolvedWork['template'] ?? ''));
        if ($workTemplateKey !== '' && trim((string) ($layout['sectionTemplate'] ?? '')) === '') {
            $template = self::template($workTemplateKey);
            if (is_string($template) && trim($template) !== '') {
                $layout['sectionTemplate'] = $template;
                $resolvedWork['layout'] = $layout;
            }
        }
        $rendered = self::renderTemplate($kind, $ctx, $resolvedWork, $layout);
        if ($rendered !== null) {
            return $rendered;
        }

        $fallbackLayout = self::normalizeLayout([
            'mode' => self::DEFAULT_LAYOUT_NAME,
            'name' => self::DEFAULT_LAYOUT_NAME,
            'engine' => 'lightncandy',
            'section' => $kind === 'folder' ? 'works' : 'work',
        ], $kind === 'folder' ? 'works' : 'work');
        $fallbackWork = $resolvedWork;
        $fallbackWork['layout'] = $fallbackLayout;

        $fallbackRendered = self::renderTemplate($kind, $ctx, $fallbackWork, $fallbackLayout);
        if ($fallbackRendered !== null) {
            return $fallbackRendered;
        }

        return self::fallbackRender($kind, $ctx);
    }

    private static function helpers(): array
    {
        return [
            'eq' => static function ($left = null, $right = null, $options = null): bool {
                return $left == $right;
            },
            'ne' => static function ($left = null, $right = null, $options = null): bool {
                return $left != $right;
            },
            'contains' => static function ($haystack = null, $needle = null, $options = null): bool {
                if ($needle === null) {
                    return false;
                }
                if (is_array($haystack)) {
                    return in_array($needle, $haystack, false);
                }

                return str_contains((string) $haystack, (string) $needle);
            },
            'startsWith' => static function ($value = null, $prefix = null, $options = null): bool {
                if ($prefix === null) {
                    return false;
                }

                return str_starts_with((string) $value, (string) $prefix);
            },
            'endsWith' => static function ($value = null, $suffix = null, $options = null): bool {
                if ($suffix === null) {
                    return false;
                }

                return str_ends_with((string) $value, (string) $suffix);
            },
            'not' => static function ($value = null, $options = null): bool {
                return !$value;
            },
            'and' => static function ($left = null, $right = null, $options = null): bool {
                return (bool) $left && (bool) $right;
            },
            'or' => static function ($left = null, $right = null, $options = null): bool {
                return (bool) $left || (bool) $right;
            },
        ];
    }

    private static function renderTemplate(string $kind, array $ctx, array $work, array $layout): ?string
    {
        $template = self::layoutTemplate($kind, $work);
        if ($template === '' || !self::rendererAvailable()) {
            return null;
        }

        $partials = self::sanitizePartials(self::templates());
        $section = (string) ($layout['section'] ?? ($kind === 'folder' ? 'works' : 'work'));
        if (!empty($layout['sectionTemplate']) && is_string($layout['sectionTemplate'])) {
            $partials[$section] = $layout['sectionTemplate'];
            $template = self::ensureTemplateIncludesSectionPartial($template, $section, $layout);
        }

        $handlerInstalled = false;
        try {
            set_error_handler(
                static function (int $severity, string $message, string $file = '', int $line = 0): void {
                    throw new \ErrorException($message, 0, $severity, $file, $line);
                }
            );
            $handlerInstalled = true;
            $compiled = \LightnCandy\LightnCandy::compile($template, [
                'partials' => $partials,
                'helpers' => self::helpers(),
                'flags' => \LightnCandy\LightnCandy::FLAG_HANDLEBARSJS
                    | \LightnCandy\LightnCandy::FLAG_RUNTIMEPARTIAL
                    | \LightnCandy\LightnCandy::FLAG_ERROR_LOG,
            ]);
            if (!is_string($compiled) || $compiled === '') {
                return null;
            }
            $renderer = \LightnCandy\LightnCandy::prepare($compiled);
            if (!is_callable($renderer)) {
                return null;
            }

            $rendered = $renderer(self::buildRenderContext($kind, $ctx, $work, $layout));
            return is_string($rendered) ? self::normalizeRenderedViewerUrls($rendered) : null;
        } catch (\Throwable $error) {
            return null;
        } finally {
            if ($handlerInstalled) {
                restore_error_handler();
            }
        }
    }

    private static function normalizeRenderedViewerUrls(string $html): string
    {
        return preg_replace_callback(
            '/\b(href|src)=([\'"])(.*?)\2/i',
            static function (array $matches): string {
                $value = html_entity_decode((string) $matches[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (substr_count($value, '?view=1&') < 2) {
                    return $matches[0];
                }

                $lastQueryStart = strrpos($value, '?view=1&');
                if ($lastQueryStart === false) {
                    return $matches[0];
                }

                $normalized = substr($value, $lastQueryStart);
                $escaped = htmlspecialchars($normalized, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return $matches[1] . '=' . $matches[2] . $escaped . $matches[2];
            },
            $html
        ) ?? $html;
    }

    private static function ensureTemplateIncludesSectionPartial(string $template, string $section, array $layout): string
    {
        if (strpos($template, '{{#if isFolder}}') !== false && strpos($template, '{{else}}') !== false) {
            return $template;
        }

        $hasWorks = preg_match('/\{\{\s*>\s*works\b/', $template) === 1;
        $hasWork = preg_match('/\{\{\s*>\s*work\b/', $template) === 1;
        $sharedBlock = '{{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}';

        if ($hasWorks && !$hasWork) {
            $updated = preg_replace('/\{\{\s*>\s*works\b[^}]*\}\}/', $sharedBlock, $template, 1);
            return is_string($updated) ? $updated : $template;
        }

        if ($hasWork && !$hasWorks) {
            $updated = preg_replace('/\{\{\s*>\s*work\b[^}]*\}\}/', $sharedBlock, $template, 1);
            return is_string($updated) ? $updated : $template;
        }

        $partialPattern = '/\{\{\s*>\s*' . preg_quote($section, '/') . '\b/';
        if (preg_match($partialPattern, $template) === 1) {
            return $template;
        }

        $partialMarkup = '{{> ' . $section . '}}';
        foreach (['</main>', '</section>', '</article>', '</div>'] as $closingTag) {
            $position = strripos($template, $closingTag);
            if ($position !== false) {
                return substr($template, 0, $position) . $partialMarkup . substr($template, $position);
            }
        }

        return $template . $partialMarkup;
    }

    private static function fallbackRender(string $kind, array $ctx): string
    {
        if ($kind === 'folder') {
            return '';
        }

        $path = htmlspecialchars((string) ($ctx['path'] ?? ''), ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars((string) ($ctx['name'] ?? ''), ENT_QUOTES, 'UTF-8');

        return '<iframe src="' . $path . '" title="' . $name . '"></iframe>';
    }

    private static function sanitizePartials(array $partials): array
    {
        $sanitized = [];
        foreach ($partials as $name => $template) {
            $partialName = trim((string) $name);
            if ($partialName === '' || $template === null) {
                continue;
            }

            if (!is_string($template)) {
                if (!is_scalar($template)) {
                    continue;
                }
                $template = (string) $template;
            }

            $sanitized[$partialName] = $template;
        }

        return $sanitized;
    }

    private static function rendererAvailable(): bool
    {
        return class_exists('\LightnCandy\LightnCandy');
    }
}
