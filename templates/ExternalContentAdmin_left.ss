<% require javascript(external-content/thirdparty/jquery-jstree/jquery.jstree.js) %>
<% require javascript(external-content/javascript/ExternalContentAdmin.js) %>
<% require css(external-content/css/ExternalContentAdmin.css) %>

<h2><% _t('CONNECTORS', 'Connectors') %></h2>

<div id="TreeTools">
	<ul id="TreeActions">
		<li class="action">
			<button id="ToggleCreateForm"><% _t('CREATE', 'Create') %></button>
		</li>
	</ul>
	$CreateSourceForm
</div>

<div id="ExternalItems" href="$Link(tree)"></div>