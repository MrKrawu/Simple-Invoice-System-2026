<?php
	require_once 'bootstrap.php';
	require_api();
?>

	<table class="table table-bordered table-hover">
			<thead>
				<tr class="font-weight-bold">
					<td>No.</td>
					<td>Item</td>
					<td>Qty</td>
					<td>Price</td>
					<td>Tax</td>
					<td>Subtotal</td>
					<td>Total</td>
					<td>Delete</td>
				</tr>
			</thead>
		<?php
		$total = 0;
		$tax_amount = 0;
		$sub_total = 0;
		$i = 0;

		if(isset($_SESSION['item_table_temp'])):foreach (@$_SESSION['item_table_temp'] as $key) {

			$row=explode("||", @$key);

		?>

			<tr>
				<td><?php echo $i+1; ?></td>
				<td><?php echo h($row[0] ?? '') ?></td>
				<td><?php echo h($row[1] ?? '') ?></td>
				<td><?php echo h(money($row[2] ?? 0)) ?></td>
				<td><?php echo h(money($row[3] ?? 0)) ?></td>
				<td><?php echo h(money($row[4] ?? 0)) ?></td>
				<td><?php echo h(money($row[5] ?? 0)) ?></td>
				<td width="5%">
					<span class="btn btn-danger btn-xs" onclick="eliminateItem('<?php echo $i; ?>');">
						<i class="fas fa-times"></i>
					</span>
				</td>
			</tr>

			<?php
			$i++;


		}
		endif;
		
		[$sub_total, $tax_amount, $total] = item_totals($_SESSION['item_table_temp'] ?? []);
		?>
	</table>

	<hr class="colorgraph">
	<div class="row">
		<div class="col-sm-12 col-lg-7">
		</div>
		<div class="col-sm-12 col-lg-5">
			<div class="card text-muted bg-light">
				<div class="card-body">
					<table class="table table-bordered table-hover">
						<tbody>
							<tr>
								<th class="text-right">Sub Total
								</th>
								<td class="text-center">
									<?php echo "$".money($sub_total); ?>
								</td>
							</tr>
							<tr>
								<th class="text-right">Tax Total
								</th>
								<td class="text-center">
									<?php echo "$".money($tax_amount); ?>
								</td>
							</tr>
							<tr>
								<th class="text-right">Grand Total
								</th>
								<td class="text-center">
									<?php echo "$".money($total); ?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>