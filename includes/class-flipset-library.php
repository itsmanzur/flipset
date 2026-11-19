<?php
/**
 * Flipset_Library
 *
 * Front-end PDF gallery: [flipset_library] shortcode. Grid of Media
 * Library PDFs with stored thumbnails; clicking a card expands the
 * existing Flipset viewer in place.
 *
 * @package Flipset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end PDF library: the [flipset_library] shortcode, shared with the block and page-builder widgets.
 */
class Flipset_Library {

	use Flipset_Att_Flag;

	/**
	 * Whether the library assets were already enqueued in this request.
	 *
	 * @var bool
	 */
	private $assets_enqueued = false;

	/**
	 * Per-request counter so pagination query vars stay stable when
	 * nested viewer unique IDs increment inside each grid item.
	 *
	 * @var int
	 */
	private static $instance_n = 0;

	/**
	 * Register the [flipset_library] shortcode.
	 */
	public function init() {
		add_shortcode( 'flipset_library', array( $this, 'render_shortcode' ) );
	}

	/**
	 * [flipset_library columns="3" per_page="12" orderby="date" order="DESC" search="true"
	 * sort_control="false" layout="grid" layout_toggle="false"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'columns'       => '3',
				'per_page'      => '12',
				'orderby'       => 'date',
				'order'         => 'DESC',
				'search'        => 'true',
				'sort_control'  => 'false',
				'layout'        => 'grid',
				'layout_toggle' => 'false',
			),
			$atts,
			'flipset_library'
		);

		$columns   = $this->clamp_int( $atts['columns'], 1, 6, 3 );
		$per_page  = $this->clamp_int( $atts['per_page'], 1, 48, 12 );
		$orderby   = $this->sanitize_orderby( $atts['orderby'] );
		$order     = ( 'ASC' === strtoupper( $atts['order'] ) ) ? 'ASC' : 'DESC';
		$search_on = $this->att_flag( $atts['search'] ?? 'true', true );
		$sort_on   = $this->att_flag( $atts['sort_control'] ?? 'false', false );
		$layout    = $this->sanitize_layout( $atts['layout'] ?? 'grid' );
		$toggle_on = $this->att_flag( $atts['layout_toggle'] ?? 'false', false );

		$uid         = ++self::$instance_n;
		$instance_id = 'flipset-library-' . $uid;
		$query_var   = 'flipset_page_' . $uid;
		$search_var  = 'flipset_q_' . $uid;
		$sort_var    = 'flipset_sort_' . $uid;
		$wrap_class  = 'flipset-library' . ( 'list' === $layout ? ' is-layout-list' : '' );
		$paged       = 1;
		if ( isset( $_GET[ $query_var ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public pagination query var.
			$paged = max( 1, absint( wp_unslash( $_GET[ $query_var ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public pagination query var.
		}
		$search = '';
		if ( $search_on && isset( $_GET[ $search_var ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public search query var.
			$search = sanitize_text_field( wp_unslash( $_GET[ $search_var ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public search query var.
		}

		// The sort menu only does anything when the page asked for it; otherwise the query var is ignored.
		$sort     = null;
		$sort_key = '';
		if ( $sort_on ) {
			$requested = '';
			if ( isset( $_GET[ $sort_var ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public sort query var.
				$requested = sanitize_key( wp_unslash( $_GET[ $sort_var ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public sort query var.
			}
			list( $orderby, $order, $sort_key ) = $this->resolve_sort( $orderby, $order, $requested );
			$sort                               = array(
				'var'      => $sort_var,
				'selected' => $sort_key,
				'options'  => $this->sort_options( $sort_key ),
			);
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => 'application/pdf',
				'posts_per_page'         => $per_page,
				'paged'                  => $paged,
				'orderby'                => $orderby,
				'order'                  => $order,
				's'                      => $search,
				'no_found_rows'          => false,
				'flipset_content_search' => ( '' !== $search ),
			)
		);

		$head  = $sort_on ? $this->render_status( (int) $query->found_posts, $sort_key ) : '';
		$head .= $this->render_controls( $search_on, $search, $search_var, $query_var, $sort, $toggle_on, $layout );

		if ( ! $query->have_posts() ) {
			wp_enqueue_style(
				'flipset-library',
				FLIPSET_URL . 'assets/css/flipset-library.css',
				array(),
				FLIPSET_VERSION
			);
			if ( $sort_on && '' !== $search ) {
				// A shared "no results" link: keep the sort menu working in place.
				$this->enqueue_assets();
			}
			wp_reset_postdata();
			// Same id/data attributes as the populated wrapper, so the in-place search and
			// pagination (which look the container up by id) also work when the result is empty.
			$html  = '<div id="' . esc_attr( $instance_id ) . '" class="' . esc_attr( $wrap_class ) . '" data-columns="' . esc_attr( (string) $columns ) . '">';
			$html .= $head;
			$html .= $this->render_empty( $search );
			$html .= '</div>';
			return wp_kses( $html, Flipset_Viewer::allowed_html() );
		}

		$this->enqueue_assets();

		ob_start();
		?>
		<div id="<?php echo esc_attr( $instance_id ); ?>" class="<?php echo esc_attr( $wrap_class ); ?>" data-columns="<?php echo esc_attr( (string) $columns ); ?>">
			<?php echo wp_kses( $head, Flipset_Viewer::allowed_html() ); ?>
			<ul class="flipset-library-grid" role="list" style="--flipset-cols: <?php echo esc_attr( (string) $columns ); ?>;">
				<?php foreach ( $query->posts as $attachment ) : ?>
					<?php echo wp_kses( $this->render_item( $attachment ), Flipset_Viewer::allowed_html() ); ?>
				<?php endforeach; ?>
			</ul>
			<?php echo wp_kses( $this->render_pagination( $query, $paged, $query_var, $search_var, $search ), Flipset_Viewer::allowed_html() ); ?>
		</div>
		<?php
		wp_reset_postdata();
		return wp_kses( (string) ob_get_clean(), Flipset_Viewer::allowed_html() );
	}

	/**
	 * Markup for one library card and its hidden in-place viewer.
	 *
	 * @param WP_Post $attachment PDF attachment.
	 * @return string HTML, or an empty string when the file has no URL.
	 */
	private function render_item( $attachment ) {
		$id  = (int) $attachment->ID;
		$url = wp_get_attachment_url( $id );
		if ( ! $url ) {
			return '';
		}

		$title = get_the_title( $attachment );
		if ( '' === $title ) {
			$title = wp_basename( $url );
		}

		$embed_id = 'flipset-library-embed-' . wp_unique_id();
		$thumb_id = Flipset_Thumbnails::get_thumbnail_id( $id );
		$image    = '';
		if ( $thumb_id ) {
			$image = wp_get_attachment_image(
				$thumb_id,
				'medium',
				false,
				array(
					'class'    => 'flipset-library-image',
					'loading'  => 'lazy',
					'decoding' => 'async',
					// Decorative: the title is printed right next to it inside the same button.
					'alt'      => '',
				)
			);
		}
		if ( ! $image ) {
			$image = '<span class="flipset-library-placeholder" aria-hidden="true">' . $this->placeholder_icon() . '</span>';
		}

		$meta = $this->item_meta( $id );

		$viewer = Flipset::instance()->viewer->render_shortcode(
			array(
				'file'  => $url,
				'width' => '100%',
				'hash'  => 'false',
			)
		);
		$viewer = str_replace( ' class="flipset-viewer"', ' class="flipset-viewer" data-flipset-defer="1"', $viewer );

		ob_start();
		?>
		<li class="flipset-library-item">
			<button type="button" class="flipset-library-card" aria-expanded="false" aria-controls="<?php echo esc_attr( $embed_id ); ?>">
				<span class="flipset-library-thumb"><?php echo wp_kses( $image, Flipset_Viewer::allowed_html() ); ?></span>
				<span class="flipset-library-body">
					<span class="flipset-library-title"><?php echo esc_html( $title ); ?></span>
					<?php if ( '' !== $meta ) : ?>
						<span class="flipset-library-meta"><?php echo esc_html( $meta ); ?></span>
					<?php endif; ?>
				</span>
			</button>
			<div id="<?php echo esc_attr( $embed_id ); ?>" class="flipset-library-embed" hidden>
				<?php echo wp_kses( $viewer, Flipset_Viewer::allowed_html() ); ?>
			</div>
		</li>
		<?php
		return ob_get_clean();
	}

	/**
	 * Muted one-line description under the title: "PDF · 12 pages · 1.2 MB". The size
	 * comes from the attachment metadata WordPress already stores; the file is only
	 * stat-ed when that is missing. The page count is shown only when it was stored
	 * for the PDF (see Flipset_SEO::META_PAGES); it is never worked out here.
	 *
	 * @param int $id PDF attachment ID.
	 * @return string
	 */
	private function item_meta( $id ) {
		$parts = array( __( 'PDF', 'flipset' ) );

		$pages = $this->page_count_label( absint( get_post_meta( $id, Flipset_SEO::META_PAGES, true ) ) );
		if ( '' !== $pages ) {
			$parts[] = $pages;
		}

		$bytes = 0;
		$data  = wp_get_attachment_metadata( $id );
		if ( is_array( $data ) && ! empty( $data['filesize'] ) ) {
			$bytes = absint( $data['filesize'] );
		}
		if ( ! $bytes ) {
			$path = get_attached_file( $id );
			if ( $path && file_exists( $path ) ) {
				$bytes = absint( filesize( $path ) );
			}
		}
		if ( $bytes ) {
			$parts[] = size_format( $bytes, 1 );
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Controls row: the search/sort form, plus the grid/list switch when it is turned on.
	 * Without the switch this is just the form, so the markup of a library that uses
	 * none of the extra controls is the same as before they existed.
	 *
	 * @param bool       $search_on  Whether the search box is turned on.
	 * @param string     $search     Current search term.
	 * @param string     $search_var Query var holding the search term.
	 * @param string     $query_var  Query var holding the page number.
	 * @param array|null $sort       Sort menu data (var, selected, options), or null when it is off.
	 * @param bool       $toggle_on  Whether the grid/list switch is turned on.
	 * @param string     $layout     Current layout, grid or list.
	 * @return string HTML.
	 */
	private function render_controls( $search_on, $search, $search_var, $query_var, $sort, $toggle_on, $layout ) {
		$form = $this->render_search_form( $search_on, $search, $search_var, $query_var, $sort );
		if ( ! $toggle_on ) {
			return $form;
		}
		return '<div class="flipset-library-controls">' . $form . $this->render_layout_toggle( $layout ) . '</div>';
	}

	/**
	 * Search form (and sort menu) that keeps the other query args as hidden fields.
	 *
	 * @param bool       $enabled    Whether the search box is turned on.
	 * @param string     $search     Current search term.
	 * @param string     $search_var Query var holding the search term.
	 * @param string     $query_var  Query var holding the page number.
	 * @param array|null $sort       Sort menu data (var, selected, options), or null when it is off.
	 * @return string HTML, or an empty string when both search and sort are off.
	 */
	private function render_search_form( $enabled, $search, $search_var, $query_var, $sort = null ) {
		if ( ! $enabled && null === $sort ) {
			return '';
		}

		// Existing query args are carried over as hidden fields. Read each one
		// through sanitize_key() / sanitize_text_field() into $args first and print
		// only from that sanitised copy.
		$args = array();
		if ( isset( $_GET ) && is_array( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- preserve public query args.
			foreach ( array_keys( $_GET ) as $raw_key ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- preserve public query args.
				$key = sanitize_key( (string) $raw_key );
				if ( '' === $key || $key === $search_var || $key === $query_var || ( null !== $sort && $key === $sort['var'] ) ) {
					continue;
				}
				if ( ! isset( $_GET[ $raw_key ] ) || ! is_scalar( $_GET[ $raw_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- preserve public query args.
					continue;
				}
				$args[ $key ] = sanitize_text_field( wp_unslash( (string) $_GET[ $raw_key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- preserve public query args.
			}
		}

		$classes = $enabled ? 'flipset-library-search' : 'flipset-library-sort';
		if ( $enabled && null !== $sort ) {
			$classes .= ' has-sort';
		}

		ob_start();
		?>
		<form class="<?php echo esc_attr( $classes ); ?>" method="get"<?php echo $enabled ? ' role="search"' : ''; ?>>
			<?php foreach ( $args as $arg_key => $arg_value ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $arg_key ); ?>" value="<?php echo esc_attr( $arg_value ); ?>" />
			<?php endforeach; ?>
			<?php if ( $enabled ) : ?>
				<label class="screen-reader-text" for="<?php echo esc_attr( $search_var ); ?>"><?php esc_html_e( 'Search documents', 'flipset' ); ?></label>
				<input
					type="search"
					id="<?php echo esc_attr( $search_var ); ?>"
					name="<?php echo esc_attr( $search_var ); ?>"
					value="<?php echo esc_attr( $search ); ?>"
					placeholder="<?php esc_attr_e( 'Search documents…', 'flipset' ); ?>"
				/>
				<button type="submit" class="flipset-library-search-submit"><?php esc_html_e( 'Search', 'flipset' ); ?></button>
			<?php endif; ?>
			<?php if ( null !== $sort ) : ?>
				<span class="flipset-library-sort-field">
					<label class="screen-reader-text" for="<?php echo esc_attr( $sort['var'] ); ?>"><?php esc_html_e( 'Sort documents', 'flipset' ); ?></label>
					<select id="<?php echo esc_attr( $sort['var'] ); ?>" name="<?php echo esc_attr( $sort['var'] ); ?>" class="flipset-library-sort-select">
						<?php foreach ( $sort['options'] as $value => $label ) : ?>
							<option value="<?php echo esc_attr( (string) $value ); ?>"<?php echo ( (string) $value === (string) $sort['selected'] ) ? ' selected="selected"' : ''; ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</span>
			<?php endif; ?>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * The grid/list switch: two real buttons, state in aria-pressed. The choice is kept in the
	 * visitor's browser by flipset-library.js; nothing is stored on the server.
	 *
	 * @param string $layout Layout the page starts with, grid or list.
	 * @return string HTML.
	 */
	private function render_layout_toggle( $layout ) {
		$buttons = array(
			'grid' => array(
				'label' => __( 'Grid view', 'flipset' ),
				'icon'  => '<rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" />',
			),
			'list' => array(
				'label' => __( 'List view', 'flipset' ),
				'icon'  => '<line x1="9" y1="6" x2="21" y2="6" /><line x1="9" y1="12" x2="21" y2="12" /><line x1="9" y1="18" x2="21" y2="18" /><line x1="3" y1="6" x2="4" y2="6" /><line x1="3" y1="12" x2="4" y2="12" /><line x1="3" y1="18" x2="4" y2="18" />',
			),
		);

		$html = '<div class="flipset-library-layout" role="group" aria-label="' . esc_attr__( 'Layout', 'flipset' ) . '">';
		foreach ( $buttons as $key => $button ) {
			$html .= '<button type="button" class="flipset-library-layout-btn" data-layout="' . esc_attr( $key ) . '" aria-pressed="' . ( $key === $layout ? 'true' : 'false' ) . '" aria-label="' . esc_attr( $button['label'] ) . '" title="' . esc_attr( $button['label'] ) . '">'
				. '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $button['icon'] . '</svg>'
				. '</button>';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Screen-reader message for the live region that flipset-library.js updates after an in-place
	 * sort, search or page change. The message sits in data-status so nothing is read on page load.
	 *
	 * @param int    $found    Number of documents matching the query.
	 * @param string $sort_key Selected sort key, or an empty string for the default order.
	 * @return string HTML.
	 */
	private function render_status( $found, $sort_key ) {
		/* translators: %s: number of documents. */
		$message = sprintf( _n( '%s document found.', '%s documents found.', $found, 'flipset' ), number_format_i18n( $found ) );

		$choices = $this->sort_choices();
		if ( isset( $choices[ $sort_key ] ) ) {
			/* translators: %s: name of the selected sort order, for example "Newest". */
			$message .= ' ' . sprintf( __( 'Sorted by %s.', 'flipset' ), $choices[ $sort_key ]['label'] );
		}

		return '<p class="flipset-library-status screen-reader-text" role="status" aria-live="polite" data-status="' . esc_attr( $message ) . '"></p>';
	}

	/**
	 * Pagination links for the library.
	 *
	 * @param WP_Query $query      The library query.
	 * @param int      $paged      Current page number.
	 * @param string   $query_var  Query var holding the page number.
	 * @param string   $search_var Query var holding the search term.
	 * @param string   $search     Current search term.
	 * @return string HTML, or an empty string when there is a single page.
	 */
	private function render_pagination( $query, $paged, $query_var, $search_var = '', $search = '' ) {
		if ( (int) $query->max_num_pages < 2 ) {
			return '';
		}

		$add_args = array();
		if ( '' !== $search_var && '' !== $search ) {
			$add_args[ $search_var ] = $search;
		}

		$links = paginate_links(
			array(
				'base'      => add_query_arg( $query_var, '%#%' ),
				'format'    => '',
				'total'     => (int) $query->max_num_pages,
				'current'   => $paged,
				'type'      => 'list',
				'add_args'  => $add_args,
				'prev_text' => __( 'Previous', 'flipset' ),
				'next_text' => __( 'Next', 'flipset' ),
			)
		);

		if ( ! $links ) {
			return '';
		}

		return '<nav class="flipset-library-pager" aria-label="' . esc_attr__( 'Document library pages', 'flipset' ) . '">' . wp_kses_post( $links ) . '</nav>';
	}

	/**
	 * Empty-state message, with an upload link for users who can upload files.
	 *
	 * @param string $search Current search term; changes the message when set.
	 * @return string HTML.
	 */
	private function render_empty( $search = '' ) {
		$message = '' !== $search
			? __( 'No documents matched that search.', 'flipset' )
			: __( 'No documents found.', 'flipset' );
		$html    = '<div class="flipset-library-empty" role="status"><p>' . esc_html( $message ) . '</p>';
		if ( current_user_can( 'upload_files' ) ) {
			$html .= '<p><a href="' . esc_url( admin_url( 'media-new.php' ) ) . '">' . esc_html__( 'Upload a PDF', 'flipset' ) . '</a></p>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Enqueue the viewer, library stylesheet and library script once per request.
	 */
	private function enqueue_assets() {
		if ( $this->assets_enqueued ) {
			return;
		}
		$this->assets_enqueued = true;

		Flipset::instance()->viewer->enqueue_assets();

		wp_enqueue_style(
			'flipset-library',
			FLIPSET_URL . 'assets/css/flipset-library.css',
			array( 'flipset-viewer' ),
			FLIPSET_VERSION
		);

		wp_enqueue_script(
			'flipset-library',
			FLIPSET_URL . 'assets/js/flipset-library.js',
			array( 'flipset-viewer' ),
			FLIPSET_VERSION,
			true
		);
	}

	/**
	 * SVG document icon used when a PDF has no thumbnail.
	 *
	 * @return string SVG markup.
	 */
	private function placeholder_icon() {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line x1="8" y1="13" x2="16" y2="13" /><line x1="8" y1="17" x2="12" y2="17" /></svg>';
	}


	/**
	 * Cast a value to a positive integer, or use the fallback when it is out of range.
	 *
	 * @param mixed $value    Raw value.
	 * @param int   $min      Smallest allowed value.
	 * @param int   $max      Largest allowed value.
	 * @param int   $fallback Value to use when out of range.
	 * @return int
	 */
	private function clamp_int( $value, $min, $max, $fallback ) {
		$n = absint( $value );
		if ( $n < $min || $n > $max ) {
			return $fallback;
		}
		return $n;
	}

	/**
	 * Limit orderby to the supported values.
	 *
	 * @param string $value Raw orderby value.
	 * @return string One of date, title or modified.
	 */
	private function sanitize_orderby( $value ) {
		$value   = sanitize_key( $value );
		$allowed = array( 'date', 'title', 'modified' );
		return in_array( $value, $allowed, true ) ? $value : 'date';
	}

	/**
	 * Limit the layout attribute to the supported values.
	 *
	 * @param mixed $value Raw layout value.
	 * @return string grid or list; anything else falls back to grid.
	 */
	private function sanitize_layout( $value ) {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
		return 'list' === $value ? 'list' : 'grid';
	}

	/**
	 * Singular or plural page count for the card meta line.
	 *
	 * @param int $pages Stored number of pages.
	 * @return string "1 page" / "12 pages", or an empty string when there is no page count.
	 */
	private function page_count_label( $pages ) {
		$pages = absint( $pages );
		if ( $pages < 1 ) {
			return '';
		}
		/* translators: %s: number of pages in the PDF. */
		return sprintf( _n( '%s page', '%s pages', $pages, 'flipset' ), number_format_i18n( $pages ) );
	}

	/**
	 * The choices of the sort menu. The key is what goes in the URL; orderby and order are
	 * the same values the shortcode attributes accept.
	 *
	 * @return array<string,array{label:string,orderby:string,order:string}>
	 */
	private function sort_choices() {
		return array(
			'newest'     => array(
				'label'   => __( 'Newest', 'flipset' ),
				'orderby' => 'date',
				'order'   => 'DESC',
			),
			'oldest'     => array(
				'label'   => __( 'Oldest', 'flipset' ),
				'orderby' => 'date',
				'order'   => 'ASC',
			),
			'title-asc'  => array(
				'label'   => __( 'Title A–Z', 'flipset' ),
				'orderby' => 'title',
				'order'   => 'ASC',
			),
			'title-desc' => array(
				'label'   => __( 'Title Z–A', 'flipset' ),
				'orderby' => 'title',
				'order'   => 'DESC',
			),
		);
	}

	/**
	 * Option labels for the sort menu, keyed by value. When the shortcode's own orderby/order
	 * is not one of the four choices (for example orderby="modified"), a first "Default order"
	 * option stands for it so the menu never shows a choice the page is not using.
	 *
	 * @param string $selected Selected sort key, or an empty string for the shortcode's own order.
	 * @return array<string,string>
	 */
	private function sort_options( $selected ) {
		$options = array();
		if ( '' === $selected ) {
			$options[''] = __( 'Default order', 'flipset' );
		}
		foreach ( $this->sort_choices() as $key => $choice ) {
			$options[ $key ] = $choice['label'];
		}
		return $options;
	}

	/**
	 * Work out which orderby/order to query with, and which menu entry is selected.
	 *
	 * @param string $orderby   Sanitized orderby from the shortcode attributes.
	 * @param string $order     Sanitized order from the shortcode attributes (ASC or DESC).
	 * @param string $requested Sort key from the URL; unknown keys are ignored.
	 * @return array{0:string,1:string,2:string} orderby, order and the selected key ('' when the
	 *                                           shortcode's own order matches none of the choices).
	 */
	private function resolve_sort( $orderby, $order, $requested ) {
		$choices = $this->sort_choices();
		if ( isset( $choices[ $requested ] ) ) {
			return array( $choices[ $requested ]['orderby'], $choices[ $requested ]['order'], $requested );
		}
		foreach ( $choices as $key => $choice ) {
			if ( $choice['orderby'] === $orderby && $choice['order'] === $order ) {
				return array( $orderby, $order, $key );
			}
		}
		return array( $orderby, $order, '' );
	}
}
