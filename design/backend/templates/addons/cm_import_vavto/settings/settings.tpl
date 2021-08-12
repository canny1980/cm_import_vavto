<fieldset>
    {assign var="magic_key" value=$addons.cm_import_vavto.cron_key|urlencode}
    <input type="hidden" name="magic_key" value="{$magic_key}"/>
    <div class="control-group">
        <label class="control-label" for="symbol_update">{__('cm_import_vavto.magic_keygen')}:</label>
        <div class="controls" id="symbol_update">
            {include file="buttons/button.tpl" but_role="submit" but_name="dispatch[cm_import_vavto.keygen]" but_text=__("cm_import_vavto.magic_keygen_button")}
        </div>
    </div>

    <div class="control-group">
        <label class="control-label" for="symbol_update">{__('cm_import_vavto.currency_sync_auto')}:</label>
        <div class="controls" id="symbol_update">
            {__('cm_import_vavto.auto_info')}
            <br />
            {array($magic_key,'cm_import_vavto.import_cron')|fn_cm_cron_link}
            <br />
        </div>
    </div>
    <div class="control-group">
        <label class="control-label" for="obj_update">{__('cm_import_vavto.update_object')}:</label>
        <div class="controls" id="obj_update">
            {include file="buttons/button.tpl" but_role="submit" but_name="dispatch[cm_import_vavto.import_manual]" but_text=__("cm_import_vavto.update_object_button")}
        </div>
    </div>
</fieldset>
