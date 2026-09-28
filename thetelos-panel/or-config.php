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
        //    GEÇERSİZ/BOZUK kısa sabitleri YOK SAY: gerçek OpenRouter anahtarı
        //    "sk-or-" ile başlar ve uzundur. 12 haneli eski bir dummy sabit,
        //    panelden girilen geçerli anahtarın önüne geçmemeli.
        $cfg_key = '';
        foreach (['OPENROUTER_KEY', 'OPENROUTER_API_KEY', 'OPENROUTER'] as $k) {
            if (defined($k) && constant($k)) {
                $v = trim((string) constant($k));
                if (strlen($v) >= 20 || strncmp($v, 'sk-or', 5) === 0) { $cfg_key = $v; break; }
            }
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

    // ═══════════════════════════════════════════════════════════════════════
    // OPENROUTER SİSTEMDEN KALDIRILDI (kullanıcı kararı).
    // Ana kapatma anahtarı: aşağıdaki fonksiyonlar artık DAİMA "kapalı" döner →
    // proto_ds / tv_ask / batch-worker / pdf-extract otomatik olarak DeepSeek →
    // Claude kademesine düşer. (Kod dosyaları kalıyor ama devrede değil.)
    // Geri açmak istenirse bu üç satırı eski haline çevirmek yeterli.
    // ═══════════════════════════════════════════════════════════════════════
    /** KAPALI: OpenRouter kaldırıldı. */
    function tls_or_key()   { return ''; }
    /** Etkin model kimliği — kullanılmıyor. */
    function tls_or_model() { return ''; }
    /** KAPALI: OpenRouter devrede değil. */
    function tls_or_active(){ return false; }
    /** Model canlı web aramalı mı? (kapalı). */
    function tls_or_is_online(){ return false; }
}
