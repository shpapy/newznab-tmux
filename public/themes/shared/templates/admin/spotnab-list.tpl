<div class="well well-sm">
<h1>{$page->title}</h1>

{if $spotnab|@count == 0}
No available sources. <a href="spotnab-edit.php?action=add">Add one?</a>
{else}

<table style="margin-top:10px;" class="data table table-striped responsive-utilities jambo-table">

	<tr>
		<th>source description</th>
		<th>current status</th>
		<th>comments</th>
		<th>last broadcast</th>
		<th>last update</th>
		<th>options</th>
	</tr>

	{foreach from=$spotnab item=source}
	<tr class="{cycle values=",alt"}">
		<td><a href="{$smarty.const.WWW_TOP}/spotnab-edit.php?id={$source.id}">{$source.description}</a></td>
		<td><a href="{$smarty.const.WWW_TOP}/spotnab-list.php?id={$source.id}&toggle={if $source.active=="1"}0{else}1{/if}" {if $source.active=="0"}style="color: #ce0000;"{/if}>{if $source.active=="1"}active{else}inactive{/if}</a></td>
		<td>{$source.comments}</td>
		<td>
			{if $source.lastbroadcast != null}
				{$source.lastbroadcast}
			{else}
				n/a
			{/if}
		</td>
		<td>
			{if $source.lastupdate != null}
				{$source.lastupdate}
			{else}
				n/a
			{/if}
		</td>
		<td><a href="{$smarty.const.WWW_TOP}/spotnab-edit.php?id={$source.id}">edit</a>&nbsp;<a class="confirm_action" href="{$smarty.const.WWW_TOP}/spotnab-delete.php?id={$source.id}">delete</a></td>
	</tr>
	{/foreach}


</table>
{/if}
	</div>
