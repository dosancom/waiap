<form action="javascript:void(0);" class="payment-wall">
    <p id="waiap-terms-alert" class="alert alert-danger" role="alert" data-alert="danger">
          {l s='Please make sure you\'ve accepted the terms and conditions.' mod='waiap'}
    </p>
    <div id="waiap-app"></div>
    <script type="text/javascript">
        $(document).trigger('waiap_draw_payment_wall');
    </script>
</form>