<h1>Current Balances</h1>
<div class="row">
	@ $i = 0;
	@ foreach ($accounts as $acct) {
		@ if (!$showHiddenAccounts && $acct->getHidden() == 1) { continue; }
		@ if ($i++ % 3 == 0) {
			</div><div class="row">
		@ }
		<div class="col-sm-3 {{ $acct->getHidden() ? "alert-danger" : "" }}">
			<h2>{{$acct->getDisplayName()}}
			<small><br>{{$acct->getDescriptionOrType()}} <span class="editDescription glyphicon glyphicon-pencil" data-accountkey="{{$acct->getAccountKey()}}" data-desc="{{$acct->getDescriptionOrType()}}" data-type="{{$acct->getType()}}"/></small>
			</h2>
			<p>
				<strong>Balance:</strong> {{money_format('%.2n', $acct->getBalance())}}
			</p>
			<p>
				@ $bits = explode('/', $acct->getSource());
				<img src="{[getWebLocation]}bankimage/{{$bits[0]}}.svg" class="banklogo" alt="Logo">
			</p>
			<p>
			  <button class="btn {{ $acct->getHidden() ? "btn-danger" : "btn-default" }} btn-xs toggleHide" data-hidden="{{$acct->getHidden()}}" data-accountkey="{{$acct->getAccountKey()}}">
			  {{ $acct->getHidden() ? "Unhide Account" : "Hide Account" }}
			  </button>
			  <a class="btn btn-default btn-xs" href="{[getWebLocation]}transactions/{{$acct->getAccountKey()}}">Details »</a>
			</p>
		</div>
	@ }
</div>

<hr>
@ if (!$showHiddenAccounts) {
	<button class="btn btn-default btn-xs showHidden">Show Hidden Accounts</button>
@ } else {
	<button class="btn btn-default btn-xs hideHidden">Hide Hidden Accounts</button>
@ }

<form id="doAccountAction" method="post">
	<input type="hidden" name="accountaction_action" value="">
	<input type="hidden" name="accountaction_id" value="">
	<input type="hidden" name="accountaction_value" value="">
</form>

<script>
	$('.showHidden').click(function() {
		var url = $.jurlp($(document).jurlp("url").toString());
		url.query({'showHiddenAccounts': true});
		window.location = url.href;
	});

	$('.hideHidden').click(function() {
		var url = $.jurlp($(document).jurlp("url").toString());
		url.query({'showHiddenAccounts': false});
		window.location = url.href;
	});

	$('.editDescription').click(function() {
		accountkey = $(this).attr('data-accountkey');
		accountdesc = $(this).attr('data-desc');
		accounttype = $(this).attr('data-type');

		bootbox.prompt({title: "Set Description (" + accounttype + ")",
			            value: accountdesc,
		                callback: function(result) {
			if (result !== null && result.length > 0) {
				$('#doAccountAction input[name="accountaction_action"]').val('editDescription');
				$('#doAccountAction input[name="accountaction_id"]').val(accountkey);
				$('#doAccountAction input[name="accountaction_value"]').val(result);
				$('#doAccountAction').submit();
			}
		}});
	});

	$('.toggleHide').click(function() {
		accountkey = $(this).attr('data-accountkey');
		ignore = $(this).attr('data-hidden');

		ignoreTitle = "Are you sure you want to hide this account?";
		ignoreMessage = "Hidden accounts do not show up by default on the home page or transactions view.";

		unignoreTitle = "Are you sure you want to un-hide this account?";
		unignoreMessage = "Non-Hidden accounts will appear on the home page and transactions view.";

		bootbox.confirm({title: (ignore == 1 ? unignoreTitle : ignoreTitle),
		                 message: (ignore == 1 ? unignoreMessage : ignoreMessage),
		                 callback: function(result) {
			if (result) {
				$('#doAccountAction input[name="accountaction_action"]').val('toggleHide');
				$('#doAccountAction input[name="accountaction_id"]').val(accountkey);
				$('#doAccountAction input[name="accountaction_value"]').val(ignore == 1 ? '0' : '1');
				$('#doAccountAction').submit();
			}
		}});
	});
</script>
