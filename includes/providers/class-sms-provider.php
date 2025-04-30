<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_SMS_Provider implements SandCrime_Provider_Interface {
    private $settings;
    private $client;
    private $provider;

    public function __construct($settings) {
        $this->settings = $settings;
        $this->provider = $this->settings->get_setting('sms.provider');
        $this->initialize_client();
    }

    private function initialize_client() {
        switch ($this->provider) {
            case 'twilio':
                $this->initialize_twilio();
                break;
            case 'messagebird':
                $this->initialize_messagebird();
                break;
            case 'vonage':
                $this->initialize_vonage();
                break;
            default:
                throw new Exception(__('Unsupported SMS provider', 'sandcrime'));
        }
    }

    private function initialize_twilio() {
        require_once SANDCRIME_PLUGIN_DIR . 'includes/lib/twilio/autoload.php';

        $account_sid = $this->settings->get_setting('sms.twilio_sid');
        $auth_token = $this->settings->get_setting('sms.twilio_token');

        if (empty($account_sid) || empty($auth_token)) {
            throw new Exception(__('Twilio credentials not configured', 'sandcrime'));
        }

        try {
            $this->client = new Twilio\Rest\Client($account_sid, $auth_token);
        } catch (Exception $e) {
            throw new Exception(sprintf(
                __('Failed to initialize Twilio client: %s', 'sandcrime'),
                $e->getMessage()
            ));
        }
    }

    private function initialize_messagebird() {
        require_once SANDCRIME_PLUGIN_DIR . 'includes/lib/messagebird/autoload.php';

        $api_key = $this->settings->get_setting('sms.messagebird_key');

        if (empty($api_key)) {
            throw new Exception(__('MessageBird API key not configured', 'sandcrime'));
        }

        try {
            $this->client = new MessageBird\Client($api_key);
        } catch (Exception $e) {
            throw new Exception(sprintf(
                __('Failed to initialize MessageBird client: %s', 'sandcrime'),
                $e->getMessage()
            ));
        }
    }

    private function initialize_vonage() {
        require_once SANDCRIME_PLUGIN_DIR . 'includes/lib/vonage/autoload.php';

        $api_key = $this->settings->get_setting('sms.vonage_key');
        $api_secret = $this->settings->get_setting('sms.vonage_secret');

        if (empty($api_key) || empty($api_secret)) {
            throw new Exception(__('Vonage credentials not configured', 'sandcrime'));
        }

        try {
            $basic = new Vonage\Client\Credentials\Basic($api_key, $api_secret);
            $this->client = new Vonage\Client($basic);
        } catch (Exception $e) {
            throw new Exception(sprintf(
                __('Failed to initialize Vonage client: %s', 'sandcrime'),
                $e->getMessage()
            ));
        }
    }

    public function send($notification) {
        try {
            // Validate phone number
            $phone_number = $this->validate_phone_number($notification['recipient']);

            // Prepare message
            $message = $this->prepare_message($notification);

            // Send based on provider
            switch ($this->provider) {
                case 'twilio':
                    return $this->send_twilio($phone_number, $message, $notification);
                case 'messagebird':
                    return $this->send_messagebird($phone_number, $message, $notification);
                case 'vonage':
                    return $this->send_vonage($phone_number, $message, $notification);
                default:
                    throw new Exception(__('Unsupported SMS provider', 'sandcrime'));
            }
        } catch (Exception $e) {
            throw new Exception(sprintf(
                __('SMS sending failed: %s', 'sandcrime'),
                $e->getMessage()
            ));
        }
    }

    private function validate_phone_number($phone_number) {
        // Remove any non-digit characters except leading +
        $cleaned = preg_replace('/[^0-9+]/', '', $phone_number);

        // Validate format
        if (!preg_match('/^\+?[1-9]\d{1,14}$/', $cleaned)) {
            throw new Exception(__('Invalid phone number format', 'sandcrime'));
        }

        // Ensure number has international format
        if (strpos($cleaned, '+') !== 0) {
            $cleaned = '+' . $cleaned;
        }

        return $cleaned;
    }

    private function prepare_message($notification) {
        $message = $notification['message'];

        // Add prefix if configured
        $prefix = $this->settings->get_setting('sms.message_prefix');
        if (!empty($prefix)) {
            $message = $prefix . ' ' . $message;
        }

        // Add suffix if configured
        $suffix = $this->settings->get_setting('sms.message_suffix');
        if (!empty($suffix)) {
            $message .= ' ' . $suffix;
        }

        // Check message length and truncate if necessary
        $max_length = $this->get_max_message_length();
        if (mb_strlen($message) > $max_length) {
            $message = mb_substr($message, 0, $max_length - 3) . '...';
        }

        return $message;
    }

    private function send_twilio($to, $message, $notification) {
        try {
            $from = $this->settings->get_setting('sms.twilio_number');
            
            $response = $this->client->messages->create($to, array(
                'from' => $from,
                'body' => $message,
                'statusCallback' => $this->get_status_callback_url('twilio', $notification['id'])
            ));

            return array(
                'success' => true,
                'message_id' => $response->sid,
                'response' => array(
                    'status' => $response->status,
                    'direction' => $response->direction,
                    'date_created' => $response->dateCreated->format('Y-m-d H:i:s'),
                    'date_sent' => $response->dateSent ? $response->dateSent->format('Y-m-d H:i:s') : null
                )
            );
        } catch (Twilio\Exceptions\TwilioException $e) {
            throw new Exception($e->getMessage());
        }
    }

    private function send_messagebird($to, $message, $notification) {
        try {
            $from = $this->settings->get_setting('sms.messagebird_originator');
            
            $message = new MessageBird\Objects\Message();
            $message->originator = $from;
            $message->recipients = array($to);
            $message->body = $message;
            $message->reportUrl = $this->get_status_callback_url('messagebird', $notification['id']);

            $response = $this->client->messages->create($message);

            return array(
                'success' => true,
                'message_id' => $response->id,
                'response' => array(
                    'status' => $response->status,
                    'type' => $response->type,
                    'created_datetime' => $response->createdDatetime
                )
            );
        } catch (MessageBird\Exceptions\MessageBirdException $e) {
            throw new Exception($e->getMessage());
        }
    }

    private function send_vonage($to, $message, $notification) {
        try {
            $from = $this->settings->get_setting('sms.vonage_from');
            
            $response = $this->client->sms()->send(
                new Vonage\SMS\Message\SMS(
                    $to,
                    $from,
                    $message,
                    array(
                        'callback' => $this->get_status_callback_url('vonage', $notification['id'])
                    )
                )
            );

            $message = $response->current();

            return array(
                'success' => $message->getStatus() === 0,
                'message_id' => $message->getMessageId(),
                'response' => array(
                    'status' => $message->getStatus(),
                    'remaining_balance' => $message->getRemainingBalance(),
                    'message_price' => $message->getMessagePrice(),
                    'network' => $message->getNetwork()
                )
            );
        } catch (Vonage\Client\Exception\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    private function get_max_message_length() {
        switch ($this->provider) {
            case 'twilio':
                return 1600; // Twilio segments messages > 160 chars
            case 'messagebird':
                return 1377; // MessageBird concatenates up to 9 segments
            case 'vonage':
                return 1224; // Vonage allows up to 8 segments
            default:
                return 160; // Standard SMS length
        }
    }

    private function get_status_callback_url($provider, $message_id) {
        return add_query_arg(array(
            'action' => 'sandcrime_sms_callback',
            'provider' => $provider,
            'message_id' => $message_id,
            'token' => wp_create_nonce('sandcrime_sms_callback_' . $message_id)
        ), admin_url('admin-ajax.php'));
    }

    public function handle_status_callback($provider, $data) {
        switch ($provider) {
            case 'twilio':
                $this->handle_twilio_callback($data);
                break;
            case 'messagebird':
                $this->handle_messagebird_callback($data);
                break;
            case 'vonage':
                $this->handle_vonage_callback($data);
                break;
        }
    }

    private function handle_twilio_callback($data) {
        if (empty($data['MessageSid']) || empty($data['MessageStatus'])) {
            return;
        }

        $this->update_message_status(
            $data['MessageSid'],
            $this->normalize_status('twilio', $data['MessageStatus']),
            $data
        );
    }

    private function handle_messagebird_callback($data) {
        if (empty($data['id']) || empty($data['status'])) {
            return;
        }

        $this->update_message_status(
            $data['id'],
            $this->normalize_status('messagebird', $data['status']),
            $data
        );
    }

    private function handle_vonage_callback($data) {
        if (empty($data['messageId']) || empty($data['status'])) {
            return;
        }

        $this->update_message_status(
            $data['messageId'],
            $this->normalize_status('vonage', $data['status']),
            $data
        );
    }

    private function normalize_status($provider, $status) {
        $status_map = array(
            'twilio' => array(
                'delivered' => 'delivered',
                'failed' => 'failed',
                'undelivered' => 'failed',
                'sent' => 'sent'
            ),
            'messagebird' => array(
                'delivered' => 'delivered',
                'failed' => 'failed',
                'expired' => 'failed',
                'sent' => 'sent'
            ),
            'vonage' => array(
                'delivered' => 'delivered',
                'failed' => 'failed',
                'expired' => 'failed',
                'accepted' => 'sent'
            )
        );

        return isset($status_map[$provider][$status]) ? 
            $status_map[$provider][$status] : 
            'unknown';
    }

    private function update_message_status($message_id, $status, $metadata = array()) {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'sandcrime_sms_tracking',
            array(
                'status' => $status,
                'updated_at' => current_time('mysql'),
                'metadata' => json_encode($metadata)
            ),
            array('message_id' => $message_id),
            array('%s', '%s', '%s'),
            array('%s')
        );

        do_action('sandcrime_sms_status_updated', $message_id, $status, $metadata);
    }
}