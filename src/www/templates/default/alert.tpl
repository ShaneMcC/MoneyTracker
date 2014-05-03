	<div class="modal hide" id="modalAlert">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal">×</button>
			<h3>{{$message[1]}}</h3>
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

	<script type="text/javascript">
		$('#modalAlert').modal({backdrop: "static"});
	</script>