<div id="waiap-app"></div>
<script src={$waiap_pwall_bundle}></script>
<link href={$waiap_pwall_css} rel=stylesheet>
<script src="https://cdn.jsdelivr.net/gh/waiap/javascript-sdk@2.0.0/dist/2.0.0/pwall-sdk.min.js"></script>
<script>
    const client = new PWall('{$waiap_enviroment}', false);
    var backoffice = client.backoffice();
    backoffice.backendUrl('{$waiap_pwall_controller}');
    backoffice.appendTo("#waiap-app");
    backoffice.init();
</script>