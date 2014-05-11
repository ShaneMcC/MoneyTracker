<h2> Transactions for {{$period}} - {[date('Y-m-d', $start)]} to {[date('Y-m-d', $end)]} </h2>

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
    var chart_{{$type}} = new google.visualization.PieChart(element_{{$type}});
    var meta_{{$type}} = {{json_encode($data['metadata'])`}};
    chart_{{$type}}.draw(data_{{$type}},
               {title: '{{ucfirst($type)}} ({{money_format('%.2n', $data['total'])}})',
                is3D: true,
                tooltip: {showColorCode: true},
               }
              );

    google.visualization.events.addListener(chart_{{$type}}, 'select', function() {
      if (chart_{{$type}}.getSelection()[0]) {
        var row = chart_{{$type}}.getSelection()[0].row;

        if (meta_{{$type}}[row]['tagid']) {
          window.location = '{[getWebLocation]}taggedtransactions/' + meta_{{$type}}[row]['tagid'];
        } else {
          var url = $.jurlp($(document).jurlp("url").toString());
          url.query({'cat': meta_{{$type}}[row]['catid']});
          window.location = url.href;
        }
      }
    });
  @ }
}
</script>
