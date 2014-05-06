<h1>Categories</h1>

@ $i = 0;
@ foreach ($tags as $category => $data) {
	@ if ($i++ % 3 == 0) {
		</div><div class="row">
	@ }

	<div class="col-sm-3">
		<h2>
		{{$data['name']}}
		<button type="button" class="btn btn-danger btn-xs deleteCategory" data-categoryid="{{$category}}"><span class="glyphicon glyphicon-remove" /></button>
		<button type="button" class="btn btn-warning btn-xs editCategory" data-categoryid="{{$category}}" data-catname="{{$data['name']}}"><span class="glyphicon glyphicon-pencil" /></button>
		</h2>
		<p>
			@ foreach ($data['tags'] as $tag => $id) {
				<button type="button" class="btn btn-danger btn-xs deleteTag" data-tagid="{{$id}}"><span class="glyphicon glyphicon-remove" /></button>
				<button type="button" class="btn btn-warning btn-xs editTag" data-tagid="{{$id}}" data-tagname="{{$tag}}"><span class="glyphicon glyphicon-pencil" /></button>
				{{$tag}}
				<br>
			@ }
			<button type="button" class="btn btn-success btn-xs addTag" data-categoryid="{{$category}}"><span class="glyphicon glyphicon-plus" /></button>
		</p>
	</div>
@ }

</div>

<div class="row">
<button type="button" class="btn btn-success btn-lg addCategory">Add Category</button>
</div>

<form id="doTagAction" method="post">
	<input type="hidden" name="tagaction_action" value="">
	<input type="hidden" name="tagaction_id" value="">
	<input type="hidden" name="tagaction_value" value="">
</form>

<script>
	$('.deleteTag').click(function() {
		tagid = $(this).attr('data-tagid');
		bootbox.confirm({title: "Are you sure you want to delete this tag?",
		                 message: "Deleting this tag will untag any transactions that were tagged with it.",
		                 callback: function(result) {
			if (result) {
				$('#doTagAction input[name="tagaction_action"]').val('deleteTag');
				$('#doTagAction input[name="tagaction_id"]').val(tagid);
				$('#doTagAction input[name="tagaction_value"]').val('');
				$('#doTagAction').submit();
			}
		}});
	});

	$('.editTag').click(function() {
		tagid = $(this).attr('data-tagid');
		oldName = $(this).attr('data-tagname');

		bootbox.prompt({title: "New name for Tag",
		                value: oldName,
		                callback: function(result) {
			if (result !== null && result.length > 0) {
				$('#doTagAction input[name="tagaction_action"]').val('editTag');
				$('#doTagAction input[name="tagaction_id"]').val(tagid);
				$('#doTagAction input[name="tagaction_value"]').val(result);
				$('#doTagAction').submit();
			}
		}});
	});

	$('.addTag').click(function() {
		categoryid = $(this).attr('data-categoryid');

		bootbox.prompt({title: "Add new tag",
		                callback: function(result) {
			if (result !== null && result.length > 0) {
				$('#doTagAction input[name="tagaction_action"]').val('addTag');
				$('#doTagAction input[name="tagaction_id"]').val(categoryid);
				$('#doTagAction input[name="tagaction_value"]').val(result);
				$('#doTagAction').submit();
			}
		}});
	});



	$('.deleteCategory').click(function() {
		catid = $(this).attr('data-categoryid');
		bootbox.confirm({title: "Are you sure you want to delete this category?",
		                 message: "Deleting this category will also delete all it's tags",
		                 callback: function(result) {
			if (result) {
				$('#doTagAction input[name="tagaction_action"]').val('deleteCategory');
				$('#doTagAction input[name="tagaction_id"]').val(catid);
				$('#doTagAction input[name="tagaction_value"]').val('');
				$('#doTagAction').submit();
			}
		}});
	});

	$('.editCategory').click(function() {
		catid = $(this).attr('data-categoryid');
		oldName = $(this).attr('data-catname');

		bootbox.prompt({title: "New name for category",
		                value: oldName,
		                callback: function(result) {
			if (result !== null && result.length > 0) {
				$('#doTagAction input[name="tagaction_action"]').val('editCategory');
				$('#doTagAction input[name="tagaction_id"]').val(catid);
				$('#doTagAction input[name="tagaction_value"]').val(result);
				$('#doTagAction').submit();
			}
		}});
	});

	$('.addCategory').click(function() {
		bootbox.prompt({title: "Add new category",
		                callback: function(result) {
			if (result !== null && result.length > 0) {
				$('#doTagAction input[name="tagaction_action"]').val('addCategory');
				$('#doTagAction input[name="tagaction_id"]').val('');
				$('#doTagAction input[name="tagaction_value"]').val(result);
				$('#doTagAction').submit();
			}
		}});
	});


</script>

{-- This row ends the row we are wrapped in by default. --}
<div class="row">
