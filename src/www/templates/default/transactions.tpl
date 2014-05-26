	@foreach ($accounts as $account) {
		<div id="transactions_{{$account->getAccountKey()}}">
		<h1>{{$account->getFullNumber()}}</h1>

		<div class="table-responsive">
		<table class="table table-striped table-bordered table-hover table-condensed transactions sortable">
		<thead>
		<tr>
		<th class="date">Date</th>
		<th class="typecode">Type</th>
		<th class="description">Description</th>
		<th class="amount">Amount</th>
		<th class="balance">Balance</th>
		{-- <th class="hash">Hash</th> --}
		<th class="transactiontags">Tags</th>
		</tr>
		</thead>

		<tbody>
		@$lastBalance = null;
		@$count = 0;
		@foreach ($account->getTransactions() as $transaction) {
			@if ($transaction->getTime() < $start) { continue; }
			@if ($transaction->getTime() > $end) { continue; }
			@$count++

			<tr>
			<td class="date" data-value="{{$transaction->getTime()}}" data-nice="{{date("l d F Y", $transaction->getTime())}}">{{date("Y-m-d H:i:s", $transaction->getTime())}}</td>
			<td class="typecode"><span data-toggle="tooltip" title="{{$transaction->getType()}}">{{$transaction->getTypeCode()}}</span></td>
			<td class="description"><span data-toggle="tooltip" title="{{$transaction->getHash()}}">{{$transaction->getDescription()}}</span></td>
			<td class="amount">{{money_format('%.2n', $transaction->getAmount())}}</td>
			<td class="balance">{{money_format('%.2n', $transaction->getBalance())}}</td>
			{-- <td class="hash">{{$transaction->getHash()}}</td> --}
			<td class="transactiontags" data-tags="{{htmlspecialchars(json_encode($transaction->getTags()))}}" data-id="{{$transaction->getHash()}}" id="tags-{{$transaction->getHash()}}">
			<div class="tagtext">
				{{getTagHTML($transaction, $tags)}}
			</div>
			</td>
			</tr>

			@if ($lastBalance !== null && !$filtered) {
				@$newBalance = $lastBalance + $transaction->getAmount();
				@if (money_format('%.2n', $transaction->getBalance()) != money_format('%.2n', $newBalance)) {
					<tr class="error">
					<td colspan=5>
					<strong><em>
					Unexpected balance... Expected: {{$newBalance}}
					</em></strong>
					</td>
					</tr>
				@}
			@}

			@$lastBalance = $transaction->getBalance();
		@}
		@if ($count == 0) {
			<tr>
				<td class="error" colspan="6">
					There are no transactions to display.
					@if ($hideEmpty) {
					<script>$('#transactions_{{$account->getAccountKey()}}').hide();</script>
					@}
				</td>
			</tr>
		@}
		</tbody>

		</table>
		</div>
		</div>
	@}

<!-- Modal -->
<div class="modal fade" id="addTagModal" tabindex="-1" role="dialog" aria-labelledby="addTagModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
				<h4 class="modal-title" id="addTagModalLabel">Tag Transaction</h4>
			</div>

			<div class="modal-body">
				<form id="addTagForm" class="form-horizontal">
					<fieldset>

						<!-- Text input-->
						<div class="form-group">
							<label class="col-md-4 control-label" for="transaction">Transaction ID</label>
							<div class="col-md-8">
								<input name="transaction" type="text" placeholder="" class="form-control input-md" disabled>
							</div>
						</div>

						<!-- Text input-->
						<div class="form-group">
							<label class="col-md-4 control-label" for="description">Description</label>
							<div class="col-md-8">
								<input name="description" type="text" placeholder="" class="form-control input-md" disabled>
							</div>
						</div>

						<!-- Text input-->
						<div class="form-group">
							<label class="col-md-4 control-label" for="date">Date</label>
							<div class="col-md-8">
								<input name="date" type="text" placeholder="" class="form-control input-md" disabled>
							</div>
						</div>

						<!-- Select Basic -->
						<div class="form-group">
							<label class="col-md-4 control-label" for="tag">Tag</label>
							<div class="col-md-8">
								<select name="tag" class="form-control">
									<option value="-1" selected disabled>Please Select...</option>
									@ foreach ($jsontags as $group => $grouptags) {
									<optgroup label="{{$group}}">
										@ foreach ($grouptags as $tag => $id) {
										<option value="{{$id}}">{{$tag}}</option>
										@ }
									</optgroup>
									@ }
								</select>
							</div>
						</div>

						<!-- Text input-->
						<div class="form-group">
							<label class="col-md-4 control-label" for="value">Value</label>
							<div class="col-md-5">
								<div class="input-group">
									<span class="input-group-addon">&pound;</span>
									<input name="value" type="text" placeholder="" class="form-control input-md">
								</div>
							</div>
						</div>

					</fieldset>
				</form>
			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="saveTag" type="button" class="btn btn-primary">Save changes</button>
			</div>
		</div>
	</div>
</div>

<script>
	$(function() {
		$('td span').tooltip();
		$.bootstrapSortable(true, 'reversed');
	});

	function addTag(clickedTag) {
		transid = $(clickedTag).parent().parent().attr('data-id');
		date = $('td.date', $(clickedTag).closest('tr')).attr('data-nice');
		description = $('td.description span', $(clickedTag).closest('tr')).text();
		remaining = parseFloat($(clickedTag).attr('data-remaining')).toFixed(2);

		$('#addTagForm input[name="transaction"]').val(transid);
		$('#addTagForm input[name="description"]').val(description);
		$('#addTagForm input[name="date"]').val(date);
		$('#addTagForm input[name="value"]').val(remaining);

		$('#addTagModal').modal();
	}

	function clickTag(clickedTag) {
		transid = $(clickedTag).parent().parent().attr('data-id');
		tagid = $(clickedTag).attr('data-tagid');

		$.ajax({
			url: '{[getWebLocation]}deletetag',
			type: 'POST',
			data: {transaction: transid, tagid: tagid},
		}).done(function(data) {
		  	$(clickedTag).parent().html(data);
		});
	}

	$('td.transactiontags div.tagtext').on('click', 'span.transactionTag', function() {
		clickTag(this)
	});

	$('td.transactiontags div.tagtext').on('click', 'span.untaggedTag', function() {
		addTag(this);
	});

	$('#addTagModal').keydown(function(e) {
		if (e.which == '13') {
			$('#saveTag').click();
		}
	});

	$('#saveTag').click(function() {
		transid = $('#addTagForm input[name="transaction"]').val();
		tagid = $('#addTagForm select[name="tag"]').val();
		value = $('#addTagForm input[name="value"]').val();

		parentElement = document.getElementById('tags-' + transid);

		$('#addTagModal').modal('hide');
		$('div.tagtext', parentElement).html('');

		$.ajax({
			url: '{[getWebLocation]}addtag',
			type: 'POST',
			data: {transaction: transid, tagid: tagid, value: value},
		}).done(function(data) {
			$('div.tagtext', parentElement).html(data);
		});
	});
</script>
