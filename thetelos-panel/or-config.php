<?php
/**
 * or-config.php — OpenRouter (Qwen vb.) ayar okuyucusu.
 *
 * NEDEN: config.php sunucuda (repoya girmez, push ile dağıtılmaz). Yeni bir API
 * anahtarını oraya elle eklemek zor. Bunun yerine anahtar + model, panelin
 * "Ayarlar" ekranından girilip sunucuda yazılabilir bir gizli dosyaya kaydedilir:
 *   thetelos-panel/openrouter.secret.php  (git'e girmez, .gitignore'da)
 * Bu dosya varsa öncelik ondadır; yoksa eski config.php sabitlerine düşülür.
 * Böylece dosya yüklemeden, sadece panele yapıştırarak model değiştirilebilir.
 *
 * Çıktı akışına etkisi: proto_ds() zaten OpenRouter anahtarı varsa ÖNCE onu
 * dener (bkz. _proto.php). Yani buradan Qwen modeli aktif edilince toplu
 * kitap özetleri Qwen üzerinden üretilir; Qwen boş dönerse DeepSeek'e düşer.
 */

if (!defined('TLS_OR_CONFIG_LOADED')) {
    define('TLS_OR_CONFIG_LOADED', 1);
    if (!defined('TLS_OR_SECRET')) define('TLS_OR_SECRET', __DIR__ . '/openrouter.secret.php');

    /** Etkin OpenRouter yapılandırmasını (panel > config yedeği) döndür. */
    function tls_or_conf() {
        static $c = null;
        if ($c !== null) return $c;

        // 1) Panelden kaydedilen gizli dosya
        $panel = ['enabled' => false, 'key' => '', 'model' => ''];
        if (is_file(TLS_OR_SECRET)) {
            $d = @include TLS_OR_SECRET;
            if (is_array($d)) {
                $panel['enabled'] = !empty($d['enabled']);
                $panel['key']     = trim((string) ($d['key']   ?? ''));
                $panel['model']   = trim((string) ($d['model'] ?? ''));
            }
        }

        // 2) config.php sabitleri (eski/yedek yol — DeepSeek köprüsü)
        $cfg_key = '';
        foreach (['OPENROUTER_KEY', 'OPENROUTER_API_KEY', 'OPENROUTER'] as $k) {
            if (defined($k) && constant($k)) { $cfg_key = (string) constant($k); break; }
        }
        $cfg_model = (defined('OPENROUTER_MODEL') && OPENROUTER_MODEL) ? (string) OPENROUTER_MODEL : '';

        if ($panel['enabled'] && $panel['key'] !== '') {
            $c = [
                'active' => true,
                'key'    => $panel['key'],
                'model'  => $panel['model'] !== '' ? $panel['model'] : ($cfg_model ?: 'deepseek/deepseek-chat'),
                'source' => 'panel',
            ];
        } elseif ($cfg_key !== '') {
            $c = [
                'active' => true,
                'key'    => $cfg_key,
                'model'  => $cfg_model ?: 'deepseek/deepseek-chat',
                'source' => 'config',
            ];
        } else {
            $c = [
                'active' => false,
                'key'    => '',
                'model'  => $cfg_model ?: 'deepseek/deepseek-chat',
                'source' => 'none',
            ];
        }
        // Paneldeki ham durumu da (arayüz için) taşı
        $c['panel_saved']   = ($panel['key'] !== '');
        $c['panel_enabled'] = $panel['enabled'];
        return $c;
    }

    /** Etkin anahtar (yoksa boş). proto_ds bunu görürse OpenRouter'ı ÖNCE dener. */
    function tls_or_key()   { $c = tls_or_conf(); return $c['active'] ? $c['key'] : ''; }
    /** Etkin model kimliği (örn. qwen/qwen-2.5-72b-instruct). */
    function tls_or_model() { $c = tls_or_conf(); return $c['model']; }
    /** OpenRouter şu an devrede mi? */
    function tls_or_active(){ $c = tls_or_conf(); return (bool) $c['active']; }
}
