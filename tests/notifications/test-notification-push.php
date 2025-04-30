<?php
class SandCrime_Test_Notification_Push extends WP_UnitTestCase {
    private $push_manager;
    private $test_user_id;

    public function setUp() {
        parent::setUp();
        
        $this->push_manager = new SandCrime_Push_Notification_Manager();
        
        $this->test_user_id = $this->factory->user->create(array(
            'role' => 'subscriber'
        ));

        // Setup test VAPID keys if not exists
        if (!get_option('sandcrime_vapid_public_key')) {
            $keys = SandCrime_Push_Notification_Manager::generate_vapid_keys();
            update_option('sandcrime_vapid_public_key', $keys['publicKey']);
            update_option('sandcrime_vapid_private_key', $keys['privateKey']);
        }
    }

    public function tearDown() {
        parent::tearDown();
        
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}sandcrime_push_subscriptions WHERE user_id = {$this->test_user_id}");
        
        wp_delete_user($this->test_user_id);
    }

    public function test_update_subscription() {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        
        $subscription_data = array(
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
            'keys' => array(
                'p256dh' => base64_encode(random_bytes(32)),
                'auth' => base64_encode(random_bytes(16))
            )
        );

        $_POST['nonce'] = wp_create_nonce('sandcrime_notifications');
        $_POST['subscription'] = json_encode($subscription_data);

        $result = $this->push_manager->handle_subscription_update();
        $this->assertTrue($result['success']);

        global $wpdb;
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sandcrime_push_subscriptions WHERE user_id = %d",
            $this->test_user_id
        ));

        $this->assertNotNull($subscription);
        $this->assertEquals($subscription_data['endpoint'], $subscription->endpoint);
        $this->assertEquals('windows', $subscription->platform);
    }

    public function test_send_notification() {
        $subscription_id = $this->create_test_subscription();

        $payload = array(
            'title' => 'Test Push Notification',
            'message' => 'This is a test push notification',
            'type' => 'test',
            'priority' => 'normal'
        );

        $results = $this->push_manager->send_notification_to_user(
            $this->test_user_id,
            $payload
        );

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals($subscription_id, $results[0]['subscription_id']);
    }

    public function test_cleanup_old_subscriptions() {
        global $wpdb;

        // Create old subscription
        $wpdb->insert(
            $wpdb->prefix . 'sandcrime_push_subscriptions',
            array(
                'user_id' => $this->test_user_id,
                'endpoint' => 'https://test-endpoint-old',
                'public_key' => 'test_key',
                'auth_token' => 'test_token',
                'last_used' => date('Y-m-d H:i:s', strtotime('-31 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-31 days'))
            )
        );

        // Create recent subscription
        $wpdb->insert(
            $wpdb->prefix . 'sandcrime_push_subscriptions',
            array(
                'user_id' => $this->test_user_id,
                'endpoint' => 'https://test-endpoint-new',
                'public_key' => 'test_key',
                'auth_token' => 'test_token',
                'last_used' => current_time('mysql'),
                'created_at' => current_time('mysql')
            )
        );

        $this->push_manager->cleanup_old_subscriptions();

        $remaining_subscriptions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sandcrime_push_subscriptions WHERE user_id = %d",
            $this->test_user_id
        ));

        $this->assertCount(1, $remaining_subscriptions);
        $this->assertEquals('https://test-endpoint-new', $remaining_subscriptions[0]->endpoint);
    }

    private function create_test_subscription() {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'sandcrime_push_subscriptions',
            array(
                'user_id' => $this->test_user_id,
                'endpoint' => 'https://test-endpoint',
                'public_key' => base64_encode(random_bytes(32)),
                'auth_token' => base64_encode(random_bytes(16)),
                'user_agent' => 'Test Browser',
                'platform' => 'test',
                'language' => 'en',
                'last_used' => current_time('mysql'),
                'created_at' => current_time('mysql')
            )
        );

        return $wpdb->insert_id;
    }
}