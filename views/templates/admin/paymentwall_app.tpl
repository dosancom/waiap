<div id="app"></div>
<script src={$waiap_pwall_bundle}></script>
<link href={$waiap_pwall_css} rel=stylesheet>
<script
      type="text/javascript"
      data-placeholder="#app"
      data-backoffice="true"
      data-endpoint={$waiap_pwall_controller}
      data-amount="0"
      data-currency={$currency->iso_code}
      src={$waiap_pwall_app}>
</script>
<script>
    document.addEventListener('DOMContentLoaded', function(){
       //document.dispatchEvent(new Event('payment_wall_load_app'));
       window.PaymentWall.start();
    });
</script>