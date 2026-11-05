<?php
/**
 * The Telos — Kitap sayfası sesli özet oynatıcısı.
 * Ses dosyası (_tls_audio_url) varsa yazının üstünde gösterilir; yoksa hiçbir şey çıkmaz.
 * Sesler Kokoro ile üretilip Cloudflare R2'de tutulur (panel → Sesli Özet).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tls_audio_player( $post_id ) {
    $url = trim( (string) get_post_meta( $post_id, '_tls_audio_url', true ) );
    if ( $url === '' ) return;
    $secs = (int) get_post_meta( $post_id, '_tls_audio_seconds', true );
    $dur  = $secs > 0 ? sprintf( '%d:%02d', floor( $secs / 60 ), $secs % 60 ) : '0:00';
    ?>
<div class="tls-audio" data-src="<?php echo esc_url( $url ); ?>">
  <button class="tls-au-skip" data-skip="-15" aria-label="15 seconds back">
    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 12a8 8 0 1 0 2.3-5.6"/><path d="M4 4v4h4"/><text x="12" y="15" font-size="7" text-anchor="middle" fill="currentColor" stroke="none">15</text></svg>
  </button>
  <button class="tls-au-play" aria-label="Play summary">
    <svg class="i-play" viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
    <svg class="i-pause" viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M7 5h4v14H7zM13 5h4v14h-4z"/></svg>
  </button>
  <button class="tls-au-skip" data-skip="15" aria-label="15 seconds forward">
    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 12a8 8 0 1 1-2.3-5.6"/><path d="M20 4v4h-4"/><text x="12" y="15" font-size="7" text-anchor="middle" fill="currentColor" stroke="none">15</text></svg>
  </button>
  <input class="tls-au-seek" type="range" min="0" max="1000" value="0" aria-label="Seek">
  <span class="tls-au-time"><span class="cur">0:00</span> / <span class="dur"><?php echo esc_html( $dur ); ?></span></span>
  <button class="tls-au-speed" aria-label="Playback speed">1×</button>
</div>
<style>
.tls-audio{display:flex;align-items:center;gap:12px;background:#FBF4E6;border:1px solid #EFE2C4;border-radius:var(--tls-radius-lg);padding:12px 18px;margin:0 0 26px}
.tls-audio button{background:none;border:0;cursor:pointer;color:var(--tls-bg-dark);padding:0;display:flex;align-items:center;justify-content:center}
.tls-au-play{width:44px;height:44px;border-radius:50%;background:var(--tls-gold)!important;color:#fff!important;flex:0 0 44px}
.tls-au-play .i-pause{display:none}.tls-audio.playing .i-play{display:none}.tls-audio.playing .i-pause{display:block}
.tls-au-seek{flex:1;min-width:60px;accent-color:var(--tls-gold);height:4px}
.tls-au-time{font:13px var(--tls-sans);color:var(--tls-muted);white-space:nowrap}
.tls-au-speed{font:600 12px var(--tls-sans)!important;border:1px solid var(--tls-border)!important;border-radius:999px!important;padding:4px 10px!important}
@media (max-width:600px){.tls-audio{gap:8px;padding:10px 12px}.tls-au-time{font-size:12px}}
</style>
<script>
(function(){
  var box=document.currentScript.previousElementSibling.previousElementSibling, a=new Audio();
  a.preload='none'; a.src=box.getAttribute('data-src');
  var play=box.querySelector('.tls-au-play'), seek=box.querySelector('.tls-au-seek'),
      cur=box.querySelector('.cur'), dur=box.querySelector('.dur'), sp=box.querySelector('.tls-au-speed'),
      speeds=[1,1.25,1.5,2,0.75], si=0;
  function fmt(s){s=Math.floor(s||0);return Math.floor(s/60)+':'+('0'+s%60).slice(-2);}
  play.onclick=function(){ if(a.paused){a.play();}else{a.pause();} };
  a.onplay=function(){box.classList.add('playing');}; a.onpause=function(){box.classList.remove('playing');};
  a.ontimeupdate=function(){ cur.textContent=fmt(a.currentTime); if(a.duration){ seek.value=Math.round(a.currentTime/a.duration*1000); dur.textContent=fmt(a.duration);} };
  seek.oninput=function(){ if(a.duration) a.currentTime=seek.value/1000*a.duration; };
  box.querySelectorAll('.tls-au-skip').forEach(function(b){ b.onclick=function(){ a.currentTime=Math.max(0,a.currentTime+parseFloat(b.getAttribute('data-skip'))); }; });
  sp.onclick=function(){ si=(si+1)%speeds.length; a.playbackRate=speeds[si]; sp.textContent=speeds[si]+'×'; };
})();
</script>
    <?php
}
