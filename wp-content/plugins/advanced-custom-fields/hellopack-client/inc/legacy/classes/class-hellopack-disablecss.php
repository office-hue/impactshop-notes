<?php
/**
 * Class HelloPackCSSDisable
 *
 * This class disables CSS for HelloPack.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HelloPackCSSDisable class
 *
 * This class disables CSS for HelloPack.
 */
class HelloPackCSSDisable {

	/**
	 * Selectors to hide.
	 *
	 * @var array
	 */
	private $selectors_to_hide = array();

	/**
	 * Tracks whether the styles have been added to the page already.
	 *
	 * @var bool
	 */
	private $styles_added = false;

	/**
	 * Add a class or ID to the list of selectors to hide.
	 *
	 * @param string $selector CSS class or ID to hide.
	 */
	public function add_selector( $selector ) {
		if ( ! in_array( $selector, $this->selectors_to_hide, true ) ) {
			$this->selectors_to_hide[] = $selector;
		}
	}

	/**
	 * Hide notices by printing CSS.
	 */
	public function hide_notices() {
		if ( $this->styles_added ) {
			// Styles have already been added, so we don't need to add them again.
			return;
		}

		add_action(
			'admin_head',
			function () {
				if ( ! empty( $this->selectors_to_hide ) ) {
					$selectors = implode( ', ', $this->selectors_to_hide );
					echo '<style>' . esc_html( $selectors ) . ' { display: none !important; }</style>';
				}
			}
		);

		// Set the flag to true so the styles aren't added again.
		$this->styles_added = true;
	}
}
