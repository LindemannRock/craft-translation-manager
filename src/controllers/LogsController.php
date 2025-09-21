<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Controller for viewing plugin logs
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\controllers;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use lindemannrock\translationmanager\TranslationManager;
use yii\web\ForbiddenHttpException;

/**
 * Logs Controller
 */
class LogsController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|int|bool $allowAnonymous = false;

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        // Require view translations permission to access logs
        if (!Craft::$app->getUser()->checkPermission('translationManager:viewTranslations')) {
            throw new ForbiddenHttpException('User does not have permission to view logs');
        }

        return parent::beforeAction($action);
    }

    /**
     * Display logs with pagination
     */
    public function actionIndex(): Response
    {
        $request = Craft::$app->getRequest();

        // Get filter parameters
        $level = $request->getParam('level', 'all');
        $date = $request->getParam('date', (new \DateTime())->format('Y-m-d'));
        $search = $request->getParam('search', '');
        $page = (int) $request->getParam('page', 1);
        $limit = 50; // Entries per page

        // Get available log files
        $logFiles = $this->_getAvailableLogFiles();

        // Read and parse log entries
        $logEntries = $this->_getLogEntries($date, $level, $search, $page, $limit);

        // Get total count for pagination
        $totalEntries = $this->_getLogEntriesCount($date, $level, $search);

        // Calculate pagination info
        $totalPages = ceil($totalEntries / $limit);

        return $this->renderTemplate('translation-manager/logs/index', [
            'pluginName' => TranslationManager::getInstance()->getSettings()->pluginName ?? 'Translation Manager',
            'logFiles' => $logFiles,
            'logEntries' => $logEntries,
            'filters' => [
                'level' => $level,
                'date' => $date,
                'search' => $search,
                'page' => $page,
            ],
            'pagination' => [
                'total' => $totalEntries,
                'perPage' => $limit,
                'currentPage' => $page,
                'totalPages' => $totalPages,
            ],
            'levels' => [
                'all' => 'All Levels',
                'error' => 'Error',
                'warning' => 'Warning',
                'info' => 'Info',
                'trace' => 'Trace',
            ],
        ]);
    }

    /**
     * Download a log file
     */
    public function actionDownload(): Response
    {
        $date = Craft::$app->getRequest()->getRequiredParam('date');

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \InvalidArgumentException('Invalid date format');
        }

        $logPath = Craft::$app->getPath()->getLogPath() . "/translation-manager-{$date}.log";

        if (!file_exists($logPath)) {
            throw new \Exception('Log file not found');
        }

        return Craft::$app->getResponse()->sendFile($logPath, "translation-manager-{$date}.log", [
            'mimeType' => 'text/plain',
            'inline' => false,
        ]);
    }

    /**
     * Get available log files
     */
    private function _getAvailableLogFiles(): array
    {
        $logPath = Craft::$app->getPath()->getLogPath();
        $files = [];

        if (is_dir($logPath)) {
            $pattern = $logPath . '/translation-manager-*.log';
            $logFiles = glob($pattern);

            foreach ($logFiles as $file) {
                if (preg_match('/translation-manager-(\d{4}-\d{2}-\d{2})\.log$/', basename($file), $matches)) {
                    $date = $matches[1];
                    $files[$date] = [
                        'date' => $date,
                        'size' => filesize($file),
                        'formattedSize' => Craft::$app->getFormatter()->asShortSize(filesize($file)),
                        'lastModified' => filemtime($file),
                    ];
                }
            }
        }

        // Sort by date descending
        krsort($files);

        return array_values($files);
    }

    /**
     * Get log entries for a specific date with filtering and pagination
     */
    private function _getLogEntries(string $date, string $level, string $search, int $page, int $limit): array
    {
        $logPath = Craft::$app->getPath()->getLogPath() . "/translation-manager-{$date}.log";

        if (!file_exists($logPath)) {
            return [];
        }

        $entries = [];
        $lineNumber = 0;
        $filteredEntries = [];

        // First pass: collect all filtered entries
        if (($handle = fopen($logPath, 'r')) !== false) {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $entry = $this->_parsLogEntry(trim($line), $lineNumber);

                if (!$entry) {
                    continue;
                }

                // Filter by level
                if ($level !== 'all' && $entry['level'] !== $level) {
                    continue;
                }

                // Filter by search
                if ($search && stripos($entry['message'] . ' ' . $entry['context'], $search) === false) {
                    continue;
                }

                $filteredEntries[] = $entry;
            }
            fclose($handle);
        }

        // Reverse to show newest first
        $filteredEntries = array_reverse($filteredEntries);

        // Apply pagination
        $offset = ($page - 1) * $limit;
        return array_slice($filteredEntries, $offset, $limit);
    }

    /**
     * Get total count of log entries for pagination
     */
    private function _getLogEntriesCount(string $date, string $level, string $search): int
    {
        $logPath = Craft::$app->getPath()->getLogPath() . "/translation-manager-{$date}.log";

        if (!file_exists($logPath)) {
            return 0;
        }

        $count = 0;

        if (($handle = fopen($logPath, 'r')) !== false) {
            while (($line = fgets($handle)) !== false) {
                $entry = $this->_parsLogEntry(trim($line));

                if (!$entry) {
                    continue;
                }

                // Filter by level
                if ($level !== 'all' && $entry['level'] !== $level) {
                    continue;
                }

                // Filter by search
                if ($search && stripos($entry['message'] . ' ' . $entry['context'], $search) === false) {
                    continue;
                }

                $count++;
            }
            fclose($handle);
        }

        return $count;
    }

    /**
     * Parse a log entry line
     */
    private function _parsLogEntry(string $line, int $lineNumber = 0): ?array
    {
        // Skip empty lines
        if (empty($line)) {
            return null;
        }

        // Parse log format: timestamp [user:id][level][category] message | context
        // Also handle format without context (message only)
        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\s+\[(.*?)\]\[(.*?)\]\[(.*?)\]\s+(.*?)(?:\s+\|\s+(.*))?$/', $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'user' => $matches[2],
                'level' => strtolower(str_replace('.', '', $matches[3])),
                'category' => $matches[4],
                'message' => $matches[5],
                'context' => isset($matches[6]) ? $matches[6] : '',
                'lineNumber' => $lineNumber,
                'raw' => $line,
            ];
        }

        // Fallback for non-standard format
        return [
            'timestamp' => '',
            'user' => '',
            'level' => 'unknown',
            'category' => '',
            'message' => $line,
            'context' => '',
            'lineNumber' => $lineNumber,
            'raw' => $line,
        ];
    }
}