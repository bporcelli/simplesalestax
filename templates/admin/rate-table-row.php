<?php global $rate, $data; ?>

<tr class="flat_rate">
	<th class="check-column"><input type="checkbox" data-val="<?php echo $rate; ?>" name="select"></th>
	<td><?php echo $data['name']; ?></td>
	<td><?php echo $data['count']; ?></td>
</tr>