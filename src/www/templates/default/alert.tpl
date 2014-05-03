	<div class="modal fade" id="modalAlert" role="dialog">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal">×</button>
					<h3 class="modal-title">{{$message[1]}}</h3>
				</div>
				<div class="modal-body">
					<p>
						@if (isset($message[3]) && $message[3] == false) {
							{{$message[2]`}}
						@} else {
							{{$message[2]}}
						@}
					</p>
				</div>
				<div class="modal-footer">
					<a href="#" class="btn btn-primary" data-dismiss="modal">Close</a>
				</div>
			</div>
		</div>
	</div>

	<script type="text/javascript">
		$('#modalAlert').modal({backdrop: "static"});
	</script>