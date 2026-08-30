<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use Craft;
use craft\base\FsInterface;
use craft\base\LocalFsInterface;
use craft\base\MissingComponentInterface;
use craft\fs\MissingFs;
use craft\models\Volume;
use craft\services\Config;
use craft\services\Volumes;
use craft\web\View;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\translationmanager\models\Settings;
use lindemannrock\translationmanager\presenters\StorageWarningPresentation;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use Throwable;
use TypeError;

/**
 * @since 5.35.0
 */
final class StorageWarningPresentationTest extends TestCase
{
    private const WARNING = 'This host has an ephemeral filesystem. Files in the effective local storage path may be lost during deployments, restarts, or environment replacement. Select a Craft volume backed by durable remote storage. On Craft Cloud, use a Cloud filesystem.';
    private const UNAVAILABLE = 'The configured backup volume cannot currently be used. Backup operations are unavailable until the volume is restored or the effective setting is changed.';

    private bool $hadEphemeralSetting;
    private mixed $originalEphemeralSetting;
    private Volumes $originalVolumes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hadEphemeralSetting = array_key_exists('CRAFT_EPHEMERAL', $_SERVER);
        $this->originalEphemeralSetting = $_SERVER['CRAFT_EPHEMERAL'] ?? null;
        $this->originalVolumes = Craft::$app->getVolumes();
        $_SERVER['CRAFT_EPHEMERAL'] = true;
    }

    protected function tearDown(): void
    {
        Craft::$app->set('volumes', $this->originalVolumes);
        if ($this->hadEphemeralSetting) {
            $_SERVER['CRAFT_EPHEMERAL'] = $this->originalEphemeralSetting;
        } else {
            unset($_SERVER['CRAFT_EPHEMERAL']);
        }
        parent::tearDown();
    }

    public function testDurableCustomPathDoesNotShowWarning(): void
    {
        $_SERVER['CRAFT_EPHEMERAL'] = false;
        $volumes = $this->createMock(Volumes::class);
        $volumes->expects(self::never())->method('getVolumeByUid');
        Craft::$app->set('volumes', $volumes);

        $presentation = StorageWarningPresentation::forSettings($this->localSettings());

        self::assertSame(StorageWarningPresentation::STATE_DURABLE_HOST, $presentation->state);
        self::assertFalse($presentation->shouldShowWarning());
    }

    public function testEphemeralCustomPathShowsWarning(): void
    {
        $presentation = StorageWarningPresentation::forSettings($this->localSettings());

        self::assertSame(StorageWarningPresentation::STATE_LOCAL, $presentation->state);
        self::assertTrue($presentation->shouldShowWarning());
    }

    public function testDurableLocalVolumeDoesNotShowWarning(): void
    {
        $_SERVER['CRAFT_EPHEMERAL'] = false;
        $this->installVolumes($this->volume($this->localFilesystem()));

        $presentation = StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame(StorageWarningPresentation::STATE_DURABLE_HOST, $presentation->state);
        self::assertFalse($presentation->shouldShowWarning());
        self::assertFalse($presentation->isUnavailable());
    }

    public function testDurableNonLocalVolumeDoesNotShowWarning(): void
    {
        $_SERVER['CRAFT_EPHEMERAL'] = false;
        $this->installVolumes($this->volume($this->nonLocalFilesystem()));

        $presentation = StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame(StorageWarningPresentation::STATE_NON_LOCAL, $presentation->state);
        self::assertFalse($presentation->shouldShowWarning());
        self::assertFalse($presentation->isUnavailable());
    }

    public function testEphemeralLocalVolumeShowsWarning(): void
    {
        $this->installVolumes($this->volume($this->localFilesystem()));

        $presentation = StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame(StorageWarningPresentation::STATE_LOCAL, $presentation->state);
        self::assertTrue($presentation->shouldShowWarning());
    }

    public function testEphemeralNonLocalVolumeSuppressesWarning(): void
    {
        $this->installVolumes($this->volume($this->nonLocalFilesystem()));

        $presentation = StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame(StorageWarningPresentation::STATE_NON_LOCAL, $presentation->state);
        self::assertFalse($presentation->shouldShowWarning());
    }

    public function testConfigNonLocalVolumeOverrideSuppressesWarningDespiteStoredLocalPath(): void
    {
        $this->installVolumes($this->volume($this->nonLocalFilesystem()));
        $effective = $this->applyConfigOverrides(
            $this->localSettings(),
            ['backupVolumeUid' => 'warning-volume'],
        );

        $presentation = StorageWarningPresentation::forSettings($effective);

        self::assertSame('warning-volume', $effective->backupVolumeUid);
        self::assertFalse($presentation->shouldShowWarning());
    }

    public function testConfigLocalPathOverrideShowsWarningDespiteStoredNonLocalVolume(): void
    {
        $effective = $this->applyConfigOverrides(
            $this->volumeSettings(),
            [
                'backupVolumeUid' => '',
                'backupPath' => '@storage/translation-manager/configured-backups',
            ],
        );

        $presentation = StorageWarningPresentation::forSettings($effective);

        self::assertSame('', $effective->backupVolumeUid);
        self::assertSame('@storage/translation-manager/configured-backups', $effective->backupPath);
        self::assertTrue($presentation->shouldShowWarning());
    }

    public function testConfigUnavailableVolumeOverrideWinsOverStoredValidVolume(): void
    {
        $effective = $this->applyConfigOverrides(
            $this->volumeSettings(),
            ['backupVolumeUid' => 'configured-missing-volume'],
        );
        $this->installVolumes(null);

        $presentation = StorageWarningPresentation::forSettings($effective);

        self::assertSame('configured-missing-volume', $effective->backupVolumeUid);
        self::assertSame(StorageWarningPresentation::STATE_UNAVAILABLE, $presentation->state);
        self::assertTrue($presentation->isUnavailable());
        self::assertFalse($presentation->shouldShowWarning());
    }

    public function testEphemeralMissingVolumeIsUnavailableWithoutLocalWarning(): void
    {
        $this->installVolumes(null);

        $presentation = StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame(StorageWarningPresentation::STATE_UNAVAILABLE, $presentation->state);
        self::assertTrue($presentation->isUnavailable());
        self::assertFalse($presentation->shouldShowWarning());
    }

    public function testDurableMissingVolumeIsUnavailableWithoutLocalWarning(): void
    {
        $_SERVER['CRAFT_EPHEMERAL'] = false;
        $this->installVolumes(null);

        $presentation = StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame(StorageWarningPresentation::STATE_UNAVAILABLE, $presentation->state);
        self::assertTrue($presentation->isUnavailable());
        self::assertFalse($presentation->shouldShowWarning());
    }

    #[DataProvider('hostTypes')]
    public function testValidationInvalidVolumeIsUnavailableWithoutLocalWarning(bool $ephemeral): void
    {
        $_SERVER['CRAFT_EPHEMERAL'] = $ephemeral;
        $webroot = Craft::getAlias('@webroot');
        self::assertIsString($webroot);
        /** @var FsInterface&LocalFsInterface&MockObject $fs */
        $fs = $this->createMockForIntersectionOfInterfaces([FsInterface::class, LocalFsInterface::class]);
        $fs->method('getRootPath')->willReturn($webroot . '/warning-test');
        $this->installVolumes($this->volume($fs));

        $presentation = StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame(StorageWarningPresentation::STATE_UNAVAILABLE, $presentation->state);
        self::assertTrue($presentation->isUnavailable());
        self::assertFalse($presentation->shouldShowWarning());
    }

    public function testEphemeralMissingFilesystemIsUnavailableAndNotClassifiedAsDurable(): void
    {
        $this->installVolumes($this->volume(new MissingFs(['handle' => 'warning-missing'])));

        $presentation = StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame(StorageWarningPresentation::STATE_UNAVAILABLE, $presentation->state);
        self::assertTrue($presentation->isUnavailable());
        self::assertFalse($presentation->shouldShowWarning());
    }

    public function testMissingComponentInterfaceIsUnavailable(): void
    {
        /** @var FsInterface&MockObject $fs */
        $fs = $this->createMockForIntersectionOfInterfaces([FsInterface::class, MissingComponentInterface::class]);
        $this->installVolumes($this->volume($fs));

        $presentation = StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame(StorageWarningPresentation::STATE_UNAVAILABLE, $presentation->state);
        self::assertTrue($presentation->isUnavailable());
        self::assertFalse($presentation->shouldShowWarning());
    }

    public function testVolumeLookupThrowableIsUnavailable(): void
    {
        $volumes = $this->createMock(Volumes::class);
        $volumes->method('getVolumeByUid')->willThrowException(new \Error('volume lookup failure'));
        Craft::$app->set('volumes', $volumes);

        $presentation = StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame(StorageWarningPresentation::STATE_UNAVAILABLE, $presentation->state);
        self::assertTrue($presentation->isUnavailable());
        self::assertFalse($presentation->shouldShowWarning());
    }

    #[DataProvider('filesystemThrowables')]
    public function testFilesystemResolutionThrowableIsUnavailable(Throwable $throwable): void
    {
        $volume = $this->createMock(Volume::class);
        $volume->method('getFs')->willThrowException($throwable);
        $this->installVolumes($volume);

        $presentation = StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame(StorageWarningPresentation::STATE_UNAVAILABLE, $presentation->state);
        self::assertTrue($presentation->isUnavailable());
        self::assertFalse($presentation->shouldShowWarning());
    }

    public function testClassificationPerformsNoStorageIoOrProviderProbe(): void
    {
        $parent = $this->createTrackedTempDirectory('translation-storage-warning-');
        $prospectiveDirectory = $parent . '/must-not-exist';
        $fs = $this->nonLocalFilesystem();
        $this->expectNoStorageIo($fs);
        $this->installVolumes($this->volume($fs));
        $settings = $this->volumeSettings();
        $settings->backupPath = $prospectiveDirectory;

        $presentation = StorageWarningPresentation::forSettings($settings);

        self::assertSame(StorageWarningPresentation::STATE_NON_LOCAL, $presentation->state);
        self::assertDirectoryDoesNotExist($prospectiveDirectory);
    }

    public function testClassificationDoesNotInitializeOperationalBackupStorage(): void
    {
        $plugin = TranslationManager::getInstance();
        $backupWasInitialized = $plugin->has('backup', true);
        $this->installVolumes($this->volume($this->nonLocalFilesystem()));

        StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame($backupWasInitialized, $plugin->has('backup', true));
    }

    public function testClassificationDoesNotMutateEffectiveSettings(): void
    {
        $settings = $this->volumeSettings();
        $before = $settings->getAttributes();
        $this->installVolumes($this->volume($this->nonLocalFilesystem()));

        StorageWarningPresentation::forSettings($settings);

        self::assertSame($before, $settings->getAttributes());
    }

    public function testVolumeLocationPreservesEffectiveSubpathWithoutBackupService(): void
    {
        $backupWasInitialized = TranslationManager::getInstance()->has('backup', true);
        $volume = $this->volume($this->nonLocalFilesystem());
        $volume->name = 'Archive';
        $volume->method('getSubpath')->willReturn('tenant/backups');
        $this->installVolumes($volume);

        $presentation = StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame(StorageWarningPresentation::STATE_NON_LOCAL, $presentation->state);
        self::assertSame(
            'Volume: Archive/tenant/backups/translation-manager/backups',
            $presentation->location,
        );
        self::assertSame($backupWasInitialized, TranslationManager::getInstance()->has('backup', true));
    }

    public function testWarningRendersExactPluginOwnedMessageImmediatelyAfterLocation(): void
    {
        $template = (string)file_get_contents(dirname(__DIR__, 2) . '/src/templates/settings/backup.twig');
        $location = strpos($template, 'Backup Location:');
        $warning = strpos($template, self::WARNING);
        $credit = strpos($template, "lindemannrock-base/_components/plugin-credit");

        self::assertIsInt($location);
        self::assertIsInt($warning);
        self::assertIsInt($credit);
        self::assertTrue($location < $warning && $warning < $credit);
        self::assertStringContainsString("|t('translation-manager')", substr($template, $warning, strlen(self::WARNING) + 45));

        $html = Craft::$app->getView()->renderTemplate(
            'lindemannrock-base/_components/info-box',
            [
                'message' => Craft::t('translation-manager', self::WARNING),
                'type' => 'warning',
                'variant' => 'colored',
                'allowHtml' => false,
            ],
            View::TEMPLATE_MODE_CP,
        );
        self::assertStringContainsString(self::WARNING, $html);
        self::assertStringContainsString('lr-info-box--colored', $html);

        foreach (['en', 'de', 'fr', 'nl', 'es', 'ar', 'it', 'pt', 'ja', 'sv', 'da', 'no'] as $locale) {
            $catalogue = require dirname(__DIR__, 2) . "/src/translations/{$locale}/translation-manager.php";
            self::assertArrayHasKey(self::WARNING, $catalogue);
        }
    }

    public function testUnavailableErrorReplacesLocationWithoutOperationalResolution(): void
    {
        $template = (string)file_get_contents(dirname(__DIR__, 2) . '/src/templates/settings/backup.twig');
        $unavailableCondition = strpos($template, 'if storageWarning.isUnavailable');
        $unavailable = strpos($template, self::UNAVAILABLE);
        $availableCondition = strpos($template, "elseif not settings.getErrors('backupPath')");
        $location = strpos($template, 'Backup Location:');
        $warningCondition = strpos($template, 'if storageWarning.shouldShowWarning');

        self::assertIsInt($unavailableCondition);
        self::assertIsInt($unavailable);
        self::assertIsInt($availableCondition);
        self::assertIsInt($location);
        self::assertIsInt($warningCondition);
        self::assertTrue($unavailableCondition < $unavailable);
        self::assertTrue($unavailable < $availableCondition);
        self::assertTrue($availableCondition < $location);
        self::assertTrue($location < $warningCondition);
        self::assertStringNotContainsString('craft.translationManager.backup.getBackupPath()', $template);
        self::assertStringContainsString(
            '{% set backupPath = storageWarning.location ?? settings.getBackupPath() %}',
            $template,
        );
        self::assertStringContainsString("type: 'error'", $template);
        self::assertSame(1, substr_count($template, 'storageWarning.isUnavailable'));
        self::assertSame(1, substr_count($template, 'storageWarning.shouldShowWarning'));

        $html = Craft::$app->getView()->renderTemplate(
            'lindemannrock-base/_components/info-box',
            [
                'message' => Craft::t('translation-manager', self::UNAVAILABLE),
                'type' => 'error',
                'variant' => 'colored',
                'allowHtml' => false,
            ],
            View::TEMPLATE_MODE_CP,
        );
        self::assertStringContainsString(self::UNAVAILABLE, $html);
        self::assertStringContainsString('lr-info-box--colored', $html);

        foreach (['en', 'de', 'fr', 'nl', 'es', 'ar', 'it', 'pt', 'ja', 'sv', 'da', 'no'] as $locale) {
            $catalogue = require dirname(__DIR__, 2) . "/src/translations/{$locale}/translation-manager.php";
            self::assertArrayHasKey(self::UNAVAILABLE, $catalogue);
        }
    }

    /** @return array<string, array{bool}> */
    public static function hostTypes(): array
    {
        return [
            'ephemeral host' => [true],
            'durable host' => [false],
        ];
    }

    /** @return array<string, array{Throwable}> */
    public static function filesystemThrowables(): array
    {
        return [
            'exception' => [new RuntimeException('filesystem exception')],
            'error' => [new \Error('filesystem error')],
            'other throwable' => [new TypeError('filesystem type error')],
        ];
    }

    private function localSettings(): Settings
    {
        return new Settings([
            'backupVolumeUid' => '',
            'backupPath' => '@storage/translation-manager/backups',
        ]);
    }

    private function volumeSettings(): Settings
    {
        return new Settings([
            'backupVolumeUid' => 'warning-volume',
            'backupPath' => '@storage/translation-manager/stored-local-backups',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function applyConfigOverrides(Settings $settings, array $overrides): Settings
    {
        $config = $this->createMock(Config::class);
        $config->method('getConfigFromFile')->willReturnCallback(
            static fn(string $handle): array => $handle === 'translation-manager' ? $overrides : [],
        );
        Craft::$app->set('config', $config);
        PluginHelper::applyConfigOverridesToSettings($settings, 'translation-manager');

        return $settings;
    }

    private function localFilesystem(): FsInterface & LocalFsInterface
    {
        /** @var FsInterface&LocalFsInterface&MockObject $fs */
        $fs = $this->createMockForIntersectionOfInterfaces([FsInterface::class, LocalFsInterface::class]);
        $fs->method('getRootPath')->willReturn($this->createTrackedTempDirectory('translation-local-volume-'));
        return $fs;
    }

    private function nonLocalFilesystem(): FsInterface & MockObject
    {
        return $this->createMock(FsInterface::class);
    }

    private function volume(FsInterface $fs): Volume & MockObject
    {
        $volume = $this->createMock(Volume::class);
        $volume->method('getFs')->willReturn($fs);
        return $volume;
    }

    private function installVolumes(?Volume ...$sequence): void
    {
        $volumes = $this->createMock(Volumes::class);
        $index = 0;
        $volumes->method('getVolumeByUid')->willReturnCallback(
            static function(string $uid) use (&$index, $sequence): ?Volume {
                $position = min($index, count($sequence) - 1);
                $index++;
                return $sequence[$position];
            },
        );
        Craft::$app->set('volumes', $volumes);
    }

    private function expectNoStorageIo(FsInterface & MockObject $fs): void
    {
        foreach ([
            'getFileList',
            'getFileSize',
            'getDateModified',
            'write',
            'read',
            'writeFileFromStream',
            'fileExists',
            'deleteFile',
            'renameFile',
            'copyFile',
            'getFileStream',
            'directoryExists',
            'createDirectory',
            'deleteDirectory',
            'renameDirectory',
        ] as $method) {
            $fs->expects(self::never())->method($method);
        }
    }
}
