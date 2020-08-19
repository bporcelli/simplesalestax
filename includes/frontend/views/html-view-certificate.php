<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<script type="text/html" id="tmpl-sst-modal-view-certificate">
	<div class="wc-backbone-modal">
		<div class="wc-backbone-modal-content sst-certificate-modal-content sst-view-certificate-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1><?php esc_html_e( 'View certificate', 'simple-sales-tax' ); ?></h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text">
							<?php esc_html_e( 'Close modal panel', 'simple-sales-tax' ); ?>
						</span>
					</button>
				</header>
				<article>
					<div class="sst-certificate" style="background-image: url({{ data.backgroundImage }});">
						<span id="PurchaserName">{{ data.PurchaserName }}</span>
						<span id="PurchaserAddress">{{ data.PurchaserAddress }}</span>
						<span id="PurchaserState">{{ data.PurchaserState }}</span>
						<span id="PurchaserExemptionReason">{{ data.PurchaserExemptionReason }}</span>
						<span id="OrderID">{{ data.SinglePurchaseOrderNumber }}</span>
						<span id="Date">{{ data.CreatedDate }}</span>
						<span id="TaxType">{{ data.TaxType }}</span>
						<span id="IDNumber">{{ data.IDNumber }}</span>
						<span id="PurchaserBusinessType">{{ data.PurchaserBusinessType }}</span>
						<span id="MerchantName">{{ SSTCertData.seller_name }}</span>
					</div>
				</article>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</script>
