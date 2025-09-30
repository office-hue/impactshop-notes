<?php
/**
 * Items setting
 *
 * @package HelloPack_Client
 * @since 2.0.0
 */

$items = hellopack_client()->get_option( 'items', array() );

?>
<ul id="hellopack-client-items">
<?php
if ( ! empty( $items ) ) {
	foreach ( $items as $key => $item ) {
		if ( empty( $item['name'] ) || empty( $item['token'] ) || empty( $item['id'] ) || empty( $item['type'] ) || empty( $item['authorized'] ) ) {
			continue;
		}
		$class = 'success' === $item['authorized'] ? 'is-authorized' : 'not-authorized';
		echo '
		<li data-id="' . esc_attr( $item['id'] ) . '" class="' . esc_attr( $class ) . '">
			<span class="item-name">' . esc_html__( 'ID', 'hellopack-client' ) . ': ' . esc_html( $item['id'] ) . ' - ' . esc_html( $item['name'] ) . '</span>
			<button class="item-delete dashicons dashicons-dismiss">
				<span class="screen-reader-text">' . esc_html__( 'Delete', 'hellopack-client' ) . '</span>
			</button>
			<input type="hidden" name="' . esc_attr( hellopack_client()->get_option_name() ) . '[items][' . esc_attr( $key ) . '][name]" value="' . esc_html( $item['name'] ) . '" />
			<input type="hidden" name="' . esc_attr( hellopack_client()->get_option_name() ) . '[items][' . esc_attr( $key ) . '][token]" value="' . esc_html( $item['token'] ) . '" />
			<input type="hidden" name="' . esc_attr( hellopack_client()->get_option_name() ) . '[items][' . esc_attr( $key ) . '][id]" value="' . esc_html( $item['id'] ) . '" />
			<input type="hidden" name="' . esc_attr( hellopack_client()->get_option_name() ) . '[items][' . esc_attr( $key ) . '][type]" value="' . esc_html( $item['type'] ) . '" />
			<input type="hidden" name="' . esc_attr( hellopack_client()->get_option_name() ) . '[items][' . esc_attr( $key ) . '][authorized]" value="' . esc_html( $item['authorized'] ) . '" />
		</li>';
	}
}
?>
</ul>

<button class="button add-hellopack-client-item"><?php esc_html_e( 'Add Item', 'hellopack-client' ); ?></button>
