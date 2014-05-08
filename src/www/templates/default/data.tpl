<h2> Transactions for {{$period}} - {[date('Y-m-d', $start)]} to {[date('Y-m-d', $end)]} </h2>

<script type="text/javascript" src="https://www.google.com/jsapi"></script>

<script type="text/javascript">
  google.load("visualization", "1", {packages:["corechart"]});
  google.setOnLoadCallback(drawCharts);

function drawCharts() {
  @ $intotal = $outtotal = 0;
  var incoming = google.visualization.arrayToDataTable([ ['Category', 'Amount'],
    @ foreach ($incoming as $row) { echo sprintf('["%s :: %s", %.2f],', $row['cat'], (isset($row['tag']) ? $row['tag'] : ''), $row['value']); $intotal += $row['value'];}
  ]);
  new google.visualization.PieChart(document.getElementById('incoming')).draw(incoming, {title: 'Incoming ({{$intotal}})', is3D: true});

  var outgoing = google.visualization.arrayToDataTable([ ['Category', 'Amount'],
    @ foreach ($outgoing as $row) { echo sprintf('["%s :: %s", %.2f],', $row['cat'], (isset($row['tag']) ? $row['tag'] : ''), abs($row['value'])); $outtotal += abs($row['value']); }
  ]);
  new google.visualization.PieChart(document.getElementById('outgoing')).draw(outgoing, {title: 'Outgoing ({{$outtotal}})', is3D: true});
}
</script>

<div class="bs-callout-info">
<strong>Incoming Total:</strong> {{money_format('%.2n', $intotal)}}
</div>
<div class="bs-callout-info">
<strong>Outgoing Total:</strong> {{money_format('%.2n', $outtotal)}}
</div>
<div class="{{ $intotal < $outtotal ? 'bs-callout-danger' : 'bs-callout-success' }}"><strong>Final:</strong> {{money_format('%.2n', $intotal - $outtotal)}}</div>

<div id="incoming" style="width: 900px; height: 500px;"></div>
<div id="outgoing" style="width: 900px; height: 500px;"></div>
