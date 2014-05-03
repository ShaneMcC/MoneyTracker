	<div class="alert alert-block alert-{{$message[0]}}">
		<a class="close" data-dismiss="alert" href="#">×</a>
		<h4 class="alert-heading">{{$message[1]}}</h4>
		@if (isset($message[3]) && $message[3] == false) {
			{{$message[2]`}}
		@} else {
			{{$message[2]}}
		@}
	</div>

	<script type="text/javascript">
		$(".alert").alert()
	</script>