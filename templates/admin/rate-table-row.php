<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $rate, $data; ?>

<tr class="flat_rate">
	<th scope="row" class="check-column"><input type="checkbox" data-val="<?php echo $rate; ?>" name="select"></th>
	<td><?php echo $data['name']; ?></td>
	<td><?php echo $data['count']; ?></td>
</tr>