<tr>
	<th id="delete_rates_table">
		<h4>Whoops! WooTax found some extra rates in your tax tables.</h4>
		<p>If you do not take action and delete these rates, your customers may be overtaxed. Please select all rates which you would like to remove and click "Delete rates in selected tax classes." Then, click "Save Changes" to continue.</p>

		<input type="hidden" name="wt_rates_checked" value="1" />
		
		<?php
			global $wpdb;

			// Get readable tax classes
			$readable_classes = array( 'Standard Rate' );
			$raw_classes = get_option( 'woocommerce_tax_classes' );
			
			if ( !empty( $raw_classes ) && $raw_classes ) {
				$readable_classes = array_map( 'trim', array_merge( $readable_classes, explode( PHP_EOL, $raw_classes ) ) );
			}

			// Get the count for each tax class
			$rate_counts = array_keys( $readable_classes );

			foreach ( $rate_counts as $key ) {
				$array_key = sanitize_title( $readable_classes[$key] );

				$count = $wpdb->get_var( $wpdb->prepare("
					SELECT COUNT(tax_rate_id) FROM
						{$wpdb->prefix}woocommerce_tax_rates 
					WHERE 
						tax_rate_class = %s
					",
					( $array_key == 'standard-rate' ? '' : $array_key )
				) );

				if ( $count != false && !empty( $count ) ) {
					$rate_counts[ $array_key ] = array( 'name' => $readable_classes[ $key ], 'count' => $count );
				} 

				unset( $rate_counts[ $key ] );
			}

			require WT_PLUGIN_PATH .'/templates/admin/rate-table-header.php';

			foreach ( $rate_counts as $rate => $data ) {
				$GLOBALS['rate'] = $rate;
				$GLOBALS['data'] = $data;

				require WT_PLUGIN_PATH .'/templates/admin/rate-table-row.php';
			}
		
			echo '</tbody>';
			echo '</table>';
		?>
	</th>
</tr>