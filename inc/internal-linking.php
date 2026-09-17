<?php
/**
 * Otomatik İç Linkleme (SEO)
 *
 * Yazı içeriğinde geçen kitap adlarını (diğer özet yazılarının başlıkları) ve
 * yazar adlarını (`authors` taxonomy) ilgili özet / yazar arşivine linkler.
 *
 * Mevcut `thetelos_autolink_author` (functions.php, öncelik 20) yalnız o yazının
 * KENDİ yazarını bir kez linkler. Bu modül onu tamamlar: kitap başlıklarını ve
 * gövdede geçen DİĞER yazarların adlarını linkler. Öncelik 12 ile (yani ondan
 * önce) çalışır; mevcut yazının kendisi ve kendi yazarı burada dışlanır, o link
 * işini eski filtre yapar — çakışma / mükerrer link olmaz.
 *
 * - Sadece tekil yazı görünümünde, ana sorguda çalışır.
 * - Kaynak harita 12 saat transient'te tutulur; yazı/terim kaydında temizlenir.
 * - Performans: binlerce başlık için dev bir regex kurulmaz. İçerik bir kez
 *   küçük harfe çevrilip strpos ile ön eleme yapılır; yalnız içerikte GEÇEN
 *   ifadelerden küçük bir alternation regex kurulur.
 * - HTML güvenliği: mevcut linkler, başlıklar (h1-h6), script/style/code/pre
 *   içine dokunulmaz; kelime sınırı korunur (Türkçe uyumlu /iu).
 *
 * @package Mediumish / TheTelos
 */

// Doğrudan erişimi engelle
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -----------------------------------------------------
// Ayarlar
// -----------------------------------------------------
if ( ! defined( 'TLS_IL_MAX_LINKS' ) ) {
	define( 'TLS_IL_MAX_LINKS', 12 );   // Sayfa başına toplam iç link üst sınırı
}
if ( ! defined( 'TLS_IL_MIN_LEN' ) ) {
	define( 'TLS_IL_MIN_LEN', 4 );      // Bir ifadenin linklenmesi için minimum karakter
}
if ( ! defined( 'TLS_IL_CACHE_TTL' ) ) {
	define( 'TLS_IL_CACHE_TTL', 12 * HOUR_IN_SECONDS );
}
if ( ! defined( 'TLS_IL_MAP_TRANSIENT' ) ) {
	define( 'TLS_IL_MAP_TRANSIENT', 'tls_ilink_map_v1' );
}

/**
 * Tutarlı küçük harf dönüşümü (harita anahtarı ve içerik aynı yolla çevrilir).
 */
function tls_il_lower( $s ) {
	if ( function_exists( 'mb_strtolower' ) ) {
		return mb_strtolower( $s, 'UTF-8' );
	}
	return strtolower( $s );
}

/**
 * Bir ifadenin linklenmeye uygun olup olmadığını kontrol eder.
 * Çok kısa olanlar ve içinde hiç harf bulunmayanlar (salt sayı vb.) elenir.
 */
function tls_il_is_linkable_phrase( $phrase ) {
	$phrase = trim( $phrase );
	if ( $phrase === '' ) {
		return false;
	}
	$len = function_exists( 'mb_strlen' ) ? mb_strlen( $phrase, 'UTF-8' ) : strlen( $phrase );
	if ( $len < TLS_IL_MIN_LEN ) {
		return false;
	}
	// En az bir harf içermeli (salt "1984", tarih vb. atlanır)
	if ( ! preg_match( '/\p{L}/u', $phrase ) ) {
		return false;
	}
	return true;
}

/**
 * Kaynak haritayı kurar (ya da transient'ten okur).
 *
 * Dönen yapı:
 *   [ küçük_harf_ifade => [ 't' => 'book'|'author', 'id' => int ] ]
 *
 * URL'ler (permalink / term link) burada HESAPLANMAZ; yalnız eşleşen az
 * sayıda ifade için, linkleme anında lazy çözülür.
 */
function tls_il_get_map() {
	$cached = get_transient( TLS_IL_MAP_TRANSIENT );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	global $wpdb;
	$map = array();

	// --- Kitaplar: yayınlanmış "post" başlıkları ---
	$rows = $wpdb->get_results(
		"SELECT ID, post_title FROM {$wpdb->posts}
		 WHERE post_type = 'post' AND post_status = 'publish' AND post_title <> ''"
	);
	if ( $rows ) {
		foreach ( $rows as $row ) {
			$title = trim( wp_specialchars_decode( $row->post_title, ENT_QUOTES ) );
			if ( ! tls_il_is_linkable_phrase( $title ) ) {
				continue;
			}
			$key = tls_il_lower( $title );
			// Aynı başlık birden çok yazıda varsa ilkini koru (belirsizliği azalt).
			if ( ! isset( $map[ $key ] ) ) {
				$map[ $key ] = array( 't' => 'book', 'id' => (int) $row->ID );
			}
		}
	}

	// --- Yazarlar: `authors` taxonomy terimleri ---
	if ( taxonomy_exists( 'authors' ) ) {
		$authors = get_terms( array(
			'taxonomy'   => 'authors',
			'hide_empty' => true,
			'number'     => 0,
		) );
		if ( ! is_wp_error( $authors ) && ! empty( $authors ) ) {
			foreach ( $authors as $term ) {
				$name = trim( $term->name );
				if ( ! tls_il_is_linkable_phrase( $name ) ) {
					continue;
				}
				$key = tls_il_lower( $name );
				// Bir yazı başlığı ile çakışırsa kitabı öne al (daha spesifik hedef).
				if ( ! isset( $map[ $key ] ) ) {
					$map[ $key ] = array( 't' => 'author', 'id' => (int) $term->term_id );
				}
			}
		}
	}

	set_transient( TLS_IL_MAP_TRANSIENT, $map, TLS_IL_CACHE_TTL );
	return $map;
}

/**
 * Bir harita girdisi için hedef URL'yi lazy çözer.
 */
function tls_il_resolve_url( $entry ) {
	if ( $entry['t'] === 'book' ) {
		$url = get_permalink( $entry['id'] );
		return $url ? $url : '';
	}
	if ( $entry['t'] === 'author' ) {
		$link = get_term_link( (int) $entry['id'], 'authors' );
		return ( $link && ! is_wp_error( $link ) ) ? $link : '';
	}
	return '';
}

/**
 * the_content filtresi: içeriğe iç linkleri ekler.
 */
function tls_internal_links( $content ) {
	// Sadece tekil yazı / analiz görünümü, ana sorgu, ana döngü.
	if ( is_admin() || ! is_singular( array( 'post', 'analysis' ) ) || ! is_main_query() || ! in_the_loop() ) {
		return $content;
	}
	// Feed / REST vb. dışında tut.
	if ( is_feed() ) {
		return $content;
	}
	// Kapatma kancası.
	if ( ! apply_filters( 'tls_internal_links_enabled', true ) ) {
		return $content;
	}
	if ( ! is_string( $content ) || $content === '' ) {
		return $content;
	}

	$map = tls_il_get_map();
	if ( empty( $map ) ) {
		return $content;
	}

	$current_id = get_the_ID();

	// Mevcut yazının başlığını ve yazar(lar)ını dışla (kendine link verme).
	$exclude_keys = array();
	$this_title = tls_il_lower( trim( wp_specialchars_decode( get_the_title( $current_id ), ENT_QUOTES ) ) );
	if ( $this_title !== '' ) {
		$exclude_keys[ $this_title ] = true;
	}
	$this_authors = get_the_terms( $current_id, 'authors' );
	if ( ! empty( $this_authors ) && ! is_wp_error( $this_authors ) ) {
		foreach ( $this_authors as $t ) {
			$exclude_keys[ tls_il_lower( trim( $t->name ) ) ] = true;
		}
	}

	// --- Ön eleme: yalnız içerikte GEÇEN ifadeleri topla ---
	$lc_content = tls_il_lower( $content );
	$candidates = array();
	foreach ( $map as $key => $entry ) {
		if ( isset( $exclude_keys[ $key ] ) ) {
			continue;
		}
		// Kendi yazısına link verme (aynı post id).
		if ( $entry['t'] === 'book' && (int) $entry['id'] === (int) $current_id ) {
			continue;
		}
		if ( strpos( $lc_content, $key ) !== false ) {
			$candidates[ $key ] = $entry;
		}
	}
	if ( empty( $candidates ) ) {
		return $content;
	}

	// Uzun ifadeler önce (alt-ifadelerden önce eşleşsin).
	uksort( $candidates, function ( $a, $b ) {
		$la = strlen( $a );
		$lb = strlen( $b );
		if ( $la === $lb ) {
			return 0;
		}
		return ( $la < $lb ) ? 1 : -1;
	} );

	// Alternation regex parçalarını hazırla.
	$parts = array();
	foreach ( array_keys( $candidates ) as $key ) {
		$parts[] = preg_quote( $key, '/' );
	}
	// Aşırı büyümeyi engelle (nadir; sayfada çok fazla eşleşen ifade olursa).
	if ( count( $parts ) > 200 ) {
		$parts = array_slice( $parts, 0, 200 );
	}
	$pattern = '/(?<![\p{L}\p{N}])(' . implode( '|', $parts ) . ')(?![\p{L}\p{N}])/iu';

	// Linkleme durumunu tutan bağlam.
	$ctx = array(
		'map'       => $candidates,
		'linked'    => array(), // hedef başına 1 kez
		'count'     => 0,
		'max'       => (int) TLS_IL_MAX_LINKS,
		'pattern'   => $pattern,
	);

	// --- İçeriği tag'lere göre böl; korumalı bölgelerin dışına uygula ---
	$segments = preg_split( '/(<[^>]+>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( $segments === false ) {
		return $content;
	}

	$skip_depth = 0; // a / h1-h6 / script / style / code / pre / button vb. içi
	$protected  = array( 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'script', 'style', 'code', 'pre', 'button', 'textarea' );

	foreach ( $segments as $i => $seg ) {
		if ( $seg === '' ) {
			continue;
		}
		// Tag mi?
		if ( $seg[0] === '<' ) {
			if ( preg_match( '/^<\s*(\/?)\s*([a-zA-Z0-9]+)/', $seg, $tm ) ) {
				$is_close = ( $tm[1] === '/' );
				$tag      = strtolower( $tm[2] );
				if ( in_array( $tag, $protected, true ) ) {
					// Kendi kendine kapanan tag korumalı bölge açmaz.
					$self_close = ( substr( rtrim( $seg ), -2 ) === '/>' );
					if ( ! $self_close ) {
						if ( $is_close ) {
							if ( $skip_depth > 0 ) {
								$skip_depth--;
							}
						} else {
							$skip_depth++;
						}
					}
				}
			}
			continue; // tag'lerin kendisine dokunma
		}

		// Metin segmenti
		if ( $skip_depth > 0 ) {
			continue;
		}
		if ( $ctx['count'] >= $ctx['max'] ) {
			break;
		}

		$segments[ $i ] = preg_replace_callback( $pattern, function ( $m ) use ( &$ctx ) {
			$matched = $m[1];
			if ( $ctx['count'] >= $ctx['max'] ) {
				return $matched;
			}
			$key = tls_il_lower( $matched );
			if ( isset( $ctx['linked'][ $key ] ) || ! isset( $ctx['map'][ $key ] ) ) {
				return $matched;
			}
			$url = tls_il_resolve_url( $ctx['map'][ $key ] );
			if ( $url === '' ) {
				return $matched;
			}
			$ctx['linked'][ $key ] = true;
			$ctx['count']++;
			$type = $ctx['map'][ $key ]['t'];
			return '<a href="' . esc_url( $url ) . '" class="tls-ilink tls-ilink-' . esc_attr( $type ) . '" title="' . esc_attr( $matched ) . '">' . $matched . '</a>';
		}, $segments[ $i ] );
	}

	return implode( '', $segments );
}
add_filter( 'the_content', 'tls_internal_links', 12 );

// -----------------------------------------------------
// Cache temizleme: içerik/terim değişince haritayı yenile
// -----------------------------------------------------
function tls_il_flush_map() {
	delete_transient( TLS_IL_MAP_TRANSIENT );
}
add_action( 'save_post_post', 'tls_il_flush_map' );
add_action( 'deleted_post', 'tls_il_flush_map' );
add_action( 'created_authors', 'tls_il_flush_map' );
add_action( 'edited_authors', 'tls_il_flush_map' );
add_action( 'delete_authors', 'tls_il_flush_map' );
