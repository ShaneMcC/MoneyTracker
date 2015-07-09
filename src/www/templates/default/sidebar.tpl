
<div id="sidebardiv" class="well" style="padding: 8px 0;">
	<ul id="sidebar" class="nav nav-list">
		{-- This is quite PHP-y, but oh well... --}
		@foreach ($sidebar as $section) {
			@if (isset($section['__HEADER__'])) {
				<li class="nav-header">{{$section['__HEADER__']}}</li>
			@} else {
				<li class="divider"></li>
			@}

			@foreach ($section as $key => $item) {
				@if ($key === '__HEADER__') { continue; }
				@if (!isset($item['icon'])) { $item['icon'] = 'none'; }
				@$class = array();
				@if (isset($item['Active']) && $item['Active']) { $class[] = 'active'; } else if ($_SERVER['REQUEST_URI'] == $item['Link']) { $class[] = 'active'; }
				@$class = implode(' ', $class);

				@$margin = 15 + (isset($item['Margin']) ? (int)$item['Margin'] : 0);

				<li class="{{$class}}"><a style="padding-left: {{$margin}}px;" href="{{$item['Link']}}"><span class="glyphicon glyphicon-{{$item['Icon']}}"></span> {{$item['Title']}}</a></li>
			@} /* Foreach section */
		@} /* Foreach sidebar */

		@ if ($showPeriods) {
			<li class="nav-header">Change Period</li>
			@ foreach (getValidPeriods() as $period => $data) {

				@$class = array();
				@if ($thisPeriod == $period) { $class[] = 'active'; }
				@$class = implode(' ', $class);
				<li class="{{$class}}"><a style="padding-left: 15px;" class="periodselection" data-period="{{$period}}"><span class="glyphicon glyphicon-dashboard"></span> {{$data['name']}}</a></li>
			@}
		@ }

		<li class="nav-header">Hidden Accounts</li>

		<li class="{{ isset($showHiddenAccounts) && $showHiddenAccounts ? "active" : "" }}"><a style="padding-left: 15px;" class="showHiddenAccounts"><span class="glyphicon glyphicon-eye-open"></span> Show Hidden Accounts</a></li>
		<li class="{{ !isset($showHiddenAccounts) || !$showHiddenAccounts ? "active" : "" }}"><a style="padding-left: 15px;" class="hideHiddenAccounts"><span class="glyphicon glyphicon-eye-close"></span> Hide Hidden Accounts</a></li>
	</ul>
</div>

<script>
	$('.periodselection').click(function() {
		var url = $.jurlp($(document).jurlp("url").toString());
		url.query({'period': $(this).data('period')});
		window.location = url.href;
	});

	$('.showHiddenAccounts').click(function() {
		var url = $.jurlp($(document).jurlp("url").toString());
		url.query({'showHiddenAccounts': true});
		window.location = url.href;
	});

	$('.hideHiddenAccounts').click(function() {
		var url = $.jurlp($(document).jurlp("url").toString());
		url.query({'showHiddenAccounts': false});
		window.location = url.href;
	});
</script>
