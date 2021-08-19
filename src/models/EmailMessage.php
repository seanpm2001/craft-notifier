<?php
/**
 * Notifier plugin for Craft CMS
 *
 * Send custom Twig messages when Craft events are triggered.
 *
 * @author    Double Secret Agency
 * @link      https://plugins.doublesecretagency.com/
 * @copyright Copyright (c) 2021 Double Secret Agency
 */

namespace doublesecretagency\notifier\models;

use Craft;
use craft\elements\User;
use craft\mail\Message as Email;
use craft\web\View;
use doublesecretagency\notifier\helpers\Log;
use Exception;

/**
 * Class EmailMessage
 * @since 1.0.0
 */
class EmailMessage extends Message
{

    /**
     * @var string Parent log message ID.
     */
    private $_logParent;

    /**
     * Send message to all recipients.
     */
    public function sendAll(array $data = [])
    {
        // Get all message recipients
        $recipients = $this->_getRecipients($data);

        // If no recipients were specified, bail
        if (!$recipients) {
            return;
        }

        // Send message to each recipient
        foreach ($recipients as $recipient) {
            $this->send($recipient, $data);
        }
    }

    /**
     * Send message to individual recipient.
     *
     * @param User|string $recipient
     * @param array $data
     */
    public function send($recipient, array $data = [])
    {
        // Whether recipient is a valid User or email address
        $validUser  = (is_object($recipient) && is_a($recipient, User::class));
        $validEmail = (is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL));

        // Get the recipient's email address
        $to = ($validUser ? $recipient->email: $recipient);

        // Log info message
        $this->_logParent = Log::info("Preparing to send email message to {$to}...");

        // If not a valid User or email address, bail
        if (!$validUser && !$validEmail) {
            // Set message based on whether recipient is a string.
            if (is_string($recipient)) {
                $message = "\"{$recipient}\" is not a valid email address.";
            } else {
                $message = "Recipient is not a valid User or email address.";
            }
            Log::warning($message, $this->_logParent);
            Log::error("Invalid recipient, email could not be sent.", $this->_logParent);
            return;
        }

        // Append recipient to data
        $data['recipient'] = $recipient;

        // Parse the message subject
        $subject = $this->_getSubject($data);

        // Parse the message body and subject
        $body = $this->_getBody($data);

        // If the message body was rejected, log error and bail
        if (false === $body) {
            Log::error("An error occurred while parsing the message body, and the email was not sent.", $this->_logParent);
            return;
        }

        // Send the email message
        $this->_dispatch($to, $subject, $body);
    }

    // ========================================================================= //

    /**
     * Send the actual email.
     *
     * @param $to
     * @param $subject
     * @param $body
     */
    private function _dispatch($to, $subject, $body)
    {
        // Compile email
        $email = new Email();
        $email->setTo($to);
        $email->setSubject($subject);
        $email->setHtmlBody($body);
        $email->setTextBody($body);

        // Send email
        $success = Craft::$app->getMailer()->send($email);

        // If unsuccessful, log error and bail
        if (!$success) {
            Log::warning("Unable to send the email using Craft's native email handling.", $this->_logParent);
            Log::error("Check your general email settings within Craft.", $this->_logParent);
            return;
        }

        // Log success message
        Log::success("The email to {$to} was sent successfully! ({$subject})", $this->_logParent);
    }

    // ========================================================================= //

    /**
     * Parse the message body.
     *
     * @param array $data
     * @return string|false Returns false if an error occurred.
     */
    private function _getBody(array $data)
    {
        // Get view services
        $view = Craft::$app->getView();

        // Get message body template
        $template = $this->template;

        // Parse the message body
        try {

            // Render message body template
            $body = $view->renderTemplate($template, $data, View::TEMPLATE_MODE_SITE);

        } catch (Exception $e) {

            // Log warning message
            Log::warning("Cannot compile message body from template. {$e->getMessage()}", $this->_logParent);

            // Return false
            return false;
        }

        // Return body
        return trim($body);
    }

    /**
     * Parse the message subject.
     */
    private function _getSubject(array $data): string
    {
        // Get view services
        $view = Craft::$app->getView();

        // Get message subject template
        $template = ($this->config['subject'] ?? '');

        // Parse the message subject
        try {

            // Get element (if one exists)
            $element =
                $data['entry'] ??
                $data['user'] ??
                $data['asset'] ??
                false;

            // If element exists
            if ($element) {
                // Render subject with object syntax
                $subject = $view->renderObjectTemplate($template, $element, $data, View::TEMPLATE_MODE_SITE);
            } else {
                // Render subject normally
                $subject = $view->renderString($template, $data, View::TEMPLATE_MODE_SITE);
            }

        } catch (Exception $e) {

            // Log a warning
            Log::warning("Cannot parse the message subject. {$e->getMessage()}", $this->_logParent);

            // Set subject to unparsed template
            $subject = $template;

        }

        // Return subject
        return trim($subject);
    }

    /**
     * Get an array of Users or email addresses.
     */
    private function _getRecipients(array $data): array
    {
        // Get recipients
        $recipients = ($this->config['recipients'] ?? []);

        // Switch according to recipient type
        switch ($recipients['type'] ?? false) {
            case 'all-users':
                return $this->_getRecipientsAllUsers();
            case 'all-admins':
                return $this->_getRecipientsAllAdmins();
            case 'all-users-in-group':
                return $this->_getRecipientsAllUsersInGroup();
            case 'specific-users':
//                return $this->_getRecipientsSpecificUsers();
            case 'specific-emails':
//                return $this->_getRecipientsSpecificEmails();
            case 'custom':
                return $this->_getRecipientsCustom($recipients, $data);
        }

        // Something went wrong, fallback to empty array
        return [];
    }

    /**
     * Get an array of email addresses based on a custom snippet.
     *
     * @return array
     */
    private function _getRecipientsCustom($recipients, array $data): array
    {
        // Switch according to recipient type
        switch ($recipients['custom']['type'] ?? false) {
            case 'users':
                $snippet = ($recipients['custom']['users'] ?? '');
                return $this->_parseUsersSnippet($snippet, $data);
            case 'emails':
                $snippet = ($recipients['custom']['emails'] ?? '');
                return $this->_parseEmailsSnippet($snippet, $data);
        }

        // Something went wrong, fallback to empty array
        return [];
    }

    // ========================================================================= //

    /**
     * Get an array of all Users.
     *
     * @return array
     */
    private function _getRecipientsAllUsers(): array
    {
        // Log info message
        $this->_logParent = Log::info("Collecting all system Users as recipients...");

        // Return all active Users
        return User::find()->all();
    }

    /**
     * Get an array of all Admin Users.
     *
     * @return array
     */
    private function _getRecipientsAllAdmins(): array
    {
        // Log info message
        $this->_logParent = Log::info("Collecting all system Admins as recipients...");

        // Return all active admin Users
        return User::find()->admin()->all();
    }

    /**
     * Get an array of Users in selected group(s).
     *
     * @return array
     */
    private function _getRecipientsAllUsersInGroup(): array
    {
        // Log info message
        $this->_logParent = Log::info("Collecting recipients based on specified user groups...");

        // Get selected user groups
        $groups = ($this->config['recipients']['groups'] ?? false);

        // If no groups, return empty array
        if (!$groups) {
            Log::warning("No user groups were selected.", $this->_logParent);
            Log::error("Recipients could not be determined, therefore no emails were sent.", $this->_logParent);
            return [];
        }

        // If all groups selected
        if ('*' === $groups) {
            // Re-initialize as empty array
            $groups = [];
            // Collect ID of every existing user group
            foreach (Craft::$app->getUserGroups()->getAllGroups() as $group) {
                $groups[] = $group->id;
            }
        }

        // If not a valid array of IDs, return empty array
        if (!is_array($groups)) {
            Log::warning("The specified user groups are not valid.", $this->_logParent);
            Log::error("Recipients could not be determined, therefore no emails were sent.", $this->_logParent);
            return [];
        }

        // Return all Users in specified group(s)
        return User::find()->groupId($groups)->all();
    }

    // ========================================================================= //

    /**
     * Use a custom snippet to get a collection of Users' email addresses.
     *
     * @param string $snippet
     * @return array
     */
    private function _parseUsersSnippet(string $snippet, array $data): array
    {
        // Log info message
        $this->_logParent = Log::info("Determining which Users based on custom Twig snippet...");

        // Get view services
        $view = Craft::$app->getView();

        // Parse the custom users snippet
        try {

            // Dynamically generated collection of User IDs
            $results = $view->renderString($snippet, $data);

            // If no IDs, bail
            if (!$results) {
                return [];
            }

            // Get array of User IDs
            $ids = $this->_jsonDecode($results);

            // Return all specified Users
            return User::find()
                ->id($ids)
                ->all();

        } catch (Exception $e) {

            // Log an error
            Log::warning("Cannot parse the Twig snippet to determine custom Users. {$e->getMessage()}", $this->_logParent);
            Log::error("Recipients could not be determined, therefore no emails were sent.", $this->_logParent);

            // Return an empty array
            return [];
        }
    }

    /**
     * Use a custom snippet to get a collection of email addresses.
     *
     * @param string $snippet
     * @return array
     */
    private function _parseEmailsSnippet(string $snippet, array $data): array
    {
        // Log info message
        $this->_logParent = Log::info("Determining which email addresses based on custom Twig snippet...");

        // Get view services
        $view = Craft::$app->getView();

        // Parse the custom emails snippet
        try {

            // Dynamically generated collection of email addresses
            $results = $view->renderString($snippet, $data);

            // If no email addresses, bail
            if (!$results) {
                return [];
            }

            // Return array of email addresses
            return $this->_jsonDecode($results);

        } catch (Exception $e) {

            // Log an error
            Log::warning("Cannot parse the Twig snippet to determine custom email addresses. {$e->getMessage()}", $this->_logParent);
            Log::error("Recipients could not be determined, therefore no emails were sent.", $this->_logParent);

            // Return an empty array
            return [];
        }
    }

}
