<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use lindemannrock\translationmanager\i18n\MergedLocaleMappingPhpMessageSource;
use lindemannrock\translationmanager\tests\TestCase;

/**
 * @since 5.26.0
 */
final class MergedLocaleMappingPhpMessageSourceTest extends TestCase
{
    private string $managedPath;

    private string $fallbackPath;

    protected function setUp(): void
    {
        parent::setUp();

        $root = sys_get_temp_dir() . '/translation-manager-merged-source-' . bin2hex(random_bytes(4));
        $this->managedPath = $root . '/managed';
        $this->fallbackPath = $root . '/fallback';

        mkdir($this->managedPath . '/de', 0775, true);
        mkdir($this->fallbackPath . '/de', 0775, true);

        file_put_contents(
            $this->managedPath . '/de/example-plugin.php',
            "<?php\nreturn [\n    'Managed only' => 'Managed only DE',\n    'Shared' => 'Managed shared DE',\n    'Trim me' => 'Trim me DE',\n];\n",
        );
        file_put_contents(
            $this->fallbackPath . '/de/example-plugin.php',
            "<?php\nreturn [\n    'Native only' => 'Native only DE',\n    'Shared' => 'Native shared DE',\n];\n",
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname($this->managedPath));
        parent::tearDown();
    }

    public function testManagedMessagesOverlayNativePluginMessages(): void
    {
        $source = $this->createSource();

        self::assertSame(
            'Native only DE',
            $source->translate('example-plugin', 'Native only', 'de'),
            'Native plugin translations must survive when TM has no matching key.',
        );
        self::assertSame(
            'Managed only DE',
            $source->translate('example-plugin', 'Managed only', 'de'),
            'TM-generated translations must load normally.',
        );
        self::assertSame(
            'Managed shared DE',
            $source->translate('example-plugin', 'Shared', 'de'),
            'TM-generated translations must override matching native plugin keys.',
        );
    }

    public function testLocaleMappingAppliesToManagedAndNativeMessages(): void
    {
        $source = $this->createSource();
        $source->localeMapping = [
            'de-CH' => 'de',
        ];

        self::assertSame('Native only DE', $source->translate('example-plugin', 'Native only', 'de-CH'));
        self::assertSame('Managed shared DE', $source->translate('example-plugin', 'Shared', 'de-CH'));
    }

    public function testManagedMessagesFallBackToTrimmedSourceKey(): void
    {
        $source = $this->createSource();

        self::assertSame('Trim me DE', $source->translate('example-plugin', ' Trim me ', 'de'));
    }

    private function createSource(): MergedLocaleMappingPhpMessageSource
    {
        $source = new MergedLocaleMappingPhpMessageSource();
        $source->sourceLanguage = 'en';
        $source->basePath = $this->managedPath;
        $source->fallbackBasePath = $this->fallbackPath;
        $source->forceTranslation = true;
        $source->fileMap = [
            'example-plugin' => 'example-plugin.php',
        ];

        return $source;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = array_diff(scandir($path) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $target = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($target)) {
                $this->removeDirectory($target);
                continue;
            }

            unlink($target);
        }

        rmdir($path);
    }
}
