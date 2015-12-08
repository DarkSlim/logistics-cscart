{if !empty($qwintry_data)}
<div id="content_qwintry">
    <fieldset>
        {include file="common/subheader.tpl" title=__("qwintry_shipment_params")}
        <form action="{""|fn_url}" method="post" name="qwintry_form" class="form-horizontal form-edit form-table">
            <input type="hidden" name="order_id" value="{$smarty.request.order_id}"/>

            <div class="control-group">
                <label class="control-label" for="ship_qwintry_weight">{__("weight")} (lbs)</label>

                <div class="controls">
                    <input id="ship_qwintry_weight" type="text" name="qwintry_data[box_weight]" size="30"
                           value="{$qwintry_data.service_params.default_weight}"/>
                </div>
            </div>

            <div class="control-group">
                <label class="control-label" for="ship_qwintry_height">{__("ship_fedex_height")}</label>

                <div class="controls">
                    <input id="ship_qwintry_height" type="text" name="qwintry_data[box_height]" size="30"
                           value="{$qwintry_data.service_params.dimensions.box_height}"/>
                </div>
            </div>

            <div class="control-group">
                <label class="control-label" for="ship_qwintry_width">{__("ship_fedex_width")}</label>

                <div class="controls">
                    <input id="ship_qwintry_width" type="text" name="qwintry_data[box_width]" size="30"
                           value="{$qwintry_data.service_params.dimensions.box_width}"/>
                </div>
            </div>

            <div class="control-group">
                <label class="control-label" for="ship_qwintry_length">{__("ship_fedex_length")}</label>

                <div class="controls">
                    <input id="ship_qwintry_length" type="text" name="qwintry_data[box_length]" size="30"
                           value="{$qwintry_data.service_params.dimensions.box_length}"/>
                </div>
            </div>
            <div class="control-group">
                <div class="controls">
                    {include file="buttons/button.tpl" but_role="submit" but_name="dispatch[orders.qwintry_create_shipment]" but_text=__("qwintry_create_shipment")}
                </div>
            </div>
        </form>
    </fieldset>
</div>
{/if}