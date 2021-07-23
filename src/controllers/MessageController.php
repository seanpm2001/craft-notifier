<?php
/**
 * Notifier plugin for Craft CMS
 *
 * Get notified when things happen.
 *
 * @author    Double Secret Agency
 * @link      https://plugins.doublesecretagency.com/
 * @copyright Copyright (c) 2021 Double Secret Agency
 */

namespace doublesecretagency\notifier\controllers;

use Craft;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\web\Controller;

/**
 * Class MessageController
 * @since 1.0.0
 */
class MessageController extends Controller
{

    /**
     * Save a notification message.
     */
    public function actionSave()
    {
        $this->requirePostRequest();
        $this->requireLogin();

        // Get request
        $request = Craft::$app->getRequest();

        // Get POST values
        $id        = $request->getBodyParam('id');
        $triggerId = $request->getBodyParam('triggerId');
        $type      = $request->getBodyParam('type');
        $template  = $request->getBodyParam('template');
        $config    = $request->getBodyParam('config');

        // JSON encode config
        $config = Json::encode($config);

        // Insert or update the Message Record
        Db::upsert('{{%notifier_messages}}', [
            'id' => $id,
        ], [
            'triggerId' => $triggerId,
            'type'      => $type,
            'template'  => $template,
            'config'    => $config,
        ], [], false);

        // Redirect to index of notifications
        return $this->redirect(UrlHelper::cpUrl('notifier'));
    }

}
