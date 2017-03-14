<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>

<table id="delete-rates" class="wp-list-table widefat striped posts" cellspacing="0">
	<thead>
		<tr>
			<td class="check-column column-cb"><input type="checkbox"></td>
			<th class="shipping_class">Tax Rate Class</th>
			<th>Number of Rates <a class="tips"></a></th>
		</tr>
	</thead>
	<tfoot>
		<tr>
			<th colspan="4"><a href="#" id="remove-rates" class="remove button">Delete rates in selected tax classes</a></th>
		</tr>
	</tfoot>
	<tbody class="flat_rates">