<?php
/** Index (de-duplicated magazine layout) */
get_header();

echo '<main id="main" role="main">';

// === Theme mods / options ===
$mediumish_homeslider_active = get_theme_mod('mediumish_homeslider_active');  // 0 = show
$mediumish_option_homeslider_recentposts = get_theme_mod('mediumish_option_homeslider_recentposts');  // 1 = recent posts (ignore tag)
$slidertag = get_theme_mod('mediumish_option_homeslider');  // array of tag IDs expected
if ((int) $mediumish_option_homeslider_recentposts === 1) {
    $slidertag = [];  // force “recent” mode
}
$slidernumber = max(1, (int) get_theme_mod('mediumish_option_homeslider_numberposts'));
$mediumish_postsbycategory_active = get_theme_mod('mediumish_postsbycategory_active');  // 0 = show
$postcategories = get_theme_mod('mediumish_option_postsbycategory');  // array of {categoryfield, postsperpage, categorystyle}
$mediumish_homecategorycloud_active = get_theme_mod('mediumish_homecategorycloud_active');  // 0 = show
$mediumish_homecategorycloud_bg = get_theme_mod('mediumish_homecategorycloud_bg');
$mediumish_allstories = get_theme_mod('all_stories_text', 'All Stories');

// === Global “seen” bucket to avoid duplicates across all sections ===
$seen_ids = [];

// === Helper: push current post into $seen_ids safely ===
function wt_seen_push(&$seen, $post_id)
{
    if ($post_id && !in_array($post_id, $seen, true)) {
        $seen[] = (int) $post_id;
    }
}

// Normalize checkbox-ish values from Customizer (0/1, "on", "true", etc.)
if (!function_exists('wt_truthy')) {
    function wt_truthy($v): bool
    {
        if (is_bool($v))
            return $v;
        $v = strtolower(trim((string) $v));
        return in_array($v, ['1', 'true', 'on', 'yes', 'y'], true);
    }
}

// === Pagination var ===
$paged = max(1, get_query_var('paged') ?: get_query_var('page') ?: 1);
?>
<div class="container">

<?php if (is_home()): ?>
    <?php if (is_paged()): ?>
        <style>
            .listpostsbycats, #main-slider { display:none; }
        </style>
    <?php endif; ?>

    <?php
    // =========================
    // SLIDER (optional)
    // =========================

    // Read controls directly (don’t pre-mangle $slidertag)
    $disable_slider = wt_truthy(get_theme_mod('mediumish_homeslider_active'));  // “Disable home slider”
    $use_recent_posts = wt_truthy(get_theme_mod('mediumish_option_homeslider_recentposts'));  // “Slider by recent posts”
    $slides_count = max(1, (int) get_theme_mod('mediumish_option_homeslider_numberposts'));

    if (!$disable_slider):
        // Base = recent posts
        $slider_args = [
            'post_type' => 'post',
            'posts_per_page' => $slides_count,
            'ignore_sticky_posts' => 1,
            'no_found_rows' => true,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => 'publish',
        ];

        // Only apply tag filter if NOT using “recent posts”
        if (!$use_recent_posts) {
            $raw = get_theme_mod('mediumish_option_homeslider');  // Kirki Select returns one value

            // Sometimes Kirki returns ['value'=>X]
            if (is_array($raw) && array_key_exists('value', $raw)) {
                $raw = $raw['value'];
            }

            if ($raw !== '' && $raw !== null) {
                if (ctype_digit((string) $raw)) {
                    $slider_args['tag__in'] = [(int) $raw];  // tag by ID
                } else {
                    $slider_args['tag_slug__in'] = [(string) $raw];  // tag by slug
                }
            }
        }

        $slider = new WP_Query($slider_args);
        $count = (int) $slider->post_count;

        if ($slider->have_posts()):
            ?>
        
	<div id="main-slider"
     class="carousel slide margb-2"
     data-ride="carousel"
     role="region"
     aria-roledescription="carousel"
     aria-label="<?php esc_attr_e('Featured posts slider', 'mediumish'); ?>">

    <?php if ($count > 1): ?>
        <ol class="carousel-indicators">
            <?php for ($i = 0; $i < $count; $i++): ?>
                <li
                    data-target="#main-slider"
                    data-slide-to="<?php echo esc_attr($i); ?>"
                    aria-label="<?php echo esc_attr(sprintf(__('Slide %d', 'mediumish'), $i + 1)); ?>"
                    <?php echo $i === 0 ? 'class="active" aria-current="true"' : ''; ?>>
                </li>
            <?php endfor; ?>
        </ol>
    <?php endif; ?>

    <div class="carousel-inner" aria-live="polite">
        <?php
        $i = 0;
        while ($slider->have_posts()):
            $slider->the_post();
            wt_seen_push($seen_ids, get_the_ID());
            ?>
            <div class="carousel-item <?php echo $i === 0 ? 'active' : ''; ?>"
                 role="group"
                 aria-roledescription="slide"
                 aria-label="<?php echo esc_attr(($i + 1) . ' / ' . $count); ?>">

                <a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title()); ?>">
                    <?php if (has_post_thumbnail()) : ?>
                        <?php
                        the_post_thumbnail('large', [
                            'class' => 'd-block',
                            'data-no-lazy' => '1',
                            'alt' => the_title_attribute(['echo' => false]),
                        ]);
                        ?>
                    <?php else : ?>
                        <img
                            src="<?php echo esc_url(get_template_directory_uri() . '/assets/img/default.jpg'); ?>"
                            class="d-block"
                            data-no-lazy="1"
                            alt="<?php echo esc_attr(the_title_attribute(['echo' => false])); ?>" />
                    <?php endif; ?>

                    <div class="carousel-caption d-flex h-100 align-items-center">
                        <h3 class="carousel-excerpt d-block">
                            <span class="title d-block"><?php the_title(); ?></span>
                            <span class="fontlight d-block hidden-md-down"><?php echo excerpt(35); ?></span>
                            <span class="btn btn-simple"><?php esc_html_e('Read More', 'mediumish'); ?></span>
                        </h3>
                    </div>
                </a>
            </div>
            <?php
            $i++;
        endwhile;
        ?>
    </div>

    <?php if ($count > 1): ?>
        <a href="#main-slider"
           class="carousel-control-prev"
           data-slide="prev"
           aria-label="<?php esc_attr_e('Previous slide', 'mediumish'); ?>">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        </a>

        <a href="#main-slider"
           class="carousel-control-next"
           data-slide="next"
           aria-label="<?php esc_attr_e('Next slide', 'mediumish'); ?>">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
        </a>
    <?php endif; ?>
</div>
	
    <?php
        endif;
        wp_reset_postdata();
    endif;  // slider
    ?>



    <?php
    // =========================
    // POSTS BY CATEGORIES
    // =========================
    // Collect up to $needed unique posts for a section, honoring $seen_ids.
    // Pages through the category until it fills the quota or runs out.
    // Collect up to $needed unique posts for a section, honoring $seen_ids.
    // Uses paged (stable) pagination and keeps post__not_in constant to avoid skips.
    if (!function_exists('wt_collect_unique_posts')) {
        function wt_collect_unique_posts(array $base_args, int $needed, array $seen_ids): array
        {
            $collected = [];
            $page = 1;
            $per_page = max(10, $needed * 3);  // overfetch each page

            // Build a constant exclusion list for the whole run
            $constant_exclude = array_map('intval', $seen_ids);

            while (count($collected) < $needed) {
                $q = new WP_Query(array_merge($base_args, [
                    'posts_per_page' => $per_page,
                    'paged' => $page,  // stable paging
                    'post__not_in' => $constant_exclude,  // DON'T mutate across passes
                    'ignore_sticky_posts' => 1,
                    'no_found_rows' => true,
                    'orderby' => ['date' => 'DESC', 'ID' => 'DESC'],
                    'order' => 'DESC',
                ]));

                if (!$q->have_posts()) {
                    wp_reset_postdata();
                    break;
                }

                while ($q->have_posts() && count($collected) < $needed) {
                    $q->the_post();
                    $id = get_the_ID();

                    // Skip anything already in global seen or collected in this pass
                    if (in_array($id, $constant_exclude, true)) {
                        continue;
                    }
                    $dup = false;
                    foreach ($collected as $p) {
                        if ((int) $p->ID === (int) $id) {
                            $dup = true;
                            break;
                        }
                    }
                    if ($dup) {
                        continue;
                    }

                    $collected[] = get_post();
                }

                $page++;
                $count = (int) $q->post_count;
                wp_reset_postdata();
                if ($count < $per_page) {
                    break;
                }  // no more posts
            }

            return $collected;
        }
    }

    if ((int) $mediumish_postsbycategory_active === 0 && !empty($postcategories) && is_array($postcategories)):
        foreach ($postcategories as $block):
            // $category    = isset($block['categoryfield']) ? (int) $block['categoryfield'] : 0;
            $rawCat = isset($block['categoryfield']) ? $block['categoryfield'] : '';
            $category = wt_normalize_term_id($rawCat, 'category');  // accepts slug or ID
            $postspp = isset($block['postsperpage']) ? max(1, (int) $block['postsperpage']) : 4;
            $styleoption = isset($block['categorystyle']) ? (string) $block['categorystyle'] : 'style-1';

            if ($category <= 0) {
                continue;
            }

            // Top-up: get exactly $postspp unique posts if available
            $picked = wt_collect_unique_posts([
                'category__in' => [$category],
            ], $postspp, $seen_ids);

            if (empty($picked)) {
                continue;
            }

            // Section title (only on first page)
            if (!is_paged()):
                ?>
            <div class="section-title listpostsbycats">
                <h2 class="d-flex justify-content-between align-items-center">
                    <span><?php echo esc_html(get_cat_name($category)); ?> &nbsp;</span>
                    <a class="d-block morefromcategory" href="<?php echo esc_url(get_category_link($category)); ?>">
                        &nbsp; <i class="fa fa-angle-right"></i>
                    </a>
                </h2>
            </div>
        <?php endif;

            global $post;  // ensure helpers see the right post

            switch ($styleoption) {
                // ================= style-1 =================
                case 'style-1':
                    // Hard-lock style-1 to 5 posts (1 big + 4 small)
                    $postspp = 5;

                    $picked = wt_collect_unique_posts([
                        'category__in' => [$category],
                    ], $postspp, $seen_ids);

                    // Debug (optional): uncomment to verify fill
                    // echo '<!-- style-1 wanted 5, got ' . count($picked) . ' for cat ' . $category . ' -->';

                    if (!empty($picked)): ?>
        <div class="row listrecent listpostsbycats thiscatstyle1 post-style-1">
            <?php
            global $post;

            // Big highlight
            $first = array_shift($picked);
            $post = $first;
            setup_postdata($post);
            ?>
            <div class="col-md-12 col-lg-4 grid-item" id="post-<?php the_ID(); ?>">
                <?php echo mediumish_post_card_highlight_first(); ?>
            </div>
            <?php $seen_ids[] = (int) get_the_ID();
            wp_reset_postdata(); ?>

            <div class="col-md-12 col-lg-8">
                <div class="row h-100">
                    <?php
                    // Always render up to 4 small cards
                    $picked = array_slice($picked, 0, 4);
                    foreach ($picked as $p):
                        $post = $p;
                        setup_postdata($post);
                        ?>
                        <div class="col-md-6 col-lg-6 grid-item" id="post-<?php the_ID(); ?>">
                            <?php echo mediumish_post_card_after_highlight(); ?>
                        </div>
                        <?php $seen_ids[] = (int) get_the_ID(); ?>
                    <?php endforeach;
                    wp_reset_postdata(); ?>
                </div>
            </div>

            <div class="clearfix"></div>
        </div>
    <?php endif;
                    break;

                // ================= style-2 =================
                case 'style-2': ?>
                <section class="featured-posts listpostsbycats post-style-2">
                    <div class="row listfeaturedtag h-100">
                        <?php foreach ($picked as $p):
                            $post = $p;
                            setup_postdata($post); ?>
                            <div class="col-md-6 mb-30" id="post-<?php the_ID(); ?>">
                                <?php echo mediumish_post_card_tall(); ?>
                            </div>
                            <?php $seen_ids[] = (int) get_the_ID(); ?>
                        <?php endforeach;
                        wp_reset_postdata(); ?>
                    </div>
                </section>
                <?php
                break;

            // ================= style-3 =================
            case 'style-3':
                ?>
                <section class="poststyle-3 post-style-3 listpostsbycats">
                    <div class="row h-100">
                        <?php foreach ($picked as $p):
                            $post = $p;
                            setup_postdata($post); ?>
                            <div class="col-md-4 mb-30" id="post-<?php the_ID(); ?>">
                                <?php echo mediumish_postbox_default(); ?>
                            </div>
                            <?php $seen_ids[] = (int) get_the_ID(); ?>
                        <?php endforeach;
                        wp_reset_postdata(); ?>
                    </div>
                </section>
                <?php
                break;

            // ================= style-4 =================
            case 'style-4':
                ?>
                <section class="post-style-4 listpostsbycats">
                    <div class="row h-100">
                        <?php foreach ($picked as $p):
                            $post = $p;
                            setup_postdata($post); ?>
                            <div class="col-md-3 mb-30" id="post-<?php the_ID(); ?>">
                                <?php echo mediumish_postbox_default(); ?>
                            </div>
                            <?php $seen_ids[] = (int) get_the_ID(); ?>
                        <?php endforeach;
                        wp_reset_postdata(); ?>
                    </div>
                </section>
                <?php
                break;

            // ================= style-5 =================
            case 'style-5':
                ?>
                <section class="post-style-5 listpostsbycats">
                    <div class="row h-100">
                        <?php foreach ($picked as $p):
                            $post = $p;
                            setup_postdata($post); ?>
                            <div class="col-md-4 mb-30" id="post-<?php the_ID(); ?>">
                                <?php echo mediumish_post_card_tall(); ?>
                            </div>
                            <?php $seen_ids[] = (int) get_the_ID(); ?>
                        <?php endforeach;
                        wp_reset_postdata(); ?>
                    </div>
                </section>
                <?php
                break;

            // ================= style-6 =================
            case 'style-6':
                ?>
                <section class="post-style-6 listpostsbycats">
                    <div class="row h-100">
                        <?php foreach ($picked as $p):
                            $post = $p;
                            setup_postdata($post); ?>
                            <div class="col-md-6 mb-30" id="post-<?php the_ID(); ?>">
                                <?php echo mediumish_postbox_default(); ?>
                            </div>
                            <?php $seen_ids[] = (int) get_the_ID(); ?>
                        <?php endforeach;
                        wp_reset_postdata(); ?>
                    </div>
                </section>
                <?php
                break;

            // ================= default → style-1 =================
            default:
                ?>
                <div class="row listrecent listpostsbycats thiscatstyle1 post-style-1">
                    <?php
                    // İlk kartı geri koy, hepsini eşit 4 sütunda göster
                    array_unshift($picked, $first ?? null);
                    foreach ($picked as $p):
                        if (!$p) continue;
                        $post = $p;
                        setup_postdata($post); ?>
                        <div class="col-md-6 col-lg-3 grid-item" id="post-<?php the_ID(); ?>">
                            <?php echo mediumish_postbox_default(); ?>
                        </div>
                        <?php $seen_ids[] = (int) get_the_ID();
                    endforeach;
                    wp_reset_postdata(); ?>
                </div>
                <?php
            }  // switch
        endforeach;
    endif;  // posts by categories
    ?>


<?php endif; // is_home ?>

    <div class="clearfix"></div>

    <!-- =========================
         BLOG POSTS — ALL STORIES
    ========================== -->
    <section class="recent-posts">
        <div class="section-title">
            <h2>
                <?php
                if (is_search()) {
                    echo 'Search results for: <span>' . esc_html(get_query_var('s')) . '</span>';
                } elseif (is_archive()) {
                    echo '<span>' . mediumish_archive_title() . '</span>';
                } else {
                    echo '<span>' . esc_html($mediumish_allstories) . '</span>';
                }
                ?>
            </h2>
        </div>

        <?php
        // Main list: only remaining posts, paginated, excluding everything seen
        $main_q = new WP_Query([
            'post_type' => 'post',
            'post__not_in' => array_map('intval', $seen_ids),
            'paged' => $paged,
            'ignore_sticky_posts' => 1,
            'orderby' => ['date' => 'DESC', 'ID' => 'DESC'],
        ]);

        if ($main_q->have_posts()):
            ?>
            <div class="row listrecent">
                <?php while ($main_q->have_posts()):
                    $main_q->the_post(); ?>
                    <div class="col-md-6 col-lg-3 grid-item" id="post-<?php the_ID(); ?>">
                        <?php echo mediumish_postbox_default(); ?>
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="bottompagination">
                <?php
                if (function_exists('wp_bootstrap_pagination')) {
                    wp_bootstrap_pagination([
                        'custom_query' => true,
                        'custom_query' => $main_q,
                        'previous_string' => '<i class="fa fa-angle-double-left"></i>',
                        'next_string' => '<i class="fa fa-angle-double-right"></i>',
                        'before_output' => '<span class="navigation">',
                        'after_output' => '</span>',
                    ]);
                } else {
                    // Native fallback
                    the_posts_pagination([
                        'mid_size' => 2,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                    ]);
                }
                ?>
            </div>
        <?php else: ?>
            <p><?php _e('Sorry, no posts matched your criteria.', 'mediumish'); ?></p>
        <?php
        endif;
        wp_reset_postdata();
        ?>
    </section>

    <!-- =========================
         JUMBO CATEGORIES CLOUD
    ========================== -->
    <?php if ((int) $mediumish_homecategorycloud_active === 0): ?>
        <div class="jumbotron fortags mt-4"<?php echo $mediumish_homecategorycloud_bg ? ' style="background-image:url(' . esc_url($mediumish_homecategorycloud_bg) . ');"' : ''; ?>>
            <div class="row">
                <div class="col-md-4 align-self-center text-center">
                    <h2 class="hidden-sm-down text-white"><?php _e('Explore', 'mediumish'); ?> &rarr;</h2>
                </div>
                <div class="col-md-8 align-self-center text-center">
                    <?php wp_tag_cloud(['taxonomy' => 'category']); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div><!-- /.container -->

</main>

<?php get_footer(); ?>