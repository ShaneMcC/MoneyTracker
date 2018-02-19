<h2> Transactions for {{$periodName}} - {[date('Y-m-d', $start)]} to {[date('Y-m-d', $end)]} </h2>

<div class="bs-callout-info">
	<strong>Incoming Total:</strong> {{money_format('%.2n', $chart['incoming']['total'])}}
</div>
<div class="bs-callout-info">
	<strong>Outgoing Total:</strong> {{money_format('%.2n', $chart['outgoing']['total'])}}
</div>
<div class="{{ $chart['incoming']['total'] < abs($chart['outgoing']['total']) ? 'bs-callout-danger' : 'bs-callout-success' }}"><strong>Final:</strong> {{money_format('%.2n', $chart['incoming']['total'] - abs($chart['outgoing']['total']))}}</div>


@ foreach ($chart as $type => $data) {
	<div id="chart_{{$type}}" style="width: 900px; height: 500px;"></div>
@ }

<script type="text/javascript" src="https://www.google.com/jsapi"></script>
<script type="text/javascript">
	google.load("visualization", "1", {packages:["corechart"]});
	google.setOnLoadCallback(drawCharts);

	function drawCharts() {
		@ foreach ($chart as $type => $data) {
			var data_{{$type}} = google.visualization.arrayToDataTable( {{json_encode($data['data'])`}} );
			var element_{{$type}} = document.getElementById('chart_{{$type}}');
			var chart_{{$type}} = new google.visualization.{{$data['charttype']}}(element_{{$type}});
			var meta_{{$type}} = {{json_encode($data['metadata'])`}};
			chart_{{$type}}.draw(data_{{$type}},
			                     {
			                      title: '{{isset($data["title"]) ? $data["title"] : ucfirst($type)}}{{$data["showtotal"] ? ' (' . money_format('%.2n', $data['total']) . ')' : ''}}',
			                      is3D: true,
			                      tooltip: {showColorCode: true},
			                     }
			                    );

			google.visualization.events.addListener(chart_{{$type}}, 'select', function() {
				if (chart_{{$type}}.getSelection()[0]) {
					var row = chart_{{$type}}.getSelection()[0].row;
					@ if ($data['hascolumns']) {
						var col = chart_{{$type}}.getSelection()[0].column - 1;
					@ }
					var url = $.jurlp($(document).jurlp("url").toString());
					@ if ($data['hascolumns']) {
						if (meta_{{$type}}['tagid']) {
					@ } else {
						if (meta_{{$type}}[row]['tagid']) {
					@ }
						period = url.query()['period'];
						if (period == undefined) { period = 'last14days'; }
						@ if ($data['hascolumns']) {
							window.location = '{[getWebLocation]}taggedtransactions/' + meta_{{$type}}['tagid'][col] + '?period=' + period;
						@ } else {
							window.location = '{[getWebLocation]}taggedtransactions/' + meta_{{$type}}[row]['tagid'] + '?period=' + period;
						@ }
					} else {
						@ if ($data['hascolumns']) {
							url.query({'cat': meta_{{$type}}['catid'][col]});
						@ } else {
							url.query({'cat': meta_{{$type}}[row]['catid']});
						@ }
						window.location = url.href;
					}
				}
			});
		@ }
	}
</script>
