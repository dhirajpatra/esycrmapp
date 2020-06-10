<?php

namespace App\Admin\Inc;

use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Label;
use Google_Service_Gmail_LabelColor;
use Google_Service_Gmail_Message;
use Google_Service_Gmail_ModifyMessageRequest;
use PhpParser\Node\Stmt\Label;

// Load Composer's autoloader
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

/**
 * this will process all API for gmail
 */
class GmailApi
{
    /**
     * get access token from client
     */
    public function GetAccessToken($client_id, $redirect_uri, $client_secret, $code)
    {
        try {
            $url = 'https://accounts.google.com/o/oauth2/token';

            $curlPost = 'client_id=' . $client_id . '&redirect_uri=' . $redirect_uri . '&client_secret=' . $client_secret . '&code=' . $code . '&grant_type=authorization_code';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
            $data = json_decode(curl_exec($ch), true);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($http_code != 200) {
                throw new \Exception('Error : Failed to receive access token');
            }

            return $data;
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     */
    public function getClient($token = null)
    {
        $client = new Google_Client();
        $client->setAuthConfig('../gmail_credentials.json');

        // Load previously authorized token from a file, if it exists.
        // The file token.json stores the user's access and refresh tokens, and is
        // created automatically when the authorization flow completes for the first
        // time.
        if (!is_array($token)) {
            $accessToken = json_decode($token, true);
        } else {
            $accessToken = $token;
        }
        $client->setAccessToken($accessToken);

        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) {
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                // Exchange authorization code for an access token.
                // $accessToken = $client->fetchAccessTokenWithAuthCode($token);
                $client->setAccessToken($accessToken);

                // Check to see if there was an error.
                if (array_key_exists('error', $accessToken)) {
                    throw new \Exception(join(', ', $accessToken));
                }
            }
        }
        return $client;
    }

    /**
     * Add a new Label to user's mailbox.
     *
     * @param  Google_Service_Gmail $service Authorized Gmail API instance.
     * @param  string $userId User's email address. The special value 'me'
     * can be used to indicate the authenticated user.
     * @param  string $new_label_name Name of the new Label.
     * @return Google_Service_Gmail_Label Created Label.
     */
    public function createLabel($service, $user, $new_label_name, $bg_color = null)
    {
        $label = new Google_Service_Gmail_Label();
        $label->setName($new_label_name);

        if ($bg_color != null) {
            $color = new Google_Service_Gmail_LabelColor();
            $color->setBackgroundColor($bg_color);
            $color->setTextColor('#ffffff');
            $label->setColor($color);
        }

        try {
            $label = $service->users_labels->create($user, $label);
            return $label->getId();
        } catch (\Exception $e) {
            print 'An error occurred: ' . $e->getMessage();
        }
    }

    /**
     * this will return id of a label
     *
     * @param [type] $label
     * @return void
     */
    public function getLabelId($label)
    {
        try {
            $label = new Google_Service_Gmail_Label();
            $id = $label->getId($label);
            return $id;
        } catch (\Exception $e) {
            print 'An error occurred: ' . $e->getMessage();
        }
    }

    /**
     * Get Thread with given ID.
     *
     * @param  Google_Service_Gmail $service Authorized Gmail API instance.
     * @param  string $userId User's email address. The special value 'me'
     * can be used to indicate the authenticated user.
     * @param  string $threadId ID of Thread to get.
     * @return Google_Service_Gmail_Thread Retrieved Thread.
     */
    public function getThread($service, $userId, $threadId)
    {
        try {
            $thread = $service->users_threads->get($userId, $threadId);
            $messages = $thread->getMessages();
            // $msgCount = count($messages);
            return $messages;
        } catch (\Exception $e) {
            print 'An error occurred: ' . $e->getMessage();
        }
    }

    /**
     * Get all the Labels in the user's mailbox.
     *
     * @param  Google_Service_Gmail $service Authorized Gmail API instance.
     * @param  string $userId User's email address. The special value 'me'
     * can be used to indicate the authenticated user.
     * @return array Array of Labels.
     */
    public function listLabels($service, $userId)
    {
        try {
            $labelsResponse = $service->users_labels->listUsersLabels($userId);
            return $labelsResponse;
        } catch (\Exception $e) {
            print 'An error occurred: ' . $e->getMessage();
            exit;
        }
    }

    /**
     * Send Message.
     *
     * @param  Google_Service_Gmail $service Authorized Gmail API instance.
     * @param  string $userId User's email address. The special value 'me'
     * can be used to indicate the authenticated user.
     * @param  Google_Service_Gmail_Message $message Message to send.
     * @return Google_Service_Gmail_Message sent Message.
     */
    public function sendMessage($service, $userId, $mime_message, $label_id)
    {
        try {
            $msg = new Google_Service_Gmail_Message();
            $msg->setLabelIds([$label_id]);
            $msg->setRaw($mime_message);
            $message = $service->users_messages->send($userId, $msg);
            // print 'Message with ID: ' . $message->getId() . ' sent.';
            return $message;
        } catch (\Exception $e) {
            print 'An error occurred: ' . $e->getMessage();
        }
    }

    /**
     * craete message to send
     *
     * @param [type] $sender
     * @param [type] $to
     * @param [type] $subject
     * @param [type] $message_text
     * @return void
     */
    public function createMessage($sender, $to, $subject, $message_text)
    {
        try {
            $strRawMessage = "From: Email <$sender> \r\n";
            $strRawMessage .= "To: <$to>\r\n";
            $strRawMessage .= 'Subject: =?utf-8?B?' . base64_encode($subject) . "?=\r\n";
            $strRawMessage .= "MIME-Version: 1.0\r\n";
            $strRawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
            $strRawMessage .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
            $strRawMessage .= "$message_text\r\n";
            $mime_email = rtrim(strtr(base64_encode($strRawMessage), '+/', '-_'), '=');

            return $mime_email;
        } catch (\Exception $e) {
            print 'An error occurred: ' . $e->getMessage();
        }
    }

    /**
     * Modify the Labels a Message is associated with.
     *
     * @param  Google_Service_Gmail $service Authorized Gmail API instance.
     * @param  string $userId User's email address. The special value 'me'
     * can be used to indicate the authenticated user.
     * @param  string $messageId ID of Message to modify.
     * @param  array $labelsToAdd Array of Labels to add.
     * @param  array $labelsToRemove Array of Labels to remove.
     * @return Google_Service_Gmail_Message Modified Message.
     */
    public function modifyMessage($service, $userId, $messageId, $labelsToAdd, $labelsToRemove)
    {
        $mods = new Google_Service_Gmail_ModifyMessageRequest();
        $mods->setAddLabelIds($labelsToAdd);
        // $mods->setRemoveLabelIds($labelsToRemove);
        try {
            $message = $service->users_messages->modify($userId, $messageId, $mods);
            // print 'Message with ID: ' . $messageId . ' successfully modified.';
            return $message;
        } catch (\Exception $e) {
            print 'An error occurred: ' . $e->getMessage();
        }
    }

    /**
     * Get Message with given ID.
     *
     * @param  Google_Service_Gmail $service Authorized Gmail API instance.
     * @param  string $userId User's email address. The special value 'me'
     * can be used to indicate the authenticated user.
     * @param  string $messageId ID of Message to get.
     * @return Google_Service_Gmail_Message Message retrieved.
     */
    public function getMessage($service, $userId, $messageId)
    {
        try {
            $message = $service->users_messages->get($userId, $messageId);
            print 'Message with ID: ' . $message->getId() . ' retrieved.';
            return $message;
        } catch (\Exception $e) {
            print 'An error occurred: ' . $e->getMessage();
        }
    }

    /**
     * Create Draft email.
     *
     * @param  Google_Service_Gmail $service Authorized Gmail API instance.
     * @param  string $userId User's email address. The special value 'me'
     * can be used to indicate the authenticated user.
     * @param  Google_Service_Gmail_Message $message Message of the created Draft.
     * @return Google_Service_Gmail_Draft Created Draft.
     */
    public function createDraft($service, $user, $message)
    {
        $draft = new \Google_Service_Gmail_Draft();
        $draft->setMessage($message);
        try {
            $draft = $service->users_drafts->create($user, $draft);
            print 'Draft ID: ' . $draft->getId();
        } catch (\Exception $e) {
            print 'An error occurred: ' . $e->getMessage();
        }
        return $draft;
    }

    /**
     * Undocumented function
     *
     * @param [type] $service
     * @param [type] $user
     * @param [type] $draft_id
     * @return void
     */
    public function sendDraft($service, $user, $draft_id)
    {
        $draft = new \Google_Service_Gmail_Draft();
        $draft->setId($draft_id);
        try {
            // To update the Draft before sending, set a new Message on the Draft before sending.
            $service->users_drafts->sendDraft($user, $draft_id);
        } catch (\Exception $e) {
            print 'An error occurred: ' . $e->getMessage();
        }
    }

    /**
     * fetch user profile along with gmail id
     *
     * @return void
     */
    public function getUserProfile($service)
    {
        try {
            $user = 'me';
            // To update the Draft before sending, set a new Message on the Draft before sending.
            // same user's gmail id
            $email = $service->users->getProfile($user);

            return $email;
        } catch (\Exception $e) {
            print 'An error occurred: ' . $e->getMessage();
        }
    }
}