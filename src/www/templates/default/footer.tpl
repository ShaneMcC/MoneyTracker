		</div>
	</div>

	<script type="text/javascript">
		$('img.minigravatar').wrap(function() {
			return '<span style="background-image:url(' + $(this).attr('src') + '); height: '+ $(this).css('height') + '; width: '+ $(this).css('width') + ';" class="minigravatar" />';
		});
	</script>

	<div class="{{$fluid ? 'container-fluid' : 'container'}}" role="main">
		<div class="row">
			<hr>
			<footer>
				<p>&copy; Shane 'Dataforce' Mc Cormack</p>
			</footer>
		</div>
	</div>

</body>
</html>