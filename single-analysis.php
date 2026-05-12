<?php
/**
 * The template for displaying single Analysis posts
 * Direkt URL ile açıldığında analiz yazısını tam sayfa gösterir
 *
 * @package Mediumish / TheTelos
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();
?>

<main id="main" role="main">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">

            <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>

                <?php
                $related_book_id = (int) get_post_meta( get_the_ID(), '_related_book_id', true );
                $related_book    = $related_book_id ? get_post( $related_book_id ) : null;
                ?>

                <?php /* Kitaba geri link */ ?>
                <?php if ( $related_book ) : ?>
                <div style="margin: 28px 0 20px;">
                    <a href="<?php echo esc_url( get_permalink( $related_book->ID ) ); ?>"
                       style="display:inline-flex;align-items:center;gap:6px;color:#00ab6b;text-decoration:none;font-size:13px;font-family:sans-serif;letter-spacing:0.02em;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <path d="M19 12H5M12 5l-7 7 7 7"/>
                        </svg>
                        <?php echo esc_html( $related_book->post_title ); ?>
                    </a>
                </div>
                <?php endif; ?>

                <?php /* Başlık */ ?>
                <div class="mainheading">
                    <div style="font-size:10px;letter-spacing:0.2em;text-transform:uppercase;color:#aaa;font-family:sans-serif;margin-bottom:10px;">
                        Deep Analysis
                    </div>
                    <h1 class="posttitle"><?php the_title(); ?></h1>
                    <?php if ( function_exists( 'thetelos_analysis_reading_time' ) ) : ?>
                    <p>
                        <span class="readingtime"><?php echo esc_html( thetelos_analysis_reading_time( get_post() ) ); ?></span>
                    </p>
                    <?php endif; ?>
                </div>

                <?php /* İçerik */ ?>
                <article class="article-post">
                    <?php the_content(); ?>
                </article>

                <?php /* Alt kısım — kitaba dön kutusu */ ?>
                <?php if ( $related_book ) : ?>
                <div style="margin-top:48px;padding:24px 28px;background:#f8f8f8;border-radius:6px;font-family:sans-serif;">
                    <div style="font-size:10px;text-transform:uppercase;letter-spacing:0.15em;color:#aaa;margin-bottom:10px;">
                        Book Summary
                    </div>
                    <a href="<?php echo esc_url( get_permalink( $related_book->ID ) ); ?>"
                       style="color:#00ab6b;font-size:16px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M19 12H5M12 5l-7 7 7 7"/>
                        </svg>
                        <?php echo esc_html( $related_book->post_title ); ?>
                    </a>
                </div>
                <?php endif; ?>

            <?php endwhile; endif; ?>

        </div>
    </div>
</div>
</main>

<?php get_footer(); ?>
