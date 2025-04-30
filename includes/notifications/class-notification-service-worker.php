<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Service_Worker {
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'register_service_worker'));
        add_action('wp_footer', array($this, 'add_service_worker_registration'));
        add_action('init', array($this, 'handle_service_worker_request'));
    }

    public function register_service_worker() {
        wp_enqueue_script(
            'sandcrime-notifications-worker',
            plugins_url('assets/js/notification-service-worker.js', SANDCRIME_PLUGIN_FILE),
            array(),
            SANDCRIME_VERSION,
            true
        );

        wp_localize_script('sandcrime-notifications-worker', 'sandcrimeWorkerConfig', array(
            'nonce' => wp_create_nonce('sandcrime_notifications'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'scope' => home_url('/'),
            'vapidPublicKey' => get_option('sandcrime_vapid_public_key')
        ));
    }

    public function add_service_worker_registration() {
        ?>
        <script>
            if ('serviceWorker' in navigator && 'PushManager' in window) {
                window.addEventListener('load', async () => {
                    try {
                        const registration = await navigator.serviceWorker.register(
                            '<?php echo esc_url(plugins_url('assets/js/notification-service-worker.js', SANDCRIME_PLUGIN_FILE)); ?>',
                            { scope: '/' }
                        );

                        if (registration.installing) {
                            console.log('Service worker installing');
                        } else if (registration.waiting) {
                            console.log('Service worker installed');
                        } else if (registration.active) {
                            console.log('Service worker active');
                        }

                        await this.registerPeriodicSync(registration);
                    } catch (error) {
                        console.error('Service worker registration failed:', error);
                    }
                });
            }

            async function registerPeriodicSync(registration) {
                if ('periodicSync' in registration) {
                    try {
                        await registration.periodicSync.register('update-notifications', {
                            minInterval: 60 * 60 * 1000 // 1 hour
                        });
                    } catch (error) {
                        console.error('Periodic Sync could not be registered:', error);
                    }
                }
            }
        </script>
        <?php
    }

    public function handle_service_worker_request() {
        if (isset($_GET['sandcrime-sw']) && $_GET['sandcrime-sw'] === 'true') {
            header('Content-Type: application/javascript');
            header('Service-Worker-Allowed: /');
            
            $sw_path = SANDCRIME_PLUGIN_DIR . 'assets/js/notification-service-worker.js';
            if (file_exists($sw_path)) {
                echo file_get_contents($sw_path);
            }
            exit;
        }
    }

    public static function get_subscription_details() {
        return array(
            'publicKey' => get_option('sandcrime_vapid_public_key'),
            'scope' => home_url('/'),
            'userVisibleOnly' => true,
            'applicationServerKey' => self::urlsafe_base64_decode(
                get_option('sandcrime_vapid_public_key')
            )
        );
    }

    private static function urlsafe_base64_decode($input) {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }
}

// Initialize the service worker handler
new SandCrime_Notification_Service_Worker();