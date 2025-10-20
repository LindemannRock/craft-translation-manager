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
use lindemannrock\translationmanager\TranslationManager;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use yii\web\Response;
use yii\web\ForbiddenHttpException;

/**
 * Generate Controller
 */
class GenerateController extends Controller
{
    use LoggingTrait;

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        // Require permission to view translations
        if (!Craft::$app->getUser()->checkPermission('translationManager:viewTranslations')) {
            throw new ForbiddenHttpException('User does not have permission to access generate');
        }

        return parent::beforeAction($action);
    }

    /**
     * Generate index page
     */
    public function actionIndex(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();

        return $this->renderTemplate('translation-manager/generate/index', [
            'settings' => $settings,
        ]);
    }
}
