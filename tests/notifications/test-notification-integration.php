<?php
class SandCrime_Test_Notification_Integration extends WP_UnitTestCase {
    private $notification_handler;
    private $queue_manager;
    private $digest_manager;
    private $push_manager;
    private $test_user_id;

    public function setUp() {
        parent::setUp();
        
        $this->notification_handler = new SandCrime_Notification_Handler();
        $this->queue_manager = new SandCrime_Notification_Queue_Manager();
        $this->digest_manager = new SandCrime_Notification_Digest_Manager();
        $this->push_manager = new SandCrime_Push_Notification_Manager();
        
        $this->test_user_id = $this->factory->user->create(array(
            'role' => 'subscriber',
            'user_email' => 'test_integration@example.com'
        ));

        // Setup user preferences
        $this->notification_handler->update_user_settings($this->test_user_id, array(
            'email_enabled' => true,
            'email_frequency' => 'daily',
            'push_enabled' => true,
            'notification_types' => json_encode(array('test', 'alert')),
            'priority_threshold' => 'normal'
        ));
    }

    public function tearDown() {
        parent::tearDown();
        
        global $wpdb;
        $tables = array(
            'sandcrime_notifications',
            'sandcrime_notification_queue',
            'sandcrime_notification_digests',
            'sandcrime_push_subscriptions'
        );

        foreach ($tables as $table) {
            $wpdb->query("DELETE FROM {$wpdb->prefix}{$table} WHERE user_id = {$this->test_user_id}");
        }
        
        wp_delete_user($this->test_user_id);
    }

    public function test_complete_notification_flow() {
        // 1. Create notification
        $notification_id = $this->notification_handler->create_notification(
            $this->test_user_id,
            array(
                'type' => 'test',
                'title' => 'Integration Test Notification',
                'message' => 'Testing complete notification flow',
                'priority' => 'high'
            )
        );

        $this->assertIsInt($notification_id);

        // 2. Queue notification for delivery
        $queue_id = $this->queue_manager->enqueue_notification(
            $notification_id,
            $this->test_user_id,
            'email',
            array('priority' => 'high')
        );

        $this->assertIsInt($queue_id);

        // 3. Create push subscription
        $subscription_data = $this->create_test_subscription();
        $subscription_id = $this->push_manager->update_subscription(
            $this->test_user_id,
            $subscription_data
        );

        $this->assertIsInt($subscription_id);

        // 4. Process notification queue
        add_filter('wp_mail', array($this, 'capture_mail'));
        $this->last_mail = null;

        $this->queue_manager->process_queue();

        // Verify email was sent
        $this->assertNotNull($this->last_mail);
        $this->assertEquals('test_integration@example.com', $this->last_mail['to']);

        // 5. Create digest
        $digest_id = $this->digest_manager->create_digest(
            $this->test_user_id,
            'daily',
            array($notification_id)
        );

        $this->assertIsInt($digest_id);

        // 6. Process digest
        $this->last_mail = null;
        $this->digest_manager->process_digest('daily');

        // Verify digest email was sent
        $this->assertNotNull($this->last_mail);
        $this->assertStringContainsString('Daily Notification Digest', $this->last_mail['subject']);

        // 7. Verify notification status
        $notification = $this->notification_handler->get_notification($notification_id);
        $this->assertTrue($notification->included_in_digest);

        // 8. Test push notification delivery
        $push_result = $this->push_manager->send_notification_to_user(
            $this->test_user_id,
            array(
                'title' => 'Push Test',
                'message' => 'Testing push notification',
                'type' => 'test'
            )
        );

        $this->assertIsArray($push_result);
        $this->assertCount(1, $push_result);
        $this->assertEquals('success', $push_result[0]['status']);
    }

    public function test_notification_preferences_flow() {
        // 1. Update user preferences
        $new_settings = array(
            'email_enabled' => true,
            'email_frequency' => 'weekly',
            'push_enabled' => true,
            'notification_types' => json_encode(array('alert')),
            'priority_threshold' => 'high',
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => '22:00:00',
            'quiet_hours_end' => '07:00:00'
        );

        $result = $this->notification_handler->update_user_settings(
            $this->test_user_id,
            $new_settings
        );

        $this->assertTrue($result);

        // 2. Create notifications of different types
        $notifications = array(
            array('type' => 'test', 'priority' => 'normal'),
            array('type' => 'alert', 'priority' => 'high')
        );

        foreach ($notifications as $notification_data) {
            $notification_data += array(
                'title' => "Test {$notification_data['type']}",
                'message' => "Test message"
            );

            $this->notification_handler->create_notification(
                $this->test_user_id,
                $notification_data
            );
        }

        // 3. Verify notification filtering based on preferences
        $filtered_notifications = $this->notification_handler->get_user_notifications(
            $this->test_user_id,
            array(
                'respect_preferences' => true
            )
        );

        $this->assertCount(1, $filtered_notifications);
        $this->assertEquals('alert', $filtered_notifications[0]->type);
        $this->assertEquals('high', $filtered_notifications[0]->priority);
    }

    private function create_test_subscription() {
        return array(
            'endpoint' => 'https://test-endpoint-' . uniqid(),
            'keys' => array(
                'p256dh' => base64_encode(random_bytes(32)),
                'auth' => base64_encode(random_bytes(16))
            )
        );
    }

    public function capture_mail($mail) {
        $this->last_mail = $mail;
        return $mail;
    }
}