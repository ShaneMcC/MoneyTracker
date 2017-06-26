	@$totalcount = 0;
	@foreach ($accounts as $account) {
		<div id="transactions_{{$account->getAccountKey()}}">
		<h1>{{$account->getFullNumber()}} <small>{{$account->getDescriptionOrType()}} <a id="chartlink_chart_balance" style="display: none" href="#containerchart_balance"><span class="glyphicon glyphicon-stats"/></small></a></h1>
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
			{-- TODO: This needs to be handled by the class not the template. --}
			@if ($transaction->getTime() < $start) { continue; }
			@if ($transaction->getTime() > $end) { continue; }
			@if (!isStringMatch($transaction->getDescription(), $searchstring)) { continue; }
			@if ($onlyUntagged && count($transaction->getTags()) > 0) { continue; }

			@$count++

			@$balanceError = false;
			@if ($lastBalance !== null && !$filtered && empty($searchstring)) {
				@$newBalance = $lastBalance + $transaction->getAmount();
				@$balanceError = (money_format('%.2n', $transaction->getBalance()) != money_format('%.2n', $newBalance));
			@}

			<tr class="{{$balanceError ? 'error' : ''}}" data-id="{{$transaction->getHash()}}">
			@$transNumber = 1 + ($transaction->getTime() - strtotime(date("Y-m-d", $transaction->getTime())));
				<td class="date" data-value="{{$transaction->getTime()}}" data-nice="{{date("l d F Y H:i:s", $transaction->getTime())}}">
					<span data-toggle="tooltip" data-html="true" title="Day: {{date("l", $transaction->getTime())}}<br>Transaction Number: {{$transNumber}}">
					{{date("Y-m-d", $transaction->getTime())}}
					</span>
				</td>
				<td class="typecode"><span data-toggle="tooltip" title="{{$transaction->getType()}}">{{$transaction->getTypeCode()}}</span></td>
				<td class="description">
					<a class="infosign"><span class="glyphicon glyphicon-info-sign"></span></a>
					<a class="searchicon" data-searchtext="{{$transaction->getDescription()}}" href="{[getNewPageLink('', array('searchstring' => $transaction->getDescription()))]}"><span class="glyphicon glyphicon-search"></span></a>
					<span data-toggle="tooltip" title="{{$transaction->getHash()}}">{{$transaction->getDescription()}}</span>
				</td>
				<td class="amount">{{money_format('%.2n', $transaction->getAmount())}}</td>
				<td class="balance">{{money_format('%.2n', $transaction->getBalance())}}</td>
				{-- <td class="hash">{{$transaction->getHash()}}</td> --}
				<td class="transactiontags" data-tags="{{htmlspecialchars(json_encode($transaction->getTags()))}}" data-id="{{$transaction->getHash()}}" id="tags-{{$transaction->getHash()}}">
				<div class="tagtext">
					{{getTagHTML($transaction, $tags)}}
					@ if (!empty($transaction->getExtra())) {
						<div class="hidden extradata">
							<table class="table">
								@foreach ($transaction->getExtra() as $k => $v) {
									<tr><th>{{$k}}</th><td>{{$v}}</td></tr>
								@}
							</table>
						</div>
					@ }
				</div>
				</td>
			</tr>

			@if ($balanceError) {
			<tr class="error">
				<td colspan=6>
					<strong><em>Unexpected balance... Expected: {{$newBalance}}</em></strong>
				</td>
			</tr>
			@}

			@$lastBalance = $transaction->getBalance();
		@}
		@if ($count == 0) {
			<tr>
				<td class="error" colspan="6">
					There are no transactions to display.
					@if ($hideEmpty || !empty($searchstring)) {
					<script>$('#transactions_{{$account->getAccountKey()}}').hide();</script>
					@}
				</td>
			</tr>
		@}
		@$totalcount += $count;
		</tbody>

		</table>
		</div>
		</div>
	@}

@if ($totalcount == 0) {
	<em>There are no transactions to show.</em>
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
						<input type="hidden" name="readOnly" value="yes">

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
						<div class="form-group writeOnly">
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
						<div class="form-group writeOnly">
							<label class="col-md-4 control-label" for="value">Value</label>
							<div class="col-md-5">
								<div class="input-group">
									<span class="input-group-addon">&pound;</span>
									<input name="value" type="text" placeholder="" class="form-control input-md">
								</div>
							</div>
						</div>

						<!-- Text input-->
						<div class="form-group extradata">
							<hr>
							<label class="col-md-4 control-label" for="value">Extra Data</label>
							<div class="col-md-5 output">

							</div>
						</div>

					</fieldset>
				</form>
			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="saveTag" type="button" class="btn btn-primary writeOnly">Save changes</button>
			</div>
		</div>
	</div>
</div>

<script>
	$(function() {
		$('td span').tooltip();
		$.bootstrapSortable(true, 'reversed');
	});

	function addTag(clickedTag, readOnly) {
		transid = $(clickedTag).parent().parent().attr('data-id');
		date = $('td.date', $(clickedTag).closest('tr')).attr('data-nice');
		description = $('td.description span', $(clickedTag).closest('tr')).text();
		remaining = parseFloat($(clickedTag).attr('data-remaining')).toFixed(2);
		extradata = $('div.extradata', $(clickedTag).closest('tr')).html();

		$('#addTagForm input[name="transaction"]').val(transid);
		$('#addTagForm input[name="description"]').val(description);
		$('#addTagForm input[name="date"]').val(date);
		$('#addTagForm input[name="value"]').val(remaining);

		if (extradata) {
			$('#addTagForm div.extradata div.output').html(extradata);
			$('#addTagForm div.extradata').show();
		} else {
			$('#addTagForm div.extradata').hide();
		}

		$('#addTagForm input[name="transaction"]').prop('disabled', !readOnly);
		$('#addTagForm input[name="description"]').prop('disabled', !readOnly);
		$('#addTagForm input[name="date"]').prop('disabled', !readOnly);

		if (readOnly) {
			$('#addTagModalLabel').text('Transaction Info');
			$('#addTagModal .writeOnly').hide();
			$('#addTagForm input[name="readOnly"]').val('yes');

			$('#addTagModal').modal();
		} else {
			$('#addTagModalLabel').text('Tag Transaction');
			$('#addTagModal .writeOnly').show();
			$('#addTagForm input[name="readOnly"]').val('no');

			$('#addTagModal').modal();

			if ($(clickedTag).attr('data-usetag') != undefined) {
				$('#addTagForm select[name="tag"]').val($(clickedTag).attr('data-usetag'));
				$('#addTagForm select[name="tag"]').css('background-color', '#FCF8E3')
				$('#addTagForm input[name="value"]').css('background-color', '#FCF8E3')
			} else {
				$('#addTagForm select[name="tag"]').css('background-color', '')
				$('#addTagForm input[name="value"]').css('background-color', '')
			}
		}
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
		addTag(this, false);
	});

	$('td.transactiontags div.tagtext').on('click', 'span.guessedTag', function() {
		addTag(this, false);
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
		readOnly = $('#addTagForm input[name="readOnly"]').val();
		if (readOnly != "no") { return; }

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

	$('.searchicon').click(function() {
		var url = $.jurlp($(document).jurlp("url").toString());
		url.query({'searchstring': $(this).data('searchtext')});
		window.location = url.href;
	});

	$('.infosign').click(function() {
		addTag(this, true);
	});
</script>
