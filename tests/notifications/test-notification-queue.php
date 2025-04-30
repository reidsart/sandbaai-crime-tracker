<?php
class SandCrime_Test_Notification_Queue extends WP_UnitTestCase {
    private $queue_manager;
    private $test_user_id;

    public function setUp() {
        parent::setUp();
        
        $this->queue_manager = new SandCrime_Notification_Queue_Manager();
        
        $this->test_user_id = $this->factory->user->create(array(
            'role' => 'subscriber'
        ));
    }

    public function tearDown() {
        parent::tearDown();
        
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}sandcrime_notification_queue WHERE user_id = {$this->test_user_id}");
        
        wp_delete_user($this->test_user_id);
    }

    public function test_enqueue_notification() {
        $notification_id = $this->create_test_notification();

        $queue_id = $this->queue_manager->enqueue_notification(
            $notification_id,
            $this->test_user_id,
            'email',
            array('priority' => 'normal')
        );

        $this->assertIsInt($queue_id);
        $this->assertGreaterThan(0, $queue_id);

        global $wpdb;
        $queue_item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sandcrime_notification_queue WHERE id = %d",
            $queue_id
        ));

        $this->assertNotNull($queue_item);
        $this->assertEquals($notification_id, $queue_item->notification_id);
        $this->assertEquals('pending', $queue_item->status);
    }

    public function test_bulk_enqueue_notifications() {
        $notifications = array(
            array(
                'notification_id' => $this->create_test_notification(),
                'user_id' => $this->test_user_id,
                'delivery_type' => 'email',
                'priority' => 'high'
            ),
            array(
                'notification_id' => $this->create_test_notification(),
                'user_id' => $this->test_user_id,
                'delivery_type' => 'push',
                'priority' => 'normal'
            )
        );

        $result = $this->queue_manager->bulk_enqueue_notifications($notifications);
        $this->assertEquals(2, $result);

        global $wpdb;
        $queue_items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sandcrime_notification_queue WHERE user_id = %d",
            $this->test_user_id
        ));

        $this->assertCount(2, $queue_items);
    }

    public function test_retry_failed_notifications() {
        $notification_id = $this->create_test_notification();
        $queue_id = $this->queue_manager->enqueue_notification(
            $notification_id,
            $this->test_user_id,
            'email'
        );

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'sandcrime_notification_queue',
            array(
                'status' => 'failed',
                'attempts' => 1
            ),
            array('id' => $queue_id)
        );

        $this->queue_manager->retry_failed_notifications();

        $queue_item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sandcrime_notification_queue WHERE id = %d",
            $queue_id
        ));

        $this->assertEquals('pending', $queue_item->status);
    }

    private function create_test_notification() {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'sandcrime_notifications',
            array(
                'user_id' => $this->test_user_id,
                'type' => 'test',
                'title' => 'Test Notification',
                'message' => 'Test message',
                'priority' => 'normal',
                'created_at' => current_time('mysql')
            )
        );
        return $wpdb->insert_id;
    }
}