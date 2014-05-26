<div class="well">
<div class="row">
<div class="col-sm-2"><button type="button" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash" /></button>&nbsp;&nbsp;Delete Tag/Category</div>
<div class="col-sm-2"><button type="button" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil" /></button>&nbsp;&nbsp;Edit Tag/Category</div>
<div class="col-sm-2"><button type="button" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-plus" /></button>&nbsp;&nbsp;Add Tag</div>
<div class="col-sm-2"><button type="button" class="btn btn-primary btn-active btn-xs"><span class="glyphicon glyphicon-ok" /></button>&nbsp;&nbsp;Tag is not currently ignored</div>
<div class="col-sm-2"><button type="button" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-remove" /></button>&nbsp;&nbsp;Tag is currently ignored</div>
<div class="col-sm-2"><button type="button" class="btn btn-info btn-xs"><span class="glyphicon glyphicon-search" /></button>&nbsp;&nbsp;Show transactions with this tag</div>
</div>
</div>

<h1>Categories</h1>
@ $i = 0;
@ foreach ($tags as $category => $data) {
	@ if ($i++ % 3 == 0) {
		</div><div class="row">
	@ }

	<div class="col-sm-3">
		<h2>
		{{$data['name']}}
		<button type="button" class="btn btn-danger btn-xs deleteCategory" data-categoryid="{{$category}}"><span class="glyphicon glyphicon-trash" /></button>
		<button type="button" class="btn btn-warning btn-xs editCategory" data-categoryid="{{$category}}" data-catname="{{$data['name']}}"><span class="glyphicon glyphicon-pencil" /></button>
		</h2>
		<p>
			@ foreach ($data['tags'] as $tag => $t) {
				@ $id = $t['tagid'];
				@ $ignore = $t['ignore'];
				@ $ignoreIcon = ($t['ignore'] == '0' ? 'ok' : 'remove');
				@ $ignoreButton = ($t['ignore'] == '0' ? 'btn-primary btn-active' : 'btn-default');
				@ $ignoreTitle = ($t['ignore'] == '0' ? 'Unignored' : 'Ignored');
				<button type="button" class="btn btn-danger btn-xs deleteTag" data-tagid="{{$id}}"><span class="glyphicon glyphicon-trash" /></button>
				<button type="button" class="btn btn-warning btn-xs editTag" data-tagid="{{$id}}" data-tagname="{{$tag}}"><span class="glyphicon glyphicon-pencil" /></button>
				{-- <button type="button" class="btn btn-primary btn-xs toggleIgnore" data-tagid="{{$id}}" data-ignore="{{$ignore}}"><span class="glyphicon glyphicon-{{$ignoreIcon}}" /></button> --}
				<button type="button" class="btn btn-xs toggleIgnore {{$ignoreButton}}" data-tagid="{{$id}}" data-ignore="{{$ignore}}"><span class="glyphicon glyphicon-{{$ignoreIcon}}" /></button>
				<button type="button" class="btn btn-info btn-xs searchTag" data-tagid="{{$id}}"><span class="glyphicon glyphicon-search" /></button>
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
	$('.toggleIgnore').click(function() {
		tagid = $(this).attr('data-tagid');
		ignore = $(this).attr('data-ignore');

		ignoreTitle = "Are you sure you want to ignore this tag?";
		ignoreMessage = "Transactions tagged with ignored tags are not counted in stats.";

		unignoreTitle = "Are you sure you want to unignore this tag?";
		unignoreMessage = "Transactions tagged with unignored tags will be counted in stats.";

		bootbox.confirm({title: (ignore == 1 ? unignoreTitle : ignoreTitle),
		                 message: (ignore == 1 ? unignoreMessage : ignoreMessage),
		                 callback: function(result) {
			if (result) {
				$('#doTagAction input[name="tagaction_action"]').val('setIgnore');
				$('#doTagAction input[name="tagaction_id"]').val(tagid);
				$('#doTagAction input[name="tagaction_value"]').val(ignore == 1 ? '0' : '1');
				$('#doTagAction').submit();
			}
		}});
	});

	$('.searchTag').click(function() {
		window.location = '{[getWebLocation]}taggedtransactions/' + $(this).attr('data-tagid');
	});

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
