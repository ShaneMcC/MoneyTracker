<!-- Chart stuff below -->
<div class="chartcontainer" id="containerchart_balance" style="display: none;">
	<h3>Balance Graph</h3>
	<div id="dashboard_chart_balance" class="resizable dashboard">
		<div class="hasControls chart " id="chart_balance"></div>
		<div class="control" id="control_chart_balance"></div>
	</div>
</div>

<script src="http://code.jquery.com/ui/1.9.1/jquery-ui.js"></script>
<style>
	.ui-resizable-helper {
		border: 1px dotted gray;
	}
	.resizable {
		border: 1px solid gray;
		margin-bottom: 5px;
	}

	.dashboard {
		height: 400px;
		padding-bottom: 20px;
	}

	.dashboard .chart.hasControls {
		height: 80%;
	}

	.dashboard .chart {
		height: 100%;
	}

	.dashboard .control {
		height: 20%;
	}
</style>
<link rel="stylesheet" href="http://code.jquery.com/ui/1.9.1/themes/base/jquery-ui.css" />
<script type="text/javascript" src="https://www.google.com/jsapi"></script>
<script type="text/javascript">
	google.load('visualization', '1.1', {packages:['corechart', 'controls']});
	google.setOnLoadCallback(function() {
		if (prepareCharts()) {
			$('.chartcontainer').show(250, function() {
				var chartID = $('.chart', this).attr('id');
				drawChart(chartID, false);
				drawDashboard(chartID, false);
			});

			$('.resizable').resizable({
					animate: false,
					resize: function(event, ui) {
						var chartID = $('.chart', ui.element).attr('id');
						drawChart(chartID, false);
						drawDashboard(chartID, false);
					}
			});
		}
	});

	function hasDashboard(chartID) {
		return showDashboard[chartID][charttype[chartID]];
	}

	function checkControls(chartID) {
		if (hasDashboard(chartID)) {
			$('#' + chartID).addClass('hasControls');
			drawChart(chartID, false);
			$('#control_' + chartID).show(250, function() {
				drawDashboard(chartID, true);
			});
			return true;
		} else {
			$('#control_' + chartID).hide(250, function() {
				$('#' + chartID).removeClass('hasControls');
				drawChart(chartID, false);
			});
			return false;
		}
	}

	var charts = {};
	var chartdata = {};
	var charttype = {};
	var charttypes = {};
	var chartoptions = {};
	var dashboard = {};
	var controls = {};
	var showDashboard = {};

	function prepareCharts() {
		var chartID = 'chart_balance';
		chartdata[chartID] = new google.visualization.DataTable();
		chartdata[chartID].addColumn('date', 'Period');
		chartdata[chartID].addColumn('number', 'Balance');

		@$count = 0;
		@foreach ($account->getTransactions() as $transaction) {
			{-- TODO: This needs to be handled by the class not the template. --}
			@if ($transaction->getTime() < $start) { continue; }
			@if ($transaction->getTime() > $end) { continue; }
			@if (!empty($searchstring) || $onlyUntagged) { continue; }

			@$count++
			@$transNumber = 1 + ($transaction->getTime() - strtotime(date("Y-m-d", $transaction->getTime())));

		chartdata[chartID].addRow([
				{v:new Date({{$transaction->getTime()}} * 1000), f:"{{date("Y-m-d", $transaction->getTime()).' ('.money_format('%.2n', $transaction->getAmount()).')'}}"},
				{v:{{$transaction->getBalance()}}, f:"{{money_format('%.2n', $transaction->getBalance())}}"},
			]);
		@}

		showDashboard[chartID] = { };
		chartoptions[chartID] = { };
		charttypes[chartID] = { "LineChart": "LineChart" };

		chartoptions[chartID]["Line Chart"] = [];
		charttypes[chartID]["Line Chart"] = "LineChart";
		showDashboard[chartID]["Line Chart"] = true;

		var type = "Line Chart";

		drawChart(chartID, true, type);
		if (checkControls(chartID)) {
			drawDashboard(chartID, false);
		}

		return {{$count == 0 ? "false" : "true"}};
	}

	function drawDashboard(chartID, rebind) {
		if (!hasDashboard(chartID)) { return; }

		if (undefined == dashboard[chartID]) {
			dashboard[chartID] = new google.visualization.Dashboard(document.getElementById('dashboard_'+chartID));
			controls[chartID] = new google.visualization.ControlWrapper({
				'controlType': 'ChartRangeFilter',
				'containerId': 'control_'+chartID,
				'options': {
					'filterColumnIndex': 0,
					'ui': {
						'chartOptions': {
							'chartArea': {'width': '75%'},
						}
					}
				},
			});

			dashboard[chartID].bind(controls[chartID], charts[chartID]);
		} else if (rebind) {
			dashboard[chartID].bind(controls[chartID], charts[chartID]);
		}
		dashboard[chartID].draw(chartdata[chartID]);
	}

	function drawChart(chartID, newChart, type) {
		if (!chartID) { return; }
		if (!type) { type = 'LineChart'; }
		if (newChart) {
			charttype[chartID] = type;
			var chartOptions = {
				'chartType': charttypes[chartID][type],
				'containerId': chartID,
				'options': chartoptions[chartID][charttype[chartID]] ? chartoptions[chartID][charttype[chartID]] : { },
				'dataTable': chartdata[chartID],
			}

			chartOptions['options']['chartArea'] = {'width': '75%'},

			charts[chartID] = new google.visualization.ChartWrapper(chartOptions);
			$('#container' + chartID + ' .options button').removeClass('active btn-info');
			$('#container' + chartID + ' .options button[data-type="' + type + '"]').addClass('active btn-info');
		}

		charts[chartID].draw();

		@ if ($count > 0) {
		$('#chartlink_' + chartID).css('display', 'inline');
		@ }
	}
</script>
<!-- Chart stuff above -->
