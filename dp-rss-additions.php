<?php
/*/
Plugin Name: Denver Post RSS feed additions
Plugin URI: www.denverpost.com
Description: Alters Wordpress RSS feeds to include additional information used for Blogs Rotator & Simplepie Widgets, and Stubs. Set feeds to 'excerpt' and you can still get the full-text feeds with blog.url/?feed=full.
Version: 0.7b
Author: Daniel J. Schneider
Author URI: schneidan.com
/*/

/**
 * Everything up here is about the plugin options in the admin area
 */

defined('ABSPATH') or die("No script kiddies please!");

function install_callback(){
    global $dprss_options;
    if( !isset($dprss_options['limit_posts']) )
        $dprss_options['limit_posts'] = '1';
}
register_activation_hook(__FILE__, 'install_callback');

// add the admin settings and such
function dprss_admin_init(){
    register_setting( 'dprss_options', 'dprss_options', 'dprss_options_validate' );
    add_settings_section('dprss_mrss', 'MRSS Settings', 'dprss_section_text', 'dprss');
    add_settings_field('mrss_image_copyright', 'MRSS image copyright org.', 'dprss_setting_string', 'dprss', 'dprss_mrss');
    add_settings_field('disable_namespace', 'Disable MRSS namespace?', 'dprss_ns_setting_string', 'dprss', 'dprss_mrss');
    add_settings_field('strip_shortcodes', 'Strip shortcodes in content?', 'dprss_strip_string', 'dprss', 'dprss_mrss');
    add_settings_field('limit_posts', 'Limit feed to 30 days?', 'dprss_limit_posts_string', 'dprss', 'dprss_mrss');
}
add_action('admin_init', 'dprss_admin_init');

// display the admin options page
function dprss_options_page() {
?>
    <div>
    <h2>Denver Post RSS Additions plugin options</h2>
    <form action="options.php" method="post">
    <?php settings_fields('dprss_options'); ?>
    <?php do_settings_sections('dprss'); ?>
     
    <input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
    </form></div>
<?php
}

// add the admin options page
function dprss_admin_add_page() {
    add_options_page('DP RSS Plugin Page', 'DP RSS Adds', 'manage_options', 'dprss_options', 'dprss_options_page');
}
add_action('admin_menu', 'dprss_admin_add_page');

function dprss_section_text() {
    echo '<p>MRSS Copyright image organization should be your publication (i.e. \'The Denver Post\')</p>';
}

function dprss_setting_string() {
    $options = get_option('dprss_options');
    echo "<input id='mrss_image_copyright' name='dprss_options[mrss_image_copyright]' size='40' type='text' value='{$options['mrss_image_copyright']}' />";
}
function dprss_ns_setting_string() {
    $options = get_option('dprss_options');
    echo '<input type="checkbox" id="disable_namespace" name="dprss_options[disable_namespace]" value="1" ' . checked( 1, $options['disable_namespace'], false) . '/> Only disable inclusion of the MRSS namespace in the XML headers if you are seeing "duplicate namespace" errors.';
}
function dprss_strip_string() {
    $options = get_option('dprss_options');
    echo '<input type="checkbox" id="strip_shortcodes" name="dprss_options[strip_shortcodes]" value="1" ' . checked( 1, $options['strip_shortcodes'], false) . '/> Removes images, maps, tweets, etc. embedded in posts using shortcodes.';
}
function dprss_limit_posts_string() {
    $options = get_option('dprss_options');
    echo '<input type="checkbox" id="limit_posts" name="dprss_options[limit_posts]" value="1" ' . checked( 1, $options['limit_posts'], false) . '/> Limits posts in the full feed to only those published within the last 30 days.';
}

// validate our options
function dprss_options_validate($input) {
    $newinput['mrss_image_copyright'] = trim($input['mrss_image_copyright']);
    if(!preg_match('/^[a-z0-9 .\-]+$/i', $newinput['mrss_image_copyright'])) {
        $newinput['mrss_image_copyright'] = '';
    }
    $newinput['disable_namespace'] = ( $input['disable_namespace'] ) ? 1 : 0;
    $newinput['strip_shortcodes'] = ( $input['strip_shortcodes'] ) ? 1 : 0;
    $newinput['limit_posts'] = ( $input['limit_posts'] ) ? 1 : 0;
    return $newinput;
}

/**
 * Actually doing stuff down here
 */

// Add MRSS content for images to RSS feed, depending on user options
function dprss_namespace() {
    $dprss_options_arr = get_option('dprss_options');
    if ( ! $dprss_options_arr['disable_namespace'] ) {
        echo 'xmlns:media="http://search.yahoo.com/mrss/" 
        xmlns:georss="http://www.georss.org/georss" 
        ';
    }
}
add_filter( 'rss2_ns', 'dprss_namespace' );

function dprss_attached_images() {
    global $post;
    $dprss_options_arr = get_option('dprss_options');
    $mrss_copyright = ($dprss_options_arr) ? $dprss_options_arr['mrss_image_copyright'] : 'The Denver Post';
    $attachments = get_posts( array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'posts_per_page' => -1,
        'post_parent' => $post->ID
    ) );
    if ( ! $attachments && has_post_thumbnail( $post->ID ) ) {
        $attachments = array( get_post( get_post_thumbnail_id( $post->ID ) ) );
    }
    if ( $attachments ) {
        foreach ( $attachments as $att ) {
            $img_attr = wp_get_attachment_image_src( $att->ID, 'full' );
            ?>
            <media:content url="<?php echo $img_attr[0]; ?>" width="<?php echo $img_attr[1]; ?>" height="<?php echo $img_attr[2]; ?>" medium="image" type="<?php echo $att->post_mime_type; ?>">
                <media:title type="plain"><![CDATA[<?php echo html_entity_decode( $att->post_title ); ?>]]></media:title>
                <media:copyright><?php echo $mrss_copyright; ?></media:copyright>
                <media:description type="plain"><![CDATA[<?php echo $att->post_excerpt; ?>]]></media:description>
            </media:content>
            <media:thumbnail url="<?php echo $img_attr[0]; ?>" width="<?php echo $img_attr[1]; ?>" height="<?php echo $img_attr[2]; ?>" />
            <?php
        }
    }
}
add_filter( 'rss2_item', 'dprss_attached_images' );

// Strip shortcodes if a use has selected the option
function dprss_strip_shortcodes( $content ) {
        $dprss_options_arr = get_option('dprss_options');
        return ( is_feed() && $dprss_options_arr['strip_shortcodes'] ) ? strip_shortcodes( $content ) : $content;
}
add_filter('the_content', 'dprss_strip_shortcodes');
add_filter('the_excerpt', 'dprss_strip_shortcodes');

function dprss_override_curly_quotes( $content ) {
    global $post;
    $dprss_options_arr = get_option('dprss_options');
    if ( is_feed() ) {
        remove_filter( 'the_content', 'wptexturize' );
        remove_filter( 'the_excerpt', 'wptexturize' );
        remove_filter( 'the_title', 'wptexturize' );
        $search = array(chr(145),
                    chr(146),
                    chr(147),
                    chr(148),
                    chr(151));
        $replace = array("'",
                         "'",
                         '"',
                         '"',
                         '-');
        str_replace($search, $replace, $content); 
        return $content;
    } else {
        return $content;
    }
}
add_filter( 'the_content', 'dprss_override_curly_quotes', 7 );
add_filter( 'the_excerpt', 'dprss_override_curly_quotes', 7 );
add_filter( 'the_title', 'dprss_override_curly_quotes', 7 );

function dprss_dublin_core_adds() {
    ?>
        <dc:publisher><![CDATA[ <?php echo get_bloginfo('name'); ?> ]]></dc:publisher>
        <dc:source><![CDATA[ <?php echo site_url(); ?> ]]></dc:source>
    <?php
}
add_filter('rss2_item','dprss_dublin_core_adds');

// add a full-text RSS feed as an option
function dprss_full_feed() {
	add_filter('pre_option_rss_use_excerpt', '__return_zero');
	load_template( ABSPATH . WPINC . '/feed-rss2.php' );
}
function dprss_add_full_feed() {
    add_feed( 'full', 'dprss_full_feed' );
}
add_action( 'init', 'dprss_add_full_feed' );

function dprss_rewrite_flush() {
    dprss_add_full_feed();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'dprss_rewrite_flush' );

function dprss_custom_feed_query( $query ) {
    $dprss_options_arr = get_option('dprss_options');
    if ( $query->is_feed( array( 'full' ) ) && $dprss_options_arr['limit_posts'] ) {
        // this filter is used to alter the where statement for the feed
        add_filter( 'posts_where', 'dprss_filter_post_age' );
    }
    return $query;
}
add_filter( 'pre_get_posts', 'dprss_custom_feed_query' );

function dprss_filter_post_age( $where = '' ) {
    global $wpdb;
    $where .= $wpdb->prepare( " AND $wpdb->posts.post_date > %s", date( 'Y-m-d', strtotime( '-30 days' ) ) );
    return $where;
}
