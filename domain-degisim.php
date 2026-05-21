<?php
/**
 * Plugin Name: Domain Değişim
 * Description: Mağaza sahibi veya admin tarafından domain değişikliği talep edilmesini sağlar.
 * Version: 1.0
 * Author: Magazac
* GitHub Plugin URI: https://github.com/adminmagazify/domain-degisim
 */

require plugin_dir_path(__FILE__) . 'plugin-update-checker-master/plugin-update-checker.php';

$updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/adminmagazify/domain-degisim',
    __FILE__,
    'domain-degisim'
);

$updateChecker->setBranch('main');
if (!defined('ABSPATH')) exit;

class DomainDegisimPlugin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_page']);

        // Shortcode
        add_shortcode('domain-degisim', [$this, 'shortcode_output']);

        // Form işleme
        add_action('admin_post_domain_degisim_talep', [$this, 'handle_domain_talep']);

        // My Account endpoint
        add_action('init', [$this, 'register_account_endpoint']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_account_menu_item']);
        add_action('woocommerce_account_domain-degisim_endpoint', [$this, 'account_endpoint_content']);
    }

    // --- Yetki kontrolü ---
    private function check_permission() {
        return current_user_can('administrator') || current_user_can('shop_manager');
    }

    // --- Admin Menü ---
    public function add_admin_page() {
        if (!$this->check_permission()) return;

        add_menu_page(
            'Domain Değişim',
            'Domain Değişim',
            'manage_options',
            'domain-degisim',
            [$this, 'admin_page_html'],
            'dashicons-admin-site',
            31
        );
    }

    // -------------------------------------------------------------------------
    // FORM HTML — Admin paneli ve Shortcode aynı formu buradan alıyor.
    // -------------------------------------------------------------------------
    public function get_form_html() {

        if (!$this->check_permission()) {
            return "<p>Bu alana erişim izniniz yok.</p>";
        }

        ob_start(); ?>

        <h1 style="font-size:26px; font-weight:600; margin-bottom:10px;">Domain Değişim Talebi</h1>

        <p style="font-size:16px; margin-bottom:25px; max-width:650px;">
            Mağaza'nın domain adresini değiştirmek isterseniz aşağıya istediğiniz domain adresini giriniz.
            Bu domain adresinin sizin kontrolünüzde olması gerekmektedir. Gerekli kontroller yapıldıktan sonra
            mağazanın domain adresi değiştirilecek ve size bilgilendirme yapılacaktır.
        </p>

        <form action="<?php echo admin_url('admin-post.php'); ?>" method="POST" style="margin-bottom:40px;">
            <input type="hidden" name="action" value="domain_degisim_talep">

            <h3>Domain Değişim Talebi</h3>

            <textarea 
                name="domain" 
                rows="3" 
                style="width:100%; padding:15px; border:1px solid #ccc;"
                placeholder="Yeni domain adresini yazınız..."></textarea>

            <br><br>

            <button class="button button-primary" style="background:#000; border:none; padding:10px 25px; font-size:15px;">
                Onayla ve Gönder
            </button>
        </form>

        <!-- DOMAIN YÖNLENDİRME BİLGİ ALANI -->
        <h2 style="margin-top:50px; font-size:22px; font-weight:600;">Domain Adres Yönlendirme</h2>

        <div style="padding:20px; border:1px solid #ccc; background:#fafafa; margin-top:15px; line-height:1.7;">

            <p style="white-space:pre-line;">
Kendi Domain Adresinizi Kullanmak İstiyorsanız Dikkat Etmeniz Gerekenler

Platformumuz üzerinden alışveriş sitesi açan kullanıcılarımız, dilerlerse kendi satın aldıkları alan adlarını (domain adreslerini) kullanarak sitelerini yayınlayabilirler. Bu işlemi gerçekleştirebilmeniz için aşağıdaki adımları takip etmeniz yeterlidir:

<strong>1. Domain Yönetim Panelinize Giriş Yapın</strong>
Domain adresinizi satın aldığınız servis sağlayıcının (örneğin: GoDaddy, Turhost, IHS, vb.) yönetim paneline giriş yapın.

<strong>2. Name Server (İsim Sunucusu) Ayarlarına Gidin</strong>
Domain ayarlarınız içerisinden Name Server ya da İsim Sunucuları bölümüne ulaşın.

<strong>3. Aşağıdaki Name Server Adreslerini Tanımlayın</strong>
ns1.guzelhosting.com  
ns2.guzelhosting.com  
ns11.guzelhosting.com  
ns12.guzelhosting.com  

<strong>4. Otomatik E-Posta Adresi Oluşumu</strong>
Name server değişikliği tamamlandıktan sonra otomatik olarak:
<strong>iletisim@domainadresi</strong> oluşturulur.

<strong>5. Ek E-Posta Hesapları</strong>
İsterseniz destek@domainadresi, info@domainadresi gibi ek hesaplar açabilirsiniz.

<strong>Not:</strong>
DNS değişikliği birkaç saat ile 24 saat arasında aktif olur.
            </p>

        </div>

        <?php 
        return ob_get_clean();
    }

    // --- ADMIN PANEL EKRANI ---
    public function admin_page_html() {
        echo $this->get_form_html();
    }

    // --- SHORTCODE ÇIKTISI ---
    public function shortcode_output() {
        return $this->get_form_html();
    }

    // --- FORM İŞLEME: DOMAIN TALEP ---
    public function handle_domain_talep() {
        if (!$this->check_permission()) wp_die("Yetkiniz yok.");

        $domain = sanitize_textarea_field($_POST['domain']);

        $body = "Yeni domain değişim talebi:\n\n";
        $body .= "Talep Edilen Domain:\n$domain\n\n";

        wp_mail("iletisim@magazac.com", "Domain Değişim Talebi", $body);

        wp_redirect($_SERVER['HTTP_REFERER']);
        exit;
    }

    // -------------------------------------------------------------------------
    // WooCommerce Hesabım Endpoint
    // -------------------------------------------------------------------------

    public function register_account_endpoint() {
        add_rewrite_endpoint('domain-degisim', EP_ROOT | EP_PAGES);
    }

    public function add_account_menu_item($items) {

        // ❗ Standart kullanıcı görmesin
        if ( ! $this->check_permission() ) {
            return $items;
        }

        // Admin + shop_manager görür
        $new_items = [];

        foreach ($items as $key => $label) {
            $new_items[$key] = $label;

            if ($key === 'dashboard') { 
                $new_items['domain-degisim'] = 'Domain Değişim';
            }
        }

        return $new_items;
    }

    public function account_endpoint_content() {

        // endpoint güvenliği
        if ( ! $this->check_permission() ) {
            wp_die("Bu alana erişim izniniz yok.");
        }

        echo $this->get_form_html();
    }
}

new DomainDegisimPlugin();