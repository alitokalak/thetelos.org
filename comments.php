<?php
/**
 * The template for displaying comments
 *
 * @package Mediumish
 */

if (!defined('ABSPATH')) {
    exit;
}

if ( post_password_required() ) {
    return;
}
?>

<div class="row justify-content-center">
<div class="col-md-8">
<div id="comments" class="comments-area">

<?php if ( have_comments() ) : ?>

    <h2 class="comments-title">
        <?php
        $comments_number = get_comments_number();
        if ( $comments_number == 1 ) {
            echo 'One Reply';
        } else {
            echo $comments_number . ' Replies';
        }
        ?>
    </h2>

    <ol class="comment-list">
        <?php
        wp_list_comments(array(
            'avatar_size' => 36,
            'style' => 'ol',
            'short_ping' => true,
            'reply_text' => 'Reply',
        ));
        ?>
    </ol>

<?php endif; ?>

<?php if ( ! comments_open() ) : ?>
    <p class="no-comments">Comments are closed.</p>
<?php endif; ?>


<?php
comment_form(array(

    'title_reply' => 'Leave a Reply',

    'label_submit' => 'Post Comment',

    'comment_notes_before' => '',

    'comment_notes_after' => '',

    'fields' => array(

        'author' =>
        '<p class="comment-form-author">
            <label>Name</label>
            <input type="text" name="author" required>
        </p>',

        'email' =>
        '<p class="comment-form-email">
            <label>Email</label>
            <input type="email" name="email" required>
        </p>',

        'url' =>
        '<p class="comment-form-url">
            <label>Website</label>
            <input type="url" name="url">
        </p>',

        'cookies' =>
        '<p class="comment-form-cookies-consent">
            <input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes">
            <label for="wp-comment-cookies-consent">
                Save my name, email, and website in this browser for the next time I comment.
            </label>
        </p>',
    ),

    'comment_field' =>
    '<p class="comment-form-comment">
        <label>Comment</label>
        <textarea name="comment" required></textarea>
    </p>',

));
?>

</div>
</div>
</div>