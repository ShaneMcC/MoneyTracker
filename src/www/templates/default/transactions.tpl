	@foreach ($accounts as $account) {
		<h1>{{$account->getFullNumber()}}</h1>

		<div class="table-responsive">
		<table class="table table-striped table-bordered table-hover table-condensed">
		<tr>
		<th>Date</th>
		<th>Type</th>
		<th>Description</th>
		<th>Amount</th>
		<th>Balance</th>
		{-- <th>Hash</th> --}
		<th>Tags</th>
		</tr>

		@$lastBalance = null;
		@$cutoff = strtotime("01 jan 2014");
		@foreach ($account->getTransactions() as $transaction) {
			@if ($transaction->getTime() < $cutoff) { continue; }
			<tr>
			<td>{{date("Y-m-d H:i:s", $transaction->getTime())}}</td>
			<td><span data-toggle="tooltip" title="{{$transaction->getType()}}">{{$transaction->getTypeCode()}}</span></td>
			<td><span data-toggle="tooltip" title="{{$transaction->getHash()}}">{{$transaction->getDescription()}}</span></td>
			<td>{{money_format('%.2n', $transaction->getAmount())}}</td>
			<td>{{money_format('%.2n', $transaction->getBalance())}}</td>
			{-- <td>{{$transaction->getHash()}}</td> --}
			<td class="transactiontags" data-tags="{{htmlspecialchars(json_encode($transaction->getTags()))}}" data-id="{{$transaction->getHash()}}" id="tags-{{$transaction->getHash()}}">
			<div class="tagtext">
			@ foreach ($transaction->getTags() as $t) {
			<span class="label label-success">
			{{$tags[$t[0] ]}}
			</span>&nbsp;
			@ }
			</div>
			<div class="tagselect" />
			</td>
			</tr>

			@if ($lastBalance !== null) {
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

		</table>
		</div>
	@}

<script>
    $('td span').tooltip();

	var tags = <?=json_encode($jsontags);?>

	function createDropDown(selected) {
		var s = $("<select name=\"tag[]\" />");
		$.each(tags, function(group, grouptags) {
			var g = $('<optGroup/>').attr('label', group).appendTo(s);
			$.each(grouptags, function(tag, id) {
				var o = $('<option/>').val(id).text(tag);
				o.appendTo(g);
			});
		});

		return s;
	}

	$('td.transactiontags div.tagtext').click(function() {
		$(this).hide();
		var parent = $(this).parent();

		currentTags = jQuery.parseJSON(parent.attr('data-tags'));
		hash = parent.attr('data-id');

		var selectDiv = $('div.tagselect', parent);

		$.each(currentTags, function() {
			id = this[0];
			value = this[1];
			createDropDown(id).appendTo(selectDiv);
		});

		selectDiv.show();
	});
</script>