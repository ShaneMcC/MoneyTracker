<h1>Current Balances</h1>
<div class="row">
	@ $i = 0;
	@ foreach ($accounts as $acct) {
		@ if ($i++ % 3 == 0) {
			</div><div class="row">
		@ }
		<div class="col-sm-3">
			<h2>{{$acct->getDisplayName()}}<small><br>{{$acct->getType()}}</small></h2>
			<p>
				<strong>Balance:</strong> {{money_format('%.2n', $acct->getBalance())}}
			</p>
			<p>
				@ $bits = explode('/', $acct->getSource());
				<img src="{[getWebLocation]}bankimage/{{$bits[0]}}.svg" class="banklogo" alt="Logo">
			</p>
			<p><a class="btn btn-default btn-xs" href="{[getWebLocation]}transactions/{{$acct->getAccountKey()}}">Details »</a></p>
		</div>
	@ }
</div>
