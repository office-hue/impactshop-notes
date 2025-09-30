<div id="search" class="hellopack-plugin-search-block">
	<div class="hellopack-client-plugin-search">

			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="hellopack-client">
				<input type="hidden" name="tab" value="plugins">
				<input name="plugins-search" type="text"
					placeholder="<?php echo esc_attr__( 'Search in HelloPack repository...', 'hellopack-client' ); ?>"
					value="<?php echo isset( $_GET['plugins-search'] ) ? esc_attr( $_GET['plugins-search'] ) : ''; ?>">
				<input type="submit" value="<?php echo esc_attr__( 'Search', 'hellopack-client' ); ?>">
			</form>


	</div>
</div>
