<div class="row">
<h1>Oops!</h1>
<p>
	Oh, it looks like something bad happened, that page you wanted? I'm afriad I can't let you see it.
</p>
@ if (session::isLoggedIn()) {
<p>
	If you think there has been a mistake, and that you should be given access to this page, then please let us know!
</p>
@} else {
<p>
	Perhaps if you were logged in there wouldn't be a problem? Try it and see!
</p>
@}
<p>
	<p><a class="btn" href="{[getWebLocation]}">Return Home &raquo;</a></p>
</p>
<p>
	<small>It looks like you wanted: {{$_SERVER['REQUEST_URI']}}</small>
</p>
</div>