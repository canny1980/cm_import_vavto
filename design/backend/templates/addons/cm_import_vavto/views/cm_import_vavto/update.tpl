{capture name="mainbox"}
    <div id="content_categories">
        {if $remote_categories}
            <div id="bind_categories_content">
				<form id="categories_api_form" action="{""|fn_url}" method="post" name="categories_api_form" enctype="multipart/form-data">
					<table class="table table-width">
						<tr>
							<th width="40%">{__("cm_import_vavto.remote_category_id")}</th>
							<th width="60%">{__("cm_import_vavto.local_categories")}</th>
						</tr>

						{foreach from=$remote_categories item="category"}
						    <tr>
							<td>{$category.remote_category_name}</td>
							<td>
							    {include file="pickers/categories/picker.tpl" company_ids=$runtime.company_id data_id="categories_`$category.remote_category_id`" input_name="remote_category_ids[{$category.remote_category_id}]" item_ids=$category.category_ids hide_link=true display_input_id="category_ids_`$category.remote_category_id`" disable_no_item_text=true view_mode="list" but_meta="btn" show_active_path=true}
							</td>
						    </tr>
						{/foreach}
					</table>
				</form>
            </div>
        {else}
            <p class="no-items">{__("no_data")}</p>
        {/if}
    </div>

	{capture name="buttons"}
		{if $remote_categories}
			{include file="buttons/save.tpl" but_name="dispatch[cm_import_vavto.save]" but_role="submit-button" but_target_form="categories_api_form"}
		{/if}
	{/capture}

{/capture}

{include file="common/mainbox.tpl"
    title=__("cm_import_vavto.categories_binding")
    content=$smarty.capture.mainbox
    buttons=$smarty.capture.buttons
}
