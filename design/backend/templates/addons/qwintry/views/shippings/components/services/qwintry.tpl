<fieldset>
{include file="common/subheader.tpl" title=__("qwintry_instructions")}
<p class="qwintry-instructions">
    {if $runtime.company_id > 0}
        {assign var="company_id" value=$runtime.company_id}
    {else}
        {assign var="company_id" value=""|fn_get_default_company_id}
    {/if}
    {__("qwintry_instructions_content", ['[company_name]' => fn_get_company_name($company_id)])|nl2br}
</p>

{include file="common/subheader.tpl" title=__("general_info")}

<div class="control-group">
    <label class="control-label" for="ship_qwintry_api_key">{__("api_key")}</label>
    <div class="controls">
    <input id="ship_qwintry_api_key" type="text" name="shipping_data[service_params][api_key]" size="30" value="{$shipping.service_params.api_key}" />
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="ship_mode">{__("qwintry_mode")}</label>
    <div class="controls">
        <select id="ship_mode" name="shipping_data[service_params][mode]">
            <option value="test"{if $shipping.service_params.mode == 'test'} selected{/if}>{__("test")}</option>
            <option value="live"{if $shipping.service_params.mode == 'live'} selected{/if}>{__("live")}</option>
        </select>
     </div>
</div>


    {assign var="hubs" value=$smarty.request.shipping_id|fn_qwintry_get_hubs}

        <div class="control-group">
            <label class="control-label" for="ship_mode">{__("qwintry_hub")}</label>
            <div class="controls">
                <select id="ship_mode" name="shipping_data[service_params][hub]"{if empty($shipping.service_params.api_key)} disabled{/if}>
                    <option value="">---</option>
                    {foreach from=$hubs item="hub"}
                        <option value="{$hub.code}"{if $shipping.service_params.hub == $hub.code} selected{/if}>{$hub.name}</option>
                    {/foreach}
                </select>
            </div>
        </div>

    <div class="control-group">
        <label class="control-label" for="ship_qwintry_default_weight">{__("qwintry_default_shipment_weight")}</label>
        <div class="controls">
            <input id="ship_qwintry_default_weight" type="text" name="shipping_data[service_params][default_weight]" size="30" value="{$shipping.service_params.default_weight|default:4}" />
        </div>
    </div>

    {include file="common/subheader.tpl" title=__("qwintry_box_sizes")}

    <div class="control-group">
        <label class="control-label" for="ship_qwintry_height">{__("ship_fedex_height")}</label>
        <div class="controls">
            <input id="ship_qwintry_height" type="text" name="shipping_data[service_params][dimensions][box_height]" size="30" value="{$shipping.service_params.dimensions.box_height}" />
        </div>
    </div>

    <div class="control-group">
        <label class="control-label" for="ship_qwintry_width">{__("ship_fedex_width")}</label>
        <div class="controls">
            <input id="ship_qwintry_width" type="text" name="shipping_data[service_params][dimensions][box_width]" size="30" value="{$shipping.service_params.dimensions.box_width}"/>
        </div>
    </div>

    <div class="control-group">
        <label class="control-label" for="ship_qwintry_length">{__("ship_fedex_length")}</label>
        <div class="controls">
            <input id="ship_qwintry_length" type="text" name="shipping_data[service_params][dimensions][box_length]" size="30" value="{$shipping.service_params.dimensions.box_length}"/>
        </div>
    </div>
</fieldset>
