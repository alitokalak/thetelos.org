<?php
/**
 * Book Cover Generator
 * Otomatik kitap kapağı oluşturur - kategori bazlı renk paleti ile
 *
 * @package Mediumish / TheTelos
 */

// CSS dosyasını enqueue et
add_filter( 'style_loader_tag', function( $html, $handle ) {
    if ( $handle === 'thetelos-book-cover' ) {
        return str_replace( "rel='stylesheet'", "rel='stylesheet' charset='UTF-8'", $html );
    }
    return $html;
}, 10, 2 );

add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'thetelos-book-cover',
        get_template_directory_uri() . '/inc/book-cover.css',
        [],
        '1.0'
    );
});

// -----------------------------------------------------
// Kategori Renk Paleteri
// Her kategori: [arka_plan, cerceve_rengi, yazi_rengi, alt_yazi_rengi]
// -----------------------------------------------------
function thetelos_get_category_palette( $category_slug ) {

    $palettes = [
        'philosophy'             => ['#1C2B3A', '#C8A165', '#F5EFE6', '#A89070'],
        'ethics'                 => ['#1A2A1A', '#8BAF7C', '#F0F5EC', '#7A9A6A'],
        'metaphysics'            => ['#2A1A3A', '#9B7EC8', '#F0ECF5', '#806BAA'],
        'epistemology'           => ['#1A2535', '#7AAFC8', '#ECF3F7', '#5A92B0'],
        'logic'                  => ['#1C2030', '#8090C0', '#EEF0F8', '#6070A8'],
        'aesthetics'             => ['#2A1A28', '#C87E9B', '#F5ECF3', '#A86080'],
        'political_philosophy'   => ['#1A2020', '#6AA8A0', '#ECF5F4', '#508880'],
        'history_of_philosophy'  => ['#2A2010', '#C8A060', '#F5F0E6', '#A8804A'],
        'philosophy_of_religion' => ['#1E1A2A', '#A07EC8', '#F2EFF8', '#806AAA'],
        'religion'               => ['#2A1810', '#C87840', '#F5EDE6', '#A85820'],
        'theology'               => ['#1E1A10', '#C8B040', '#F5F2E6', '#A89030'],
        'systematic_theology'    => ['#221A10', '#D4A855', '#F6F0E5', '#B08838'],
        'christian_theology'     => ['#1A1A2A', '#7878C8', '#EEEEF8', '#5858A8'],
        'islamic_theology'       => ['#0F2010', '#50A860', '#E8F5EC', '#388848'],
        'christianity'           => ['#1A1528', '#8070C0', '#EEEAF8', '#6050A0'],
        'islam'                  => ['#102010', '#40A850', '#E5F5E8', '#2A8838'],
        'judaism'                => ['#201A10', '#C8A040', '#F5F0E5', '#A88028'],
        'buddhism'               => ['#1A1505', '#C8A820', '#F5F0E0', '#A88810'],
        'hinduism'               => ['#2A1005', '#D86020', '#F8EDE0', '#B84010'],
        'atheism'                => ['#151515', '#909090', '#F0F0F0', '#707070'],
        'agnosticism'            => ['#181820', '#8888A8', '#EEEEF4', '#666688'],
        'history'                => ['#251810', '#B07840', '#F3EDE5', '#906030'],
        'world_history'          => ['#201510', '#A87040', '#F2EBE5', '#885030'],
        'ancient_history'        => ['#2A2010', '#C8A060', '#F5F0E5', '#A88040'],
        'medieval_history'       => ['#201818', '#A06060', '#F2E8E8', '#885050'],
        'modern_history'         => ['#101820', '#507090', '#E5EEF5', '#386080'],
        'military_history'       => ['#101810', '#506850', '#E5EEE5', '#385038'],
        'cultural_history'       => ['#201A10', '#B08850', '#F2EDE5', '#907038'],
        'biography'              => ['#1A1520', '#907898', '#EEE8F2', '#706078'],
        'autobiography'          => ['#18151E', '#887090', '#EEE8F2', '#685878'],
        'memoir'                 => ['#1E1520', '#987898', '#F0E8F2', '#786078'],
        'literature'             => ['#1A1015', '#A06080', '#F2E5EC', '#804860'],
        'classic_literature'     => ['#200F15', '#B06880', '#F4E5EC', '#906068'],
        'world_literature'       => ['#181015', '#A06878', '#F2E5EA', '#805860'],
        'poetry'                 => ['#1E0F20', '#B068B8', '#F5E5F5', '#904898'],
        'drama'                  => ['#1A0F10', '#B05858', '#F5E5E5', '#904040'],
        'novel'                  => ['#151020', '#7868B0', '#EAE8F5', '#585890'],
        'fiction'                => ['#100F20', '#6868B8', '#E8E8F5', '#484898'],
        'historical_fiction'     => ['#1A1010', '#A06858', '#F2E8E5', '#805040'],
        'science_fiction'        => ['#0F1020', '#5068C8', '#E5E8F8', '#3050A8'],
        'dystopian_fiction'      => ['#100F10', '#606060', '#E8E8E8', '#404040'],
        'fantasy'                => ['#0F1020', '#6850C8', '#E8E5F8', '#5038A8'],
        'horror'                 => ['#0F0A0A', '#900000', '#F5E0E0', '#700000'],
        'mystery'                => ['#0F0F18', '#506080', '#E5E8EE', '#384860'],
        'detective_fiction'      => ['#101018', '#484878', '#E5E5F0', '#303068'],
        'romance'                => ['#200F15', '#C86880', '#F8E5EC', '#A84860'],
        'adventure'              => ['#0F1810', '#406840', '#E5EEE5', '#286028'],
        'psychology'             => ['#1A1020', '#8858A8', '#EEE5F5', '#683888'],
        'cognitive_psychology'   => ['#151020', '#7860A8', '#EAE8F5', '#584888'],
        'social_psychology'      => ['#101820', '#507098', '#E5EEF4', '#385878'],
        'psychoanalysis'         => ['#180F20', '#906898', '#F0E5F2', '#705078'],
        'sociology'              => ['#101820', '#487890', '#E5EEF4', '#306070'],
        'anthropology'           => ['#1A1510', '#908050', '#F2EEE5', '#706030'],
        'politics'               => ['#100F18', '#484878', '#E5E5F2', '#303060'],
        'political_science'      => ['#0F1018', '#404878', '#E5E5F2', '#283060'],
        'economics'              => ['#0F1810', '#387038', '#E5F0E5', '#205020'],
        'microeconomics'         => ['#0F1810', '#407840', '#E5F2E5', '#286028'],
        'macroeconomics'         => ['#0F1A10', '#388038', '#E5F2E5', '#206820'],
        'education'              => ['#101A18', '#408078', '#E5F2F0', '#286868'],
        'law'                    => ['#101018', '#484848', '#E8E8E8', '#303030'],
        'international_law'      => ['#0F1018', '#404868', '#E5E5F0', '#282858'],
        'science'                => ['#0F1820', '#306890', '#E5EEF5', '#185070'],
        'physics'                => ['#0F1020', '#305090', '#E5E8F5', '#183070'],
        'astronomy'              => ['#05051A', '#3030C0', '#E0E0F8', '#1818A0'],
        'chemistry'              => ['#0F1818', '#306868', '#E5F0F0', '#185050'],
        'mathematics'            => ['#101020', '#484890', '#E5E5F5', '#303070'],
        'statistics'             => ['#101828', '#386898', '#E5EEF5', '#205078'],
        'biology'                => ['#0F1A0F', '#308030', '#E5F5E5', '#186018'],
        'evolution'              => ['#101A10', '#407840', '#E5F2E5', '#286028'],
        'genetics'               => ['#0F1818', '#308868', '#E5F2EE', '#186850'],
        'medicine'               => ['#0F1818', '#286868', '#E5F0F0', '#105050'],
        'neuroscience'           => ['#100F20', '#5048A0', '#E8E5F5', '#383080'],
        'public_health'          => ['#0F1818', '#288878', '#E5F5F2', '#107060'],
        'technology'             => ['#101828', '#3878A8', '#E5EEF5', '#205888'],
        'computers'              => ['#0F1828', '#2870A8', '#E5EEF8', '#185888'],
        'artificial_intelligence'=> ['#080F20', '#2040B0', '#E0E5F8', '#1028A0'],
        'programming'            => ['#0A1020', '#2858A0', '#E0E8F5', '#104088'],
        'data_science'           => ['#0F1828', '#3068A0', '#E5EEF5', '#185080'],
        'art'                    => ['#200F18', '#B06888', '#F5E5EE', '#904868'],
        'art_history'            => ['#1A0F18', '#A06080', '#F2E5EC', '#804860'],
        'music'                  => ['#1A0F20', '#9058A8', '#F0E5F5', '#703888'],
        'music_history'          => ['#180F1E', '#8858A0', '#F0E5F4', '#683880'],
        'architecture'           => ['#181818', '#707070', '#F0F0F0', '#505050'],
        'design'                 => ['#1A1020', '#8868B0', '#EEE8F5', '#685890'],
        'photography'            => ['#0F0F0F', '#808080', '#F0F0F0', '#606060'],
        'film'                   => ['#0A0A10', '#505080', '#E8E8F0', '#303060'],
        'theatre'                => ['#1A0F10', '#A85858', '#F5E5E5', '#884040'],
        'geography'              => ['#0F1818', '#407868', '#E5F2EE', '#286050'],
        'travel'                 => ['#0F1820', '#3878A0', '#E5EEF5', '#206080'],
        'culture'                => ['#1A1010', '#A07060', '#F2EBE8', '#806050'],
        'mythology'              => ['#200F10', '#B06860', '#F5E8E5', '#905048'],
        'folklore'               => ['#1A1008', '#B08850', '#F2EDE0', '#906030'],
        'children'               => ['#0F2010', '#40A860', '#E5F8EC', '#208848'],
        'young_adult'            => ['#100F20', '#6858A8', '#EAE8F5', '#483888'],
        'self_help'              => ['#101A10', '#50A050', '#E8F5E8', '#308030'],
        'personal_development'   => ['#0F1A0F', '#489848', '#E8F5E8', '#287828'],
        'business'               => ['#101018', '#383878', '#E5E5F2', '#202060'],
        'management'             => ['#101018', '#404878', '#E5E5F2', '#283068'],
        'marketing'              => ['#180F10', '#A04858', '#F5E5E8', '#803040'],
        'entrepreneurship'       => ['#100F18', '#504878', '#E8E8F2', '#383060'],
    ];

    return isset( $palettes[ $category_slug ] ) ? $palettes[ $category_slug ] : ['#1C2B3A', '#C8A165', '#F5EFE6', '#A89070'];
}

// -----------------------------------------------------
// Yazarı taxonomy'den çek
// -----------------------------------------------------
function thetelos_get_book_author( $post_id ) {
    $authors = get_the_terms( $post_id, 'authors' );
    if ( ! empty( $authors ) && ! is_wp_error( $authors ) ) {
        return $authors[0]->name;
    }
    return get_the_author_meta( 'display_name' );
}

// -----------------------------------------------------
// Kitap Kapağı HTML Üret
// -----------------------------------------------------
function thetelos_render_book_cover( $post_id ) {
    $categories = get_the_category( $post_id );
    $cat_slug   = ! empty( $categories ) ? $categories[0]->slug : 'philosophy';

    $palette = thetelos_get_category_palette( $cat_slug );
    $bg      = $palette[0];
    $border  = $palette[1];
    $text    = $palette[2];
    $subtext = $palette[3];

    $title  = get_the_title( $post_id );
    $author = thetelos_get_book_author( $post_id );
    $title  = trim( str_ireplace( $author, '', $title ) );
    $title  = preg_replace('/\s*[\(\(].*?[\)\)]/u', '', $title); // parantez içini kaldır
    $title  = preg_replace('/\s*[-–—].*$/u', '', $title);        // tireden sonrasını kaldır
    $title  = trim( $title );

    $author = thetelos_get_book_author( $post_id );

    ob_start();
    $w = 160; $h = 220;
    ?>
    <div class="thetelos-book-cover" style="background:<?php echo esc_attr($bg); ?>; width:<?php echo $w; ?>px; height:<?php echo $h; ?>px; padding:16px 10px; box-sizing:border-box; border-radius:3px; position:relative; display:flex; flex-direction:column; align-items:center; justify-content:space-between; font-family:'DM Serif Display',Georgia,serif; flex-shrink:0; overflow:hidden;">

        <div style="position:absolute;inset:5px;border:1px solid <?php echo esc_attr($border); ?>;opacity:0.30;border-radius:1px;pointer-events:none;z-index:1;"></div>

        <div style="color:<?php echo esc_attr($text); ?>;opacity:0.85;font-size:9px;letter-spacing:0.16em;text-align:center;text-transform:uppercase;z-index:2;font-family:'DM Serif Display',Georgia,serif;"><?php echo esc_html($author); ?></div>

        <div style="color:<?php echo esc_attr($text); ?>;font-size:17px;font-weight:400;font-style:italic;text-align:center;line-height:1.35;z-index:2;padding:0 10px;font-family:'DM Serif Display',Georgia,serif;"><?php echo esc_html($title); ?></div>

        <div style="z-index:2;line-height:0;opacity:0.55;">
            <?php
            $logo_path = get_template_directory() . '/assets/img/thetelos_logo.svg';
            if ( file_exists( $logo_path ) ) {
                $svg = file_get_contents( $logo_path );
                $svg = preg_replace( '/<svg/', '<svg style="height:13px;width:auto;display:block;filter:brightness(0) invert(1);"', $svg, 1 );
                echo $svg;
            } else {
                echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 62 9" width="62" height="9"><text x="31" y="7.5" text-anchor="middle" font-family="\'DM Serif Display\',Georgia,serif" font-size="6.5" letter-spacing="2" fill="' . esc_attr($text) . '">thetelos</text></svg>';
            }
            ?>
        </div>

    </div>
    <?php
    return ob_get_clean();
}
