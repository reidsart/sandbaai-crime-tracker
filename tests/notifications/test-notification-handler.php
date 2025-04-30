<?php
class SandCrime_Test_Notification_Handler extends WP_UnitTestCase {
    private $notification_handler;
    private $test_user_id;

    public function setUp() {
        parent::setUp();
        
        $this->notification_handler = new SandCrime_Notification_Handler();
        
        // Create test user
        $this->test_user_id = $this->factory->user->create(array(
            'role' => 'subscriber'
        ));
    }

    public function tearDown() {
        parent::tearDown();
        
        // Cleanup test data
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}sandcrime_notifications WHERE user_id = {$this->test_user_id}");
        $wpdb->query("DELETE FROM {$wpdb->prefix}sandcrime_notification_settings WHERE user_id = {$this->test_user_id}");
        
        wp_delete_user($this->test_user_id);
    }

    public function test_create_notification() {
        $notification_data = array(
            'type' => 'test',
            'title' => 'Test Notification',
            'message' => 'This is a test notification',
            'priority' => 'normal'
        );

        $notification_id = $this->notification_handler->create_notification(
            $this->test_user_id,
            $notification_data
        );

        $this->assertIsInt($notification_id);
        $this->assertGreaterThan(0, $notification_id);

        global $wpdb;
        $notification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sandcrime_notifications WHERE id = %d",
            $notification_id
        ));

        $this->assertNotNull($notification);
        $this->assertEquals($notification_data['title'], $notification->title);
        $this->assertEquals($notification_data['message'], $notification->message);
        $this->assertEquals($notification_data['type'], $notification->type);
        $this->assertEquals($notification_data['priority'], $notification->priority);
    }

    public function test_get_user_notifications() {
        // Create multiple test notifications
        $test_notifications = array(
            array(
                'type' => 'test1',
                'title' => 'Test 1',
                'message' => 'Test message 1',
                'priority' => 'high'
            ),
            array(
                'type' => 'test2',
                'title' => 'Test 2',
                'message' => 'Test message 2',
                'priority' => 'normal'
            )
        );

        foreach ($test_notifications as $notification_data) {
            $this->notification_handler->create_notification(
                $this->test_user_id,
                $notification_data
            );
        }

        $notifications = $this->notification_handler->get_user_notifications(
            $this->test_user_id,
            array('limit' => 10)
        );

        $this->assertCount(2, $notifications);
        $this->assertEquals('Test 1', $notifications[0]->title);
        $this->assertEquals('Test 2', $notifications[1]->title);
    }

    public function test_update_notification_settings() {
        $settings = array(
            'email_enabled' => true,
            'email_frequency' => 'daily',
            'push_enabled' => false,
            'notification_types' => json_encode(array('test1', 'test2')),
            'priority_threshold' => 'normal',
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => '22:00:00',
            'quiet_hours_end' => '07:00:00',
            'timezone' => 'UTC'
        );

        $result = $this->notification_handler->update_user_settings(
            $this->test_user_id,
            $settings
        );

        $this->assertTrue($result);

        $saved_settings = $this->notification_handler->get_user_settings($this->test_user_id);
        
        $this->assertEquals($settings['email_enabled'], $saved_settings['email_enabled']);
        $this->assertEquals($settings['email_frequency'], $saved_settings['email_frequency']);
        $this->assertEquals($settings['push_enabled'], $saved_settings['push_enabled']);
        $this->assertEquals($settings['notification_types'], $saved_settings['notification_types']);
    }

    public function test_notification_validation() {
        $invalid_data = array(
            'type' => '', // Empty type
            'title' => str_repeat('a', 300), // Too long title
            'message' => 'Test message',
            'priority' => 'invalid_priority' // Invalid priority
        );

        $this->expectException('InvalidArgumentException');
        $this->notification_handler->create_notification(
            $this->test_user_id,
            $invalid_data
        );
    }

    public function test_notification_filtering() {
        // Create notifications with different priorities
        $priorities = array('low', 'normal', 'high', 'urgent');
        foreach ($priorities as $priority) {
            $this->notification_handler->create_notification(
                $this->test_user_id,
                array(
                    'type' => 'test',
                    'title' => "Test {$priority}",
                    'message' => "Test message",
                    'priority' => $priority
                )
            );
        }

        // Test filtering by priority
        $high_priority = $this->notification_handler->get_user_notifications(
            $this->test_user_id,
            array(
                'priority' => array('high', 'urgent'),
                'limit' => 10
            )
        );

        $this->assertCount(2, $high_priority);
        $this->assertEquals('urgent', $high_priority[0]->priority);
        $this->assertEquals('high', $high_priority[1]->priority);
    }

    public function test_notification_read_status() {
        $notification_id = $this->notification_handler->create_notification(
            $this->test_user_id,
            array(
                'type' => 'test',
                'title' => 'Test Notification',
                'message' => 'Test message',
                'priority' => 'normal'
            )
        );

        // Mark as read
        $result = $this->notification_handler->mark_notification_read($notification_id);
        $this->assertTrue($result);

        global $wpdb;
        $notification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sandcrime_notifications WHERE id = %d",
            $notification_id
        ));

        $this->assertNotNull($notification->read_at);
    }

    public function test_notification_deletion() {
        $notification_id = $this->notification_handler->create_notification(
            $this->test_user_id,
            array(
                'type' => 'test',
                'title' => 'Test Notification',
                'message' => 'Test message',
                'priority' => 'normal'
            )
        );

        $result = $this->notification_handler->delete_notification($notification_id);
        $this->assertTrue($result);

        global $wpdb;
        $notification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sandcrime_notifications WHERE id = %d",
            $notification_id
        ));

        $this->assertNull($notification);
    }
}