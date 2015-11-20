function fn_qwintry_calculate_total_shipping_cost()
{
    params = [];
    parents = Tygh.$('#shipping_rates_list');
    radio = Tygh.$('input[type=radio]:checked', parents);
    select = Tygh.$('select', parents);

    Tygh.$.each(radio, function(id, elm) {
        params.push({name: elm.name, value: elm.value});
    });

    Tygh.$.each(select, function(id, elm) {
        params.push({name: elm.name, value: elm.value});
    });

    url = fn_url('checkout.checkout');

    for (var i in params) {
        url += '&' + params[i]['name'] + '=' + escape(params[i]['value']);
    }

    Tygh.$.ceAjax('request', url, {
        result_ids: 'shipping_rates_list,checkout_info_summary_*,checkout_info_order_info_*',
        method: 'get',
        full_render: true
    });
}