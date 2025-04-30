<?php
class SandCrime_Test_Notification_Digest extends WP_UnitTestCase {
    private $digest_manager;
    private $test_user_id;
    private $notification_handler;

    public function setUp() {
        parent::setUp();
        
        $this->digest_manager = new SandCrime_Notification_Digest_Manager();
        $this->notification_handler = new SandCrime_Notification_Handler();
        
        $this->test_user_id = $this->factory->user->create(array(
            'role' => 'subscriber',
            'user_email' => 'test_user@example.com'
        ));

        // Setup test user's notification preferences
        $this->notification_handler->update_user_settings($this->test_user_id, array(
            'email_enabled' => true,
            'email_frequency' => 'daily',
            'notification_types' => json_encode(array('test', 'alert'))
        ));
    }

    public function tearDown() {
        parent::tearDown();
        
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}sandcrime_notification_digests WHERE user_id = {$this->test_user_id}");
        $wpdb->query("DELETE FROM {$wpdb->prefix}sandcrime_notifications WHERE user_id = {$this->test_user_id}");
        
        wp_delete_user($this->test_user_id);
    }

    public function test_create_digest() {
        $notifications = $this->create_test_notifications(3);
        
        $digest_id = $this->digest_manager->create_digest(
            $this->test_user_id,
            'daily',
            $notifications
        );

        $this->assertIsInt($digest_id);
        $this->assertGreaterThan(0, $digest_id);

        global $wpdb;
        $digest = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sandcrime_notification_digests WHERE id = %d",
            $digest_id
        ));

        $this->assertNotNull($digest);
        $this->assertEquals('daily', $digest->frequency);
        $this->assertNull($digest->sent_at);

        $notification_ids = explode(',', $digest->notifications);
        $this->assertCount(3, $notification_ids);
    }

    public function test_process_digest() {
        $notifications = $this->create_test_notifications(5);
        
        $digest_id = $this->digest_manager->create_digest(
            $this->test_user_id,
            'daily',
            $notifications
        );

        add_filter('wp_mail', array($this, 'capture_mail'));
        $this->last_mail = null;

        $this->digest_manager->process_digest('daily');

        $this->assertNotNull($this->last_mail);
        $this->assertEquals('test_user@example.com', $this->last_mail['to']);
        $this->assertStringContainsString('Daily Notification Digest', $this->last_mail['subject']);

        global $wpdb;
        $digest = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sandcrime_notification_digests WHERE id = %d",
            $digest_id
        ));

        $this->assertNotNull($digest->sent_at);
    }

    public function test_group_notifications_by_type() {
        $notifications = array(
            $this->create_test_notification('test', 'Test 1'),
            $this->create_test_notification('alert', 'Alert 1'),
            $this->create_test_notification('test', 'Test 2'),
            $this->create_test_notification('alert', 'Alert 2')
        );

        $digest_id = $this->digest_manager->create_digest(
            $this->test_user_id,
            'daily',
            $notifications
        );

        add_filter('wp_mail', array($this, 'capture_mail'));
        $this->last_mail = null;

        $this->digest_manager->process_digest('daily');

        $this->assertNotNull($this->last_mail);
        $this->assertStringContainsString('Test Notifications', $this->last_mail['message']);
        $this->assertStringContainsString('Alert Notifications', $this->last_mail['message']);
    }

    public function test_digest_scheduling() {
        $notifications = $this->create_test_notifications(2);
        
        $digest_id = $this->digest_manager->create_digest(
            $this->test_user_id,
            'daily',
            $notifications
        );

        global $wpdb;
        $digest = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sandcrime_notification_digests WHERE id = %d",
            $digest_id
        ));

        // Verify scheduled_for is set to next delivery window
        $scheduled_time = strtotime($digest->scheduled_for);
        $now = current_time('timestamp');
        
        $this->assertGreaterThan($now, $scheduled_time);
        $this->assertEquals('06:00:00', date('H:i:s', $scheduled_time));
    }

    public function test_cleanup_old_digests() {
        global $wpdb;

        // Create old digest
        $wpdb->insert(
            $wpdb->prefix . 'sandcrime_notification_digests',
            array(
                'user_id' => $this->test_user_id,
                'frequency' => 'daily',
                'notifications' => '1,2,3',
                'created_at' => date('Y-m-d H:i:s', strtotime('-100 days')),
                'sent_at' => date('Y-m-d H:i:s', strtotime('-100 days'))
            )
        );

        // Create recent digest
        $wpdb->insert(
            $wpdb->prefix . 'sandcrime_notification_digests',
            array(
                'user_id' => $this->test_user_id,
                'frequency' => 'daily',
                'notifications' => '4,5,6',
                'created_at' => current_time('mysql'),
                'sent_at' => null
            )
        );

        $this->digest_manager->cleanup_old_digests();

        $remaining_digests = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sandcrime_notification_digests WHERE user_id = %d",
            $this->test_user_id
        ));

        $this->assertCount(1, $remaining_digests);
        $this->assertNull($remaining_digests[0]->sent_at);
    }

    private function create_test_notifications($count) {
        $notifications = array();
        for ($i = 0; $i < $count; $i++) {
            $notifications[] = $this->create_test_notification(
                'test',
                "Test Notification {$i}"
            );
        }
        return $notifications;
    }

    private function create_test_notification($type, $title) {
        return $this->notification_handler->create_notification(
            $this->test_user_id,
            array(
                'type' => $type,
                'title' => $title,
                'message' => "This is a test notification",
                'priority' => 'normal'
            )
        );
    }

    public function capture_mail($mail) {
        $this->last_mail = $mail;
        return $mail;
    }
}