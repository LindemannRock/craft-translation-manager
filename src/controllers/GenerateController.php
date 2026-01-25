<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Controller for generating translation files
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\TranslationManager;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Generate Controller
 *
 * @since 1.0.0
 */
class GenerateController extends Controller
{
    use LoggingTrait;

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        // Require any generate permission to access the generate page
        $user = Craft::$app->getUser();
        $hasGenerateAccess =
            $user->checkPermission('translationManager:generateTranslations') ||
            $user->checkPermission('translationManager:generateAllTranslations') ||
            $user->checkPermission('translationManager:generateFormieTranslations') ||
            $user->checkPermission('translationManager:generateSiteTranslations');

        if (!$hasGenerateAccess) {
            throw new ForbiddenHttpException('User does not have permission to generate translation files');
        }

        return parent::beforeAction($action);
    }

    /**
     * Generate index page
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionIndex(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();

        return $this->renderTemplate('translation-manager/generate/index', [
            'settings' => $settings,
        ]);
    }
}
