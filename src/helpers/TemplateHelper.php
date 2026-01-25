<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * AST-based template scanning for translation discovery
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\helpers;

use Craft;
use craft\helpers\FileHelper;
use lindemannrock\translationmanager\TranslationManager;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\TempNameExpression;
use Twig\Node\Node;
use Twig\Node\SetNode;
use Twig\Source;

/**
 * Template Helper
 *
 * Uses Twig's AST parser to accurately find translation usage in templates.
 * More reliable than regex - handles comments, variables, and edge cases correctly.
 *
 * @since 5.17.0
 */
class TemplateHelper
{
    /**
     * Scan all templates for translation keys
     *
     * @param array $enabledCategories Categories to scan for
     * @return array{
     *     found: array<string, array<string, array{file: string, category: string}>>,
     *     errors: array<string>,
     *     scannedFiles: int
     * }
     * @since 5.17.0
     */
    public static function scanTemplates(array $enabledCategories): array
    {
        $result = [
            'found' => [],
            'errors' => [],
            'scannedFiles' => 0,
        ];

        // Initialize categories
        foreach ($enabledCategories as $category) {
            $result['found'][$category] = [];
        }

        $originalMode = null;
        $twig = self::getTwigEnvironment($originalMode);
        if ($twig === null) {
            $result['errors'][] = 'Could not initialize Twig environment';
            return $result;
        }

        try {
            $settings = TranslationManager::getInstance()->getSettings();
            $primaryCategory = $settings->getPrimaryCategory();

            $templateFiles = self::getTemplateFiles();

            foreach ($templateFiles as $filePath) {
                $result['scannedFiles']++;

                try {
                    $messages = self::parseTemplateFile($twig, $filePath, $primaryCategory);

                    foreach ($messages as $message) {
                        $category = $message['category'];

                        // Only track if category is enabled
                        if (!in_array($category, $enabledCategories, true)) {
                            continue;
                        }

                        $key = $message['key'];
                        $relativePath = self::getRelativePath($filePath);

                        $result['found'][$category][$key] = [
                            'file' => $relativePath,
                            'category' => $category,
                        ];
                    }
                } catch (\Throwable $e) {
                    // Log but continue - one broken template shouldn't stop the scan
                    $relativePath = self::getRelativePath($filePath);
                    $result['errors'][] = "{$relativePath}: {$e->getMessage()}";
                }
            }
        } finally {
            // Always restore the original template mode
            self::restoreTemplateMode($originalMode);
        }

        return $result;
    }

    /**
     * Parse a single template file for translation keys
     *
     * @return array<array{key: string, category: string}>
     * @since 5.17.0
     */
    public static function parseTemplateFile(\Twig\Environment $twig, string $filePath, string $defaultCategory = 'site'): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Could not read file: {$filePath}");
        }

        $source = new Source($content, basename($filePath), $filePath);
        $tokenStream = $twig->tokenize($source);
        $ast = $twig->parse($tokenStream);

        $messages = [];
        $variables = [];

        self::walkNode($ast, $messages, $variables, $defaultCategory);

        return $messages;
    }

    /**
     * Recursively walk the AST to find translation filter usage
     *
     * @param array<string, mixed> $variables Tracked variable assignments
     * @param array<array{key: string, category: string}> $messages Found messages
     */
    private static function walkNode(
        Node $node,
        array &$messages,
        array &$variables,
        string $defaultCategory,
    ): void {
        // Track variable assignments: {% set myVar = 'Hello' %}
        if ($node instanceof SetNode) {
            self::trackSetNode($node, $variables);
        }

        // Look for |t filter usage
        if ($node instanceof FilterExpression) {
            $message = self::extractTranslation($node, $variables, $defaultCategory);
            if ($message !== null) {
                $messages[] = $message;
            }
        }

        // Recurse into child nodes
        foreach ($node as $child) {
            if ($child instanceof Node) {
                self::walkNode($child, $messages, $variables, $defaultCategory);
            }
        }
    }

    /**
     * Track variable assignments from SetNode
     *
     * Handles: {% set myMessage = 'Hello World' %}
     */
    private static function trackSetNode(SetNode $node, array &$variables): void
    {
        try {
            $namesNode = $node->getNode('names');
            $valuesNode = $node->getNode('values');

            // Get variable name
            $name = null;
            if ($namesNode instanceof NameExpression || $namesNode instanceof TempNameExpression) {
                $name = $namesNode->getAttribute('name');
            } elseif (method_exists($namesNode, 'getAttribute')) {
                try {
                    $name = $namesNode->getAttribute('name');
                } catch (\Throwable) {
                    // Ignore - complex assignment
                }
            }

            // Get value if it's a constant string
            if ($name !== null && $valuesNode instanceof ConstantExpression) {
                $value = $valuesNode->getAttribute('value');
                if (is_string($value)) {
                    $variables[$name] = $value;
                }
            }
        } catch (\Throwable) {
            // Complex set nodes we can't parse - that's okay
        }
    }

    /**
     * Extract translation from a FilterExpression if it's a |t filter
     *
     * @return array{key: string, category: string}|null
     */
    private static function extractTranslation(
        FilterExpression $node,
        array $variables,
        string $defaultCategory,
    ): ?array {
        try {
            // Check if this is the |t filter
            $filterNode = $node->getNode('filter');
            $filterName = $filterNode->getAttribute('value');

            if ($filterName !== 't') {
                return null;
            }

            // Get the value being translated
            $valueNode = $node->getNode('node');
            $key = self::extractStringValue($valueNode, $variables);

            if ($key === null || $key === '') {
                return null;
            }

            // Skip Twig code patterns
            if (self::containsTwigCode($key)) {
                return null;
            }

            // Get category from arguments
            $category = self::extractCategory($node, $defaultCategory);

            return [
                'key' => $key,
                'category' => $category,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Extract string value from an expression node
     */
    private static function extractStringValue(Node $node, array $variables): ?string
    {
        // Direct string: 'Hello'|t
        if ($node instanceof ConstantExpression) {
            $value = $node->getAttribute('value');
            return is_string($value) ? $value : null;
        }

        // Variable reference: myVar|t (where myVar was set earlier)
        if ($node instanceof TempNameExpression || $node instanceof NameExpression) {
            $name = $node->getAttribute('name');
            return $variables[$name] ?? null;
        }

        return null;
    }

    /**
     * Extract category from filter arguments
     *
     * Handles:
     * - 'Hello'|t('myCategory')
     * - 'Hello'|t(category='myCategory')
     * - 'Hello'|t(_globals.primaryTranslationCategory) -> uses default
     */
    private static function extractCategory(FilterExpression $node, string $defaultCategory): string
    {
        try {
            $argumentsNode = $node->getNode('arguments');

            foreach ($argumentsNode as $key => $argNode) {
                // Named argument: category='myCategory'
                if ($key === 'category' && $argNode instanceof ConstantExpression) {
                    $value = $argNode->getAttribute('value');
                    if (is_string($value) && $value !== '') {
                        return $value;
                    }
                }

                // Positional first argument: |t('myCategory')
                if ($key === 0 && $argNode instanceof ConstantExpression) {
                    $value = $argNode->getAttribute('value');
                    if (is_string($value) && $value !== '') {
                        return $value;
                    }
                }

                // Dynamic category (variable/global) - use default
                // e.g., |t(_globals.primaryTranslationCategory)
                if ($key === 0 || $key === 'category') {
                    if (!($argNode instanceof ConstantExpression)) {
                        return $defaultCategory;
                    }
                }
            }
        } catch (\Throwable) {
            // Fall through to default
        }

        return $defaultCategory;
    }

    /**
     * Check if text contains Twig code that shouldn't be translated
     */
    private static function containsTwigCode(string $text): bool
    {
        return preg_match('/\{\{|\{%|\{#/', $text) === 1;
    }

    /**
     * Get Twig environment configured for site templates
     *
     * @param string|null &$originalMode Pass a variable to receive the original mode for later restoration
     */
    private static function getTwigEnvironment(?string &$originalMode = null): ?\Twig\Environment
    {
        try {
            $view = Craft::$app->getView();
            $originalMode = $view->getTemplateMode();
            $view->setTemplateMode('site');
            return $view->getTwig();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Restore the original template mode
     */
    private static function restoreTemplateMode(?string $originalMode): void
    {
        if ($originalMode !== null) {
            try {
                Craft::$app->getView()->setTemplateMode($originalMode);
            } catch (\Throwable) {
                // Ignore errors restoring mode
            }
        }
    }

    /**
     * Get all template files in the templates directory
     *
     * @return array<string>
     */
    private static function getTemplateFiles(): array
    {
        $templatesPath = Craft::getAlias('@templates');

        if (!is_string($templatesPath) || !is_dir($templatesPath)) {
            return [];
        }

        return FileHelper::findFiles($templatesPath, [
            'only' => ['*.twig', '*.html'],
            'recursive' => true,
        ]);
    }

    /**
     * Get path relative to templates directory
     */
    private static function getRelativePath(string $filePath): string
    {
        $templatesPath = Craft::getAlias('@templates');

        if (is_string($templatesPath) && str_starts_with($filePath, $templatesPath)) {
            return ltrim(substr($filePath, strlen($templatesPath)), '/');
        }

        return basename($filePath);
    }
}
