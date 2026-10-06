<?php
/**
 * Plugin Name: WordPress RSS Bot
 * Description: Fetches RSS articles into WordPress posts, with an optional category for each feed.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL-3.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WORDPRESS_RSS_BOT_OPTION', 'wordpress_rss_bot_feeds' );
define( 'WORDPRESS_RSS_BOT_CRON_HOOK', 'wordpress_rss_bot_fetch_feeds' );

function wordpress_rss_bot_cron_schedules( $schedules ) {
	$schedules['wordpress_rss_bot_50_seconds'] = array(
		'interval' => 50,
		'display'  => __( 'Every 50 seconds', 'wordpress-rss-bot' ),
	);

	return $schedules;
}
add_filter( 'cron_schedules', 'wordpress_rss_bot_cron_schedules' );

function wordpress_rss_bot_schedule_fetch() {
	if ( ! wp_next_scheduled( WORDPRESS_RSS_BOT_CRON_HOOK ) ) {
		wp_schedule_event( time() + 50, 'wordpress_rss_bot_50_seconds', WORDPRESS_RSS_BOT_CRON_HOOK );
	}
}
add_action( 'init', 'wordpress_rss_bot_schedule_fetch' );
register_activation_hook( __FILE__, 'wordpress_rss_bot_schedule_fetch' );

function wordpress_rss_bot_deactivate() {
	wp_clear_scheduled_hook( WORDPRESS_RSS_BOT_CRON_HOOK );
}
register_deactivation_hook( __FILE__, 'wordpress_rss_bot_deactivate' );

function wordpress_rss_bot_add_admin_menu() {
	add_management_page(
		__( 'RSS Bot', 'wordpress-rss-bot' ),
		__( 'RSS Bot', 'wordpress-rss-bot' ),
		'manage_options',
		'wordpress-rss-bot',
		'wordpress_rss_bot_render_admin_page'
	);
}
add_action( 'admin_menu', 'wordpress_rss_bot_add_admin_menu' );

function wordpress_rss_bot_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$feeds = get_option( WORDPRESS_RSS_BOT_OPTION, array() );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'RSS Bot', 'wordpress-rss-bot' ); ?></h1>
		<p><?php esc_html_e( 'Add RSS sources to automatically publish their new articles as WordPress posts.', 'wordpress-rss-bot' ); ?></p>

		<h2><?php esc_html_e( 'Add a source', 'wordpress-rss-bot' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wordpress_rss_bot_add_feed">
			<?php wp_nonce_field( 'wordpress_rss_bot_add_feed' ); ?>
			<p>
				<label for="wordpress-rss-bot-url"><?php esc_html_e( 'RSS URL', 'wordpress-rss-bot' ); ?></label><br>
				<input id="wordpress-rss-bot-url" name="feed_url" type="url" class="regular-text" required>
			</p>
			<p>
				<label for="wordpress-rss-bot-category"><?php esc_html_e( 'Category (optional)', 'wordpress-rss-bot' ); ?></label><br>
				<?php
				wp_dropdown_categories(
					array(
						'show_option_none' => __( 'Use the default category', 'wordpress-rss-bot' ),
						'option_none_value' => '0',
						'hide_empty'        => 0,
						'name'              => 'category_id',
						'id'                => 'wordpress-rss-bot-category',
						'selected'          => 0,
					)
				);
				?>
			</p>
			<?php submit_button( __( 'Add source', 'wordpress-rss-bot' ) ); ?>
		</form>

		<h2><?php esc_html_e( 'RSS sources', 'wordpress-rss-bot' ); ?></h2>
		<?php if ( empty( $feeds ) ) : ?>
			<p><?php esc_html_e( 'No RSS sources have been added yet.', 'wordpress-rss-bot' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Source', 'wordpress-rss-bot' ); ?></th>
						<th><?php esc_html_e( 'Category', 'wordpress-rss-bot' ); ?></th>
						<th><?php esc_html_e( 'Action', 'wordpress-rss-bot' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $feeds as $feed ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( $feed['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $feed['url'] ); ?></a></td>
							<td>
								<?php
								$category = ! empty( $feed['category_id'] ) ? get_category( absint( $feed['category_id'] ) ) : false;
								echo $category ? esc_html( $category->name ) : esc_html__( 'Default category', 'wordpress-rss-bot' );
								?>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="wordpress_rss_bot_delete_feed">
									<input type="hidden" name="feed_id" value="<?php echo esc_attr( $feed['id'] ); ?>">
									<?php wp_nonce_field( 'wordpress_rss_bot_delete_feed_' . $feed['id'] ); ?>
									<?php submit_button( __( 'Remove', 'wordpress-rss-bot' ), 'delete', 'submit', false ); ?>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

function wordpress_rss_bot_handle_add_feed() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage RSS sources.', 'wordpress-rss-bot' ) );
	}
	check_admin_referer( 'wordpress_rss_bot_add_feed' );

	$url = isset( $_POST['feed_url'] ) ? esc_url_raw( wp_unslash( $_POST['feed_url'] ) ) : '';
	$parsed_url = wp_parse_url( $url );
	if ( ! $url || ! wp_http_validate_url( $url ) || empty( $parsed_url['scheme'] ) || ! in_array( strtolower( $parsed_url['scheme'] ), array( 'http', 'https' ), true ) ) {
		wp_die( esc_html__( 'Enter a valid HTTP or HTTPS RSS URL.', 'wordpress-rss-bot' ) );
	}

	$category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
	if ( $category_id && ! term_exists( $category_id, 'category' ) ) {
		$category_id = 0;
	}

	$feeds   = get_option( WORDPRESS_RSS_BOT_OPTION, array() );
	$feeds[] = array(
		'id'          => wp_generate_uuid4(),
		'url'         => $url,
		'category_id' => $category_id,
	);
	update_option( WORDPRESS_RSS_BOT_OPTION, $feeds );

	wp_safe_redirect( admin_url( 'tools.php?page=wordpress-rss-bot' ) );
	exit;
}
add_action( 'admin_post_wordpress_rss_bot_add_feed', 'wordpress_rss_bot_handle_add_feed' );

function wordpress_rss_bot_handle_delete_feed() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage RSS sources.', 'wordpress-rss-bot' ) );
	}

	$feed_id = isset( $_POST['feed_id'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_id'] ) ) : '';
	check_admin_referer( 'wordpress_rss_bot_delete_feed_' . $feed_id );

	$feeds = get_option( WORDPRESS_RSS_BOT_OPTION, array() );
	$feeds = array_values(
		array_filter(
			$feeds,
			static function ( $feed ) use ( $feed_id ) {
				return isset( $feed['id'] ) && $feed['id'] !== $feed_id;
			}
		)
	);
	update_option( WORDPRESS_RSS_BOT_OPTION, $feeds );

	wp_safe_redirect( admin_url( 'tools.php?page=wordpress-rss-bot' ) );
	exit;
}
add_action( 'admin_post_wordpress_rss_bot_delete_feed', 'wordpress_rss_bot_handle_delete_feed' );

function wordpress_rss_bot_fetch_feeds() {
	if ( ! function_exists( 'fetch_feed' ) ) {
		require_once ABSPATH . WPINC . '/feed.php';
	}

	$feeds = get_option( WORDPRESS_RSS_BOT_OPTION, array() );
	foreach ( $feeds as $source ) {
		if ( empty( $source['url'] ) || empty( $source['id'] ) ) {
			continue;
		}

		$feed = fetch_feed( $source['url'] );
		if ( is_wp_error( $feed ) ) {
			continue;
		}

		$items = $feed->get_items( 0, 20 );
		foreach ( (array) $items as $item ) {
			$item_id = $item->get_id( true );
			if ( ! $item_id ) {
				$item_id = $item->get_permalink();
			}
			if ( ! $item_id ) {
				continue;
			}

			$item_hash = hash( 'sha256', $source['id'] . '|' . $item_id );
			$existing  = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'any',
					'meta_key'       => '_wordpress_rss_bot_item_hash',
					'meta_value'     => $item_hash,
					'fields'         => 'ids',
					'posts_per_page' => 1,
				)
			);
			if ( $existing ) {
				continue;
			}

			$title   = sanitize_text_field( $item->get_title() );
			$content = $item->get_content();
			if ( ! $content ) {
				$content = $item->get_description();
			}

			$post_data = array(
				'post_title'   => $title ? $title : esc_url_raw( $item->get_permalink() ),
				'post_content' => wp_kses_post( $content ),
				'post_status'  => 'publish',
				'post_type'    => 'post',
			);
			$category_id = isset( $source['category_id'] ) ? absint( $source['category_id'] ) : 0;
			if ( $category_id && term_exists( $category_id, 'category' ) ) {
				$post_data['post_category'] = array( $category_id );
			}

			$post_id = wp_insert_post( $post_data, true );
			if ( ! is_wp_error( $post_id ) && $post_id ) {
				update_post_meta( $post_id, '_wordpress_rss_bot_item_hash', $item_hash );
				update_post_meta( $post_id, '_wordpress_rss_bot_source_url', esc_url_raw( $item->get_permalink() ) );
			}
		}
	}
}
add_action( WORDPRESS_RSS_BOT_CRON_HOOK, 'wordpress_rss_bot_fetch_feeds' );
