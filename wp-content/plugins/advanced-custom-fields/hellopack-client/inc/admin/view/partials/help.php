<?php
/**
 * Help panel partial
 *
 * @package HelloPack_Client
 * @since 2.0.1
 */

?>
<div id="help" class="panel">

	<div
			class="hellopack-builder-important-notice hellopack-template-builder hellopack-db-card hellopack-db-card-first">
			<div class="intro-text">
				<h1 class="hellopack-panel-title">
					<svg class="hellopack-layer-icon">
						<use
								xlink:href="<?php echo HELLOPACK_CLIENT_URI . 'images/sprite.svg'; ?>?v=<?php echo HELLOPACK_CLIENT_VERSION; ?>#hellopack-layer-icon">
						</use>
					</svg>
					<?php esc_html_e( 'Help', 'hellopack-client' ); ?>

				</h1>

				<p><?php esc_html_e( 'Encountered problems? Here you can find the tester and here you can get additional information on how to solve common issues.', 'hellopack-client' ); ?>
				</p>
			</div>
	</div>


	<div class="hellopack-client-columns">


			<?php
			if ( ! defined( 'HELLOPACK_WHITELABEL' ) ) {
				?>
			<div class="hellopack-client-column">
				<h3><svg style="fill:red" class="hellopack-bug">
						<use
								xlink:href="<?php echo HELLOPACK_CLIENT_URI . 'images/sprite.svg'; ?>?v=<?php echo HELLOPACK_CLIENT_VERSION; ?>#hellopack-bug">
						</use>
					</svg> <?php _e( 'Troubleshooting:', 'hellopack-client' ); ?></h3>
				<p> <?php _e( 'We have compiled a list of issues that typically cause problems when using HelloPack. Please review the', 'hellopack-client' ); ?>
					<a target="_blank" href="https://hub.hellowp.io/docs/dokumentacio/hellopack/hibaelharitas">
						<?php _e( 'Troubleshooting', 'hellopack-client' ); ?> </a>
					<?php _e( 'guide.', 'hellopack-client' ); ?>
				</p>
			</div>

				<?php
			}
			?>

			<div class="hellopack-client-column">
				<h3><svg class="hellopack-network-wired-solid">
						<use
								xlink:href="<?php echo HELLOPACK_CLIENT_URI . 'images/sprite.svg'; ?>?v=<?php echo HELLOPACK_CLIENT_VERSION; ?>#hellopack-network-wired-solid">
						</use>
					</svg>
					<?php _e( 'Testing Server and Communication:', 'hellopack-client' ); ?>
				</h3>
				<p><?php _e( 'Checking the required storage settings and the communication between servers.', 'hellopack-client' ); ?>
				</p>
				<div class="hellopack-client-healthcheck">
					<?php _e( 'Problem starting healthcheck. Please check javascript console for errors.', 'hellopack-client' ); ?>
				</div>
			</div>



			<div class="hellopack-client-column">
				<h3><svg class="hellopack-copy-solid">
						<use
								xlink:href="<?php echo HELLOPACK_CLIENT_URI . 'images/sprite.svg'; ?>?v=<?php echo HELLOPACK_CLIENT_VERSION; ?>#hellopack-copy-solid">
						</use>
					</svg>


					<?php _e( 'Copy and Paste:', 'hellopack-client' ); ?>
				</h3>
				<p><?php _e( 'You can easily copy this text. Just click into it and press the Ctrl+C and Ctrl+V keys | ⌘+C and ⌘+V keys:', 'hellopack-client' ); ?>
				</p>

				<div>
					<textarea id="hellopack-system-info-raw-code" readonly="">Loading...</textarea>
					<script>
					var targetNode = document.querySelector('.hellopack-client-healthcheck');

					var config = {
						childList: true,
						subtree: true,
						characterData: true
					};

					var callback = function(mutationsList, observer) {
						var isLoadingTextPresent = targetNode.textContent.includes("Loading...");
						if (!isLoadingTextPresent) {
								var listItems = targetNode.querySelectorAll('li');
								var contentArray = [
									"=== Testing Server and Communication ==="
								];

								listItems.forEach(function(item) {
									contentArray.push(item.textContent.trim());
								});
								var textarea = document.getElementById('hellopack-system-info-raw-code');
								textarea.value = contentArray.join('\n\n');
								observer.disconnect();
						}
					};

					var observer = new MutationObserver(callback);

					observer.observe(targetNode, config);

					var textarea = document.getElementById("hellopack-system-info-raw-code");
					var selectRange = function() {
						textarea.setSelectionRange(0, textarea.value.length);
					};
					textarea.onfocus = textarea.onblur = textarea.onclick = selectRange;
					textarea.onfocus();
					</script>

				</div>
			</div>
	</div>
