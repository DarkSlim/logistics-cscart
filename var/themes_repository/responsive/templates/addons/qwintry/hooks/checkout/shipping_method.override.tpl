{if $display == "radio"}
    <p class="ty-shipping-options__method">
        <input type="radio" class="ty-valign" id="sh_{$group_key}_{$shipping.shipping_id}" name="shipping_ids[{$group_key}]" value="{$shipping.shipping_id}" onclick="fn_calculate_total_shipping_cost();" {$checked} />
        <label for="sh_{$group_key}_{$shipping.shipping_id}" class="ty-valign">{$shipping.shipping} {$delivery_time} - {$rate nofilter}</label>
    </p>

    {if $shipping.module == 'qwintry' && in_array($cart.user_data.s_country, ['RU', 'BY', 'KZ'])}
        <div class="qwintry-menu{if $checked == ''} hidden{/if}">
            <p>
                <input type="radio" class="ty-valign" id="sh_{$group_key}_{$shipping.shipping_id}_qwintry_type_courier" name="qwintry[{$group_key}][{$shipping.shipping_id}][type]" value="courier" onclick="fn_qwintry_calculate_total_shipping_cost();" {if empty($cart.qwintry.$group_key.{$shipping.shipping_id}.type) || $cart.qwintry.$group_key.{$shipping.shipping_id}.type == 'courier'}checked {/if}/>
                <label for="sh_{$group_key}_{$shipping.shipping_id}_qwintry_type_courier" class="ty-valign">{__("qwintry_courier")}</label>
            </p>
            <p>
                <input type="radio" class="ty-valign" id="sh_{$group_key}_{$shipping.shipping_id}_qwintry_type_pickup" name="qwintry[{$group_key}][{$shipping.shipping_id}][type]" value="pickup" onclick="fn_qwintry_calculate_total_shipping_cost();" {if $cart.qwintry.$group_key.{$shipping.shipping_id}.type == 'pickup'}checked {/if}/>
                <label for="sh_{$group_key}_{$shipping.shipping_id}_qwintry_type_pickup" class="ty-valign">{__("qwintry_pickup")}</label>&nbsp;&nbsp;&nbsp;
                {assign var="pickup_points" value=$cart.user_data.s_country|fn_qwintry_get_pickup_points:$shipping.shipping_id}
                <select name="qwintry[{$group_key}][{$shipping.shipping_id}][point]" onchange="fn_qwintry_calculate_total_shipping_cost();">
                   {foreach from=$pickup_points item="point"}
                       <option value="{$point.code}"{if $cart.qwintry.$group_key.{$shipping.shipping_id}.point == $point.code} selected{/if}>{$point.name}</option>
                   {/foreach}
                </select>
                <br /><a target="_blank" href="http://logistics.qwintry.com/cities">{__("qwintry_view_all_points")}</a>
            </p>
        </div>

        {assign var="country_data" value=$cart.user_data.s_country|fn_qwintry_get_country_data:$shipping.shipping_id}
        {foreach from=$country_data item="data"}
            {if $data.bold}<b>{/if}
            {$data.header} {$data.content}<br />
            {if $data.bold}</b>{/if}
        {/foreach}
    {/if}
{elseif $display == "select"}
    <option value="{$shipping.shipping_id}" {$selected}>{$shipping.shipping} {$delivery_time} - {$rate nofilter}</option>

{elseif $display == "show"}
    <p>
        {$strong_begin}{$rate.name} {$delivery_time} - {$rate nofilter}{$strong_begin}
    </p>
{/if}